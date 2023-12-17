<?php

namespace MediaWiki\Wikispeech\Api;

/**
 * @file
 * @ingroup API
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 *
 * Advanced API module to synthesize text as speech with extra performance metrics.
 *
 * This module extends the standard API by accepting an additional parameter "advanced".
 * When set, it returns enhanced metrics such as the processing time in microseconds.
 *
 * @since 0.2.0
 */

use ApiBase;
use ApiMain;
use ConfigException;
use ExternalStoreException;
use FormatJson;
use InvalidArgumentException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Wikispeech\Segment\DeletedRevisionException;
use MediaWiki\Wikispeech\Segment\RemoteWikiPageProviderException;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\Segment\TextFilter\Sv\SwedishFilter;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\SpeechoidConnectorException;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWiki\Wikispeech\VoiceHandler;
use MWException;
use MWTimestamp;
use Psr\Log\LoggerInterface;
use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

class ApiWikispeechAdvancedListen extends ApiBase {

	/** @var \Config */
	private $config;

	/** @var WANObjectCache */
	private $cache;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var LoggerInterface */
	private $logger;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var VoiceHandler */
	private $voiceHandler;

	/** @var ListenMetricsEntry */
	private $listenMetricEntry;

	/**
	 * Constructor.
	 *
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param WANObjectCache $cache
	 * @param RevisionStore $revisionStore
	 * @param HttpRequestFactory $requestFactory
	 * @param string $modulePrefix
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		WANObjectCache $cache,
		RevisionStore $revisionStore,
		HttpRequestFactory $requestFactory,
		string $modulePrefix = ''
	) {
		$this->config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		$this->cache = $cache;
		$this->revisionStore = $revisionStore;
		$this->requestFactory = $requestFactory;
		$this->logger = LoggerFactory::getInstance( 'WikispeechAdvanced' );
		$this->speechoidConnector = new SpeechoidConnector( $this->config, $requestFactory );
		$this->utteranceStore = new UtteranceStore();
		$this->voiceHandler = new VoiceHandler(
			$this->logger,
			$this->config,
			$this->speechoidConnector,
			MediaWikiServices::getInstance()->getMainWANObjectCache()
		);
		$this->listenMetricEntry = new ListenMetricsEntry();
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 * Execute an API request.
	 *
	 * @since 0.2.0
	 */
	public function execute() {
		$startTime = microtime( true );
		$this->listenMetricEntry->setTimestamp( MWTimestamp::getInstance() );

		$inputParameters = $this->extractRequestParams();
		$this->validateParameters( $inputParameters );

		$language = $inputParameters['lang'];
		$voice = $inputParameters['voice'];
		if ( !$voice ) {
			$voice = $this->voiceHandler->getDefaultVoice( $language );
			if ( !$voice ) {
				throw new ConfigException( 'Invalid default voice configuration.' );
			}
		}

		// Synthesize speech based on parameters.
		if ( isset( $inputParameters['revision'] ) ) {
			$response = $this->getUtteranceForRevisionAndSegment(
				$voice,
				$language,
				$inputParameters['revision'],
				$inputParameters['segment'],
				$inputParameters['consumer-url'] ?? null
			);
		} else {
			try {
				$speechoidResponse = $this->speechoidConnector->synthesize(
					$language,
					$voice,
					$inputParameters
				);
			} catch ( \Throwable $exception ) {
				$this->dieWithException( $exception );
			}
			$response = [
				'audio' => $speechoidResponse['audio_data'],
				'tokens' => $speechoidResponse['tokens']
			];
		}

		// If "advanced" flag is set, add extra performance metrics.
		if ( !empty( $inputParameters['advanced'] ) ) {
			$elapsed = microtime( true ) - $startTime;
			$response['advancedMetrics'] = [
				'processingTimeMicroseconds' => intval( 1000000 * $elapsed )
			];
		}

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$response
		);

		// Record additional metrics (similar to the standard module).
		$charactersInSegment = 0;
		foreach ( $response['tokens'] as $token ) {
			$charactersInSegment += mb_strlen( $token['orth'] );
			$charactersInSegment += 1; // Count whitespace/sentence end
		}
		$this->listenMetricEntry->setCharactersInSegment( $charactersInSegment );
		$this->listenMetricEntry->setLanguage( $inputParameters['lang'] );
		$this->listenMetricEntry->setVoice( $voice );
		$this->listenMetricEntry->setPageRevisionId( $inputParameters['revision'] ?? 0 );
		$this->listenMetricEntry->setSegmentHash( $inputParameters['segment'] ?? '' );
		$this->listenMetricEntry->setConsumerUrl( $inputParameters['consumer-url'] ?? '' );
		$this->listenMetricEntry->setRemoteWikiHash(
			UtteranceStore::evaluateRemoteWikiHash( $inputParameters['consumer-url'] ?? '' )
		);
		$this->listenMetricEntry->setMillisecondsSpeechInUtterance(
			$response['tokens'][ count( $response['tokens'] ) - 1 ]['endtime'] ?? 0
		);
		$this->listenMetricEntry->setMicrosecondsSpent( intval( 1000000 * ( microtime( true ) - $startTime ) ) );

		if ( empty( $inputParameters['skip-journal-metrics'] )
			&& $this->config->get( 'WikispeechListenDoJournalMetrics' )
		) {
			$metricsJournal = new ListenMetricsEntryFileJournal( $this->config );
			try {
				$metricsJournal->appendEntry( $this->listenMetricEntry );
			} catch ( \Throwable $exception ) {
				$this->logger->warning(
					'Exception caught while appending to metrics journal {exception}',
					[ 'exception' => $exception ]
				);
			}
		}
	}

	/**
	 * Retrieve the matching utterance for a given revision id and segment hash.
	 *
	 * @since 0.2.0
	 * @param string $voice
	 * @param string $language
	 * @param int $revisionId
	 * @param string $segmentHash
	 * @param string|null $consumerUrl
	 * @return array
	 */
	private function getUtteranceForRevisionAndSegment(
		string $voice,
		string $language,
		int $revisionId,
		string $segmentHash,
		?string $consumerUrl
	): array {
		$segmentPageFactory = new SegmentPageFactory(
			$this->cache,
			MediaWikiServices::getInstance()->getConfigFactory()
		);
		try {
			$segmentPageResponse = $segmentPageFactory
				->setSegmentBreakingTags( null )
				->setRemoveTags( null )
				->setUseSegmentsCache( true )
				->setUseRevisionPropertiesCache( true )
				->setContextSource( $this->getContext() )
				->setRevisionStore( $this->revisionStore )
				->setHttpRequestFactory( MediaWikiServices::getInstance()->getHttpRequestFactory() )
				->setConsumerUrl( $consumerUrl )
				->setRequirePageRevisionProperties( true )
				->segmentPage( null, $revisionId );
		} catch ( RemoteWikiPageProviderException $e ) {
			$this->dieWithError( [
				'apierror-wikispeech-advanced-listen-failed-getting-page',
				$revisionId,
				$consumerUrl
			] );
		} catch ( DeletedRevisionException $e ) {
			$this->dieWithError( 'apierror-wikispeech-advanced-listen-deleted-revision' );
		}
		$segment = $segmentPageResponse->getSegments()->findFirstItemByHash( $segmentHash );
		if ( $segment === null ) {
			throw new MWException( 'No such segment. The segment hash may be invalid or created with incompatible settings.' );
		}
		$this->listenMetricEntry->setSegmentIndex( $segmentPageResponse->getSegments()->indexOf( $segment ) );
		$this->listenMetricEntry->setPageId( $segmentPageResponse->getPageId() );
		$this->listenMetricEntry->setPageTitle( $segmentPageResponse->getTitle()->getText() );

		return $this->getUtterance(
			$consumerUrl,
			$voice,
			$language,
			$segmentPageResponse->getPageId(),
			$segment
		);
	}

	/**
	 * Return the utterance corresponding to the request.
	 *
	 * @since 0.2.0
	 * @param string|null $consumerUrl
	 * @param string $voice
	 * @param string $language
	 * @param int $pageId
	 * @param Segment $segment
	 * @return array
	 */
	private function getUtterance(
		?string $consumerUrl,
		string $voice,
		string $language,
		int $pageId,
		Segment $segment
	): array {
		if ( !$pageId ) {
			throw new InvalidArgumentException( 'Page ID must be set.' );
		}
		$segmentHash = $segment->getHash();
		if ( $segmentHash === null ) {
			throw new InvalidArgumentException( 'Segment hash must be set.' );
		}
		$utterance = $this->utteranceStore->findUtterance(
			$consumerUrl,
			$pageId,
			$language,
			$voice,
			$segmentHash
		);
		$this->listenMetricEntry->setUtteranceSynthesized( $utterance === null );
		if ( !$utterance ) {
			$segmentText = '';
			foreach ( $segment->getContent() as $content ) {
				$segmentText .= $content->getString();
			}
			$this->validateText( $segmentText );
			$ssml = null;
			if ( $language === 'sv' ) {
				$textFilter = new SwedishFilter( $segmentText );
				$ssml = $textFilter->process();
			}
			if ( $ssml !== null ) {
				$speechoidResponse = $this->speechoidConnector->synthesize(
					$language,
					$voice,
					[ 'ssml' => $ssml ]
				);
			} else {
				$speechoidResponse = $this->speechoidConnector->synthesizeText(
					$language,
					$voice,
					$segmentText
				);
			}
			$this->utteranceStore->createUtterance(
				$consumerUrl,
				$pageId,
				$language,
				$voice,
				$segmentHash,
				$speechoidResponse['audio_data'],
				FormatJson::encode( $speechoidResponse['tokens'] )
			);
			return [
				'audio' => $speechoidResponse['audio_data'],
				'tokens' => $speechoidResponse['tokens']
			];
		}
		return [
			'audio' => $utterance->getAudio(),
			'tokens' => FormatJson::parse(
				$utterance->getSynthesisMetadata(),
				FormatJson::FORCE_ASSOC
			)->getValue()
		];
	}

	/**
	 * Validate input text length.
	 *
	 * @since 0.2.0
	 * @param string $text
	 */
	private function validateText( $text ) {
		$numChars = mb_strlen( $text );
		$maxChars = $this->config->get( 'WikispeechListenMaximumInputCharacters' );
		if ( $numChars > $maxChars ) {
			$this->dieWithError( [
				'apierror-wikispeech-advanced-listen-invalid-input-too-long',
				$maxChars,
				$numChars
			] );
		}
	}

	/**
	 * Specify allowed parameters.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			[
				'lang' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true,
				],
				'text' => [
					ParamValidator::PARAM_TYPE => 'string',
				],
				'revision' => [
					ParamValidator::PARAM_TYPE => 'integer',
				],
				'segment' => [
					ParamValidator::PARAM_TYPE => 'string',
				],
				'voice' => [
					ParamValidator::PARAM_TYPE => 'string',
				],
				'consumer-url' => [
					ParamValidator::PARAM_TYPE => 'string',
				],
				'advanced' => [
					ParamValidator::PARAM_TYPE => 'boolean',
					ParamValidator::PARAM_DEFAULT => false,
				],
				'skip-journal-metrics' => [
					ParamValidator::PARAM_TYPE => 'boolean',
					ParamValidator::PARAM_DEFAULT => false,
				],
			]
		);
	}

	/**
	 * Provide example usage messages.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=wikispeech-advanced-listen&format=json&lang=en&text=Read this'
			=> 'apihelp-wikispeech-advanced-listen-example-1',
			'action=wikispeech-advanced-listen&format=json&lang=en&text=Read this&voice=cmu-slt-hsmm'
			=> 'apihelp-wikispeech-advanced-listen-example-2',
			'action=wikispeech-advanced-listen&format=json&lang=en&revision=1&segment=hash1234'
			=> 'apihelp-wikispeech-advanced-listen-example-3',
			'action=wikispeech-advanced-listen&format=json&lang=en&revision=1&segment=hash1234&consumer-url=https://consumer.url/w'
			=> 'apihelp-wikispeech-advanced-listen-example-4',
		];
	}
}
