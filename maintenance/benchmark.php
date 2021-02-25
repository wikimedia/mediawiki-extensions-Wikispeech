<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use EmptyBagOStuff;
use Maintenance;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Segment\Segmenter;
use RequestContext;
use Title;

/** @var string MediaWiki installation path */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to evaluate interesting resource use metrics
 * related to executing Wikispeech and Speechoid on a page.
 *
 * php extensions/Wikispeech/maintenance/benchmark.php -p Barack_Obama
 *
 * @since 0.1.8
 */
class Benchmark extends Maintenance {

	/** @var VoiceHandler */
	private $voiceHandler;

	/** @var Segmenter */
	private $segmenter;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var bool Whether or not ctrl-c has been pressed. */
	private $caughtSigInt;

	/** @var array */
	private $segments;

	/** @var int */
	private $synthesizeResponseTimeoutSeconds;

	/** @var float|int */
	private $millisecondsSpentSegmenting;

	/** @var int */
	private $numberOfSuccessfullySynthesizedSegments;

	/** @var int|float */
	private $totalMillisecondsSpentSynthesizing;

	/** @var int */
	private $totalMillisecondsSynthesizedVoice;

	/** @var int */
	private $totalNumberOfTokensSynthesizedVoice;

	/** @var int */
	private $totalBytesSynthesizedVoice;

	/** @var int */
	private $totalNumberOfTokenCharactersSynthesizedVoice;

	/** @var string */
	private $language;

	/** @var string */
	private $voice;

	/** @var Title */
	private $title;

	/**
	 * Benchmark constructor.
	 *
	 * @since 0.1.8
	 */
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Wikispeech' );
		$this->addDescription( 'Benchmark use of resources.' );
		$this->addOption(
			'language',
			'Synthesized language. If not set, page language is selected.',
			false,
			true,
			'l'
		);
		$this->addOption(
			'voice',
			'Synthesized voice. If not set, default voice for language is selected.',
			false,
			true,
			'v'
		);
		$this->addOption(
			'page',
			'Title of page to be segmented and synthesized.',
			true,
			true,
			'p'
		);
		$this->addOption(
			'timeout',
			'Maximum number of seconds to await Speechoid synthesize HTTP response. Defaults to 240.',
			false,
			true,
			't'
		);

		$this->caughtSigInt = false;
		declare( ticks = 1 );
		pcntl_async_signals( true );
		pcntl_signal( SIGINT, [ $this, 'signalHandler' ] );
	}

	/**
	 * Clean ctrl-c
	 */
	public function signalHandler() {
		$this->caughtSigInt = true;
	}

	private function executeSetUp() {
		// Non PHP core classes aren't available prior to this point,
		// i.e. we can't initialize the fields in the constructor,
		// and we have to be lenient for mocked instances set by tests.

		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );

		$emptyWanCache = new EmptyBagOStuff();

		$logger = LoggerFactory::getInstance( 'Wikispeech' );

		if ( !$this->speechoidConnector ) {
			$this->speechoidConnector = new SpeechoidConnector( $config );
		}
		if ( !$this->voiceHandler ) {
			$this->voiceHandler = new VoiceHandler(
				$logger,
				$config,
				$this->speechoidConnector,
				// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
				$emptyWanCache
			);
		}
		if ( !$this->segmenter ) {
			$this->segmenter = new Segmenter(
				new RequestContext(),
				// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
				$emptyWanCache
			);
		}
	}

	private function executeValidateInput() {
		$this->language = '';
		$this->voice = '';
		$this->title = Title::newFromText( $this->getOption( 'page' ) );
		if ( !$this->title->isKnown() ) {
			$this->output( "Error: Page is not known.\n" );
			return false;
		}
		if ( $this->title->isSpecialPage() ) {
			$this->output( "Error: Page is a SpecialPage.\n" );
			return false;
		}

		if ( !$this->getOption( 'language', false ) ) {
			$language = $this->title->getPageLanguage();
			if ( !$language ) {
				$this->output( "Error: Unable to read language for page. Use parameter language.\n" );
				return false;
			}
			$this->language = $language->getCode();
			$this->output( "Language $this->language set from page default.\n" );
		} else {
			$this->language = $this->getOption( 'language' );
			$this->output( "Language $this->language set from option.\n" );
			// todo validate language
		}

		if ( !$this->getOption( 'voice', false ) ) {
			$this->voice = $this->voiceHandler->getDefaultVoice( $this->language );
			if ( !$this->voice ) {
				// This will never occur unless underlying default voice logic change.
				// I.e. if the default voice cannot be found
				// then your language must not be defined (in Speechoid or locally)
				$this->output( "Error: No default voice for language $this->language. Use parameter voice.\n" );
				return false;
			}
			$this->output( "Voice $this->voice set from default for language $this->language.\n" );
		} else {
			$this->voice = $this->getOption( 'voice' );
			$this->output( "Voice $this->voice set from option.\n" );
			// todo validate voice of language
		}

		$this->synthesizeResponseTimeoutSeconds = intval(
			$this->getOption( 'timeout', 240 )
		);

		return true;
	}

	private function executeSegmenting() {
		// todo add these three as options?
		$removeTags = null;
		$segmentBreakingTags = null;
		$revisionId = null;

		$this->output( 'Benchmarking page ' .
			"$this->title->getText() using language " .
			"$this->language and voice " .
			"$this->voice.\n"
		);

		// We don't want to count time spent rendering to segmenting time,
		// so we call the segmenter twice. Segmenting cache is turned off.
		$this->output( "Allowing for MediaWiki to render page...\n" );
		$this->segmenter->segmentPage(
			$this->title, $removeTags, $segmentBreakingTags, $revisionId
		);

		$this->output( "Segmenting...\n" );
		$startSegmenting = microtime( true ) * 1000;
		$this->segments = $this->segmenter->segmentPage(
			$this->title, $removeTags, $segmentBreakingTags, $revisionId
		);
		$endSegmenting = microtime( true ) * 1000;
		$this->millisecondsSpentSegmenting = $endSegmenting - $startSegmenting;
	}

	private function executeSynthesizing() {
		$this->numberOfSuccessfullySynthesizedSegments = 0;

		$this->totalBytesSynthesizedVoice = 0;
		$this->totalNumberOfTokenCharactersSynthesizedVoice = 0;
		$this->totalNumberOfTokensSynthesizedVoice = 0;
		$this->totalMillisecondsSynthesizedVoice = 0;
		$this->output( 'Synthesizing ' . count( $this->segments ) . " segments... \n" );
		$this->output( "Press ^C to abort and calculate on evaluated state.\n" );
		$this->totalMillisecondsSpentSynthesizing = 0;

		$failures = '';

		$progressCounterLength = 40;
		$segmentCounter = 0;
		$progressCounter = 0;
		foreach ( $this->segments as $segment ) {
			if ( $this->caughtSigInt ) {
				break;
			}
			$segmentCounter++;

			$segmentText = '';
			foreach ( $segment['content'] as $content ) {
				$segmentText .= $content->string;
			}

			$attempt = 0;
			$maximumAttempts = 3;
			$retriesLeft = $maximumAttempts;
			while ( true ) {
				$attempt++;
				$startSynthesizing = microtime( true ) * 1000;
				try {
					$speechoidResponse = $this->speechoidConnector->synthesize(
						$this->language, $this->voice, $segmentText, $this->synthesizeResponseTimeoutSeconds
					);
					$endSynthesizing = microtime( true ) * 1000;
					$millisecondsSpentSynthesizingSegment = $endSynthesizing - $startSynthesizing;
					$this->totalMillisecondsSpentSynthesizing += $millisecondsSpentSynthesizingSegment;

					$bytesSynthesizedVoiceInSegment = mb_strlen( $speechoidResponse['audio_data'] );
					$this->totalBytesSynthesizedVoice += $bytesSynthesizedVoiceInSegment;

					$numberOfTokensInSegment = count( $speechoidResponse[ 'tokens' ] );
					$this->totalNumberOfTokensSynthesizedVoice += $numberOfTokensInSegment;

					$millisecondsSynthesizedVoiceInSegment =
						$speechoidResponse['tokens'][ $numberOfTokensInSegment - 1 ]['endtime'];
					$this->totalMillisecondsSynthesizedVoice += $millisecondsSynthesizedVoiceInSegment;

					$charactersInSegmentTokens = 0;
					foreach ( $speechoidResponse['tokens'] as $token ) {
						$charactersInSegmentTokens += mb_strlen( $token['orth'] );
					}
					$this->totalNumberOfTokenCharactersSynthesizedVoice += $charactersInSegmentTokens;

					if ( $attempt > 1 ) {
						$this->output( strval( $attempt ) );
					} else {
						$this->output( '.' );
					}
					$this->numberOfSuccessfullySynthesizedSegments++;
				} catch ( SpeechoidConnectorException $speechoidConnectorException ) {
					$millisecondsSpentBeforeException = ( microtime( true ) * 1000 ) - $startSynthesizing;
					$failures .= "\nException $millisecondsSpentBeforeException milliseconds after request.\n";
					$failures .= $speechoidConnectorException->getText() . "\n";
					$retriesLeft--;
					if ( $retriesLeft == 0 ) {
						$failures .= "Giving up after attempt #$attempt. Segment ignored.\n";
						$failures .= $segmentText;
						$failures .= "\n";
						$this->output( 'E' );
					} else {
						continue;
					}
				}
				$progressCounter++;
				if ( $progressCounter === $progressCounterLength ) {
					$progressCounter = 0;

					$eta = ', ETA ~';
					$meanMillisecondsSpentSynthesizingPerSegment =
						$this->totalMillisecondsSpentSynthesizing / $this->numberOfSuccessfullySynthesizedSegments;
					$millisecondsEta = intval( count( $this->segments ) - $segmentCounter )
						* $meanMillisecondsSpentSynthesizingPerSegment;
					if ( $millisecondsEta < 1000 ) {
						$eta .= $millisecondsEta . ' ms';
					} elseif ( $millisecondsEta < 1000 * 60 ) {
						$eta .= intdiv( $millisecondsEta, 1000 ) . ' seconds';
					} else {
						$eta .= intdiv( $millisecondsEta, 1000 * 60 ) . ' minutes';
					}
					$eta .= ' (~' .	intdiv( $meanMillisecondsSpentSynthesizingPerSegment, 1000 ) . 's/seg)';
					$this->output( ' ' . $segmentCounter . ' / ' . count( $this->segments ) . $eta . "\n" );
				}
				break;
			}
		}

		if ( $failures ) {
			$this->output( "\n" );
			$this->output( $failures );
			$this->output( "\n" );
		}
	}

	/**
	 * @return bool success
	 * @since 0.1.8
	 */
	public function execute() {
		$this->executeSetUp();
		if ( !$this->executeValidateInput() ) {
			return false;
		}
		$this->executeSegmenting();
		$this->executeSynthesizing();

		$this->output( "\n\n" );
		$this->output( "Benchmark results\n" );
		$this->output( "-----------------\n" );
		$this->output( "\n" );

		$this->output( 'Number of segments: ' .
			count( $this->segments ) . "\n" );
		$this->output( "Milliseconds spent segmenting: $this->millisecondsSpentSegmenting\n" );

		$meanMillisecondsSpentSegmentingPerSegment =
			$this->millisecondsSpentSegmenting / count( $this->segments );

		$this->output( 'Mean milliseconds spent segmenting per segment: ' .
			"$meanMillisecondsSpentSegmentingPerSegment\n" );

		if ( $this->numberOfSuccessfullySynthesizedSegments === 0 ) {
			$this->output( "Nothing synthesized, no further metrics available.\n" );
			exit( 1 );
		}

		$this->totalMillisecondsSpentSynthesizing = intval( $this->totalMillisecondsSpentSynthesizing );
		$this->totalMillisecondsSynthesizedVoice = intval( $this->totalMillisecondsSynthesizedVoice );

		$meanMillisecondsSynthesizingPerToken =
			$this->totalMillisecondsSynthesizedVoice / $this->totalNumberOfTokensSynthesizedVoice;
		$meanMillisecondsSynthesizingPerCharacter =
			$this->totalMillisecondsSynthesizedVoice / $this->totalNumberOfTokenCharactersSynthesizedVoice;
		$meanBytesSynthesizedVoicePerToken =
			$this->totalBytesSynthesizedVoice / $this->totalNumberOfTokensSynthesizedVoice;
		$meanBytesSynthesizedVoicePerCharacter =
			$this->totalBytesSynthesizedVoice / $this->totalNumberOfTokenCharactersSynthesizedVoice;

		$meanTokensPerSegment =
			$this->totalNumberOfTokensSynthesizedVoice / $this->numberOfSuccessfullySynthesizedSegments;
		$meanTokenCharactersPerSegment =
			$this->totalNumberOfTokenCharactersSynthesizedVoice /
			$this->numberOfSuccessfullySynthesizedSegments;

		$meanMillisecondsSpentSegmentingPerToken =
			( $meanMillisecondsSpentSegmentingPerSegment * $this->numberOfSuccessfullySynthesizedSegments ) /
			$this->totalNumberOfTokensSynthesizedVoice;
		$meanMillisecondsSpentSegmentingPerTokenCharacter =
			( $meanMillisecondsSpentSegmentingPerSegment * $this->numberOfSuccessfullySynthesizedSegments ) /
			$this->totalNumberOfTokenCharactersSynthesizedVoice;

		$this->output( 'Mean milliseconds spent segmenting per token synthesized: ' .
			"$meanMillisecondsSpentSegmentingPerToken\n" );
		$this->output( 'Mean milliseconds spent segmenting per token character synthesized: ' .
			"$meanMillisecondsSpentSegmentingPerTokenCharacter\n" );

		if ( $this->numberOfSuccessfullySynthesizedSegments != count( $this->segments ) ) {
			$this->output( 'Warning! Not all segments synthesized, ' .
				"mean segmenting per token values might be slightly off.\n" );
		}

		$this->output( "\n" );

		$this->output( 'Number of synthesized segments: ' .
			"$this->numberOfSuccessfullySynthesizedSegments\n" );
		$this->output( "Number of synthesized tokens: $this->totalNumberOfTokensSynthesizedVoice\n" );
		$this->output( 'Number of synthesized token characters: ' .
			"$this->totalNumberOfTokenCharactersSynthesizedVoice\n" );

		$this->output( "\n" );

		$this->output( "Mean number of tokens per synthesized segment: $meanTokensPerSegment\n" );
		$this->output( 'Mean number of token characters per synthesized segment: ' .
			"$meanTokenCharactersPerSegment\n" );

		$this->output( "\n" );

		$this->output( 'Mean milliseconds synthesizing per token: ' .
			"$meanMillisecondsSynthesizingPerToken\n" );
		$this->output( 'Mean milliseconds synthesizing per token character: ' .
			"$meanMillisecondsSynthesizingPerCharacter\n" );

		$this->output( 'Mean bytes synthesized voice per token: ' .
			intval( $meanBytesSynthesizedVoicePerToken ) . "\n" );
		$this->output( 'Mean bytes synthesized voice per token character: ' .
			intval( $meanBytesSynthesizedVoicePerCharacter ) . "\n" );

		$this->output( "\n" );

		$this->output( "Milliseconds of synthesized voice: $this->totalMillisecondsSynthesizedVoice\n" );
		$this->output( 'Seconds of synthesized voice: ' .
			intdiv( $this->totalMillisecondsSynthesizedVoice, 1000 ) . "\n" );
		$this->output( 'Minutes of synthesized voice: ' .
			intdiv( $this->totalMillisecondsSynthesizedVoice, 1000 * 60 ) . "\n" );

		$this->output( "\n" );

		$this->output( "Milliseconds spent synthesizing: $this->totalMillisecondsSpentSynthesizing\n" );
		$this->output( 'Seconds spent synthesizing: ' .
			intdiv( $this->totalMillisecondsSpentSynthesizing, 1000 ) . "\n" );
		$this->output( 'Minutes spent synthesizing: ' .
			intdiv( $this->totalMillisecondsSpentSynthesizing, 1000 * 60 ) . "\n" );

		$this->output( "\n" );

		$this->output( "Synthesized voice bytes: $this->totalBytesSynthesizedVoice\n" );
		$this->output( 'Synthesized voice kilobytes: ' .
			intdiv( $this->totalBytesSynthesizedVoice, 1024 ) . "\n" );
		$this->output( 'Synthesized voice megabytes: ' .
			intdiv( $this->totalBytesSynthesizedVoice, 1024 * 1024 ) . "\n" );

		return true;
	}

}

/** @var string This class, required to start via Maintenance. */
$maintClass = Benchmark::class;

require_once RUN_MAINTENANCE_IF_MAIN;
