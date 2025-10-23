<?php
namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */
use Exception;
use InvalidArgumentException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\Segment\SegmentMessagesFactory;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;

/** @var string MediaWiki installation path */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
/**
 * Maintenance script to pre synthesize error messages
 *
 * @since 0.1.13
 */
class PreSynthesizeMessages extends Maintenance {
	/** @var UtteranceGenerator */
	private $utteranceGenerator;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Wikispeech' );
		$this->addDescription( 'Pre synthesize error messages' );
		$this->addOption(
			'language',
			'Pre synthesize error messages with this language.',
			true,
			true,
			'l'
		);
		$this->addOption(
			'voice',
			'Pre synthesize error messages with this voice (language required).',
			false,
			true,
			'v'
		);
	}

	/**
	 * @return bool success
	 */
	public function execute() {
		$language = $this->getOption( 'language', null );
		$voice = $this->getOption( 'voice', null );
		$this->utteranceGenerator = WikispeechServices::getUtteranceGenerator();

		// @todo These messages are arbitrary to show that it works.
		// In the future we probably want to generate a list of messages
		$errorMessageKeys = [ 'wikispeech-error-loading-audio-title', 'wikispeech-error-generate-preview-title' ];
		foreach ( $errorMessageKeys as $messageKey ) {
			$this->synthesizeErrorMessage( $messageKey, $language, $voice );
		}
		return true;
	}

	/**
	 * Synthesize the error messages
	 *
	 * @param string $messageKey
	 * @param string $language
	 * @param string $voice
	 */
	public function synthesizeErrorMessage( $messageKey, $language, $voice ) {
		try {
			$services = MediaWikiServices::getInstance();
			$segmentMessagesFactory = new SegmentMessagesFactory(
				$services->getMainWANObjectCache(),
				$this->getConfig()
			);
			$segmentList = $segmentMessagesFactory->segmentMessage( $messageKey, $language );
			if ( !$voice ) {
				$voiceHandler = WikispeechServices::getVoiceHandler();
				$voice = $voiceHandler->getDefaultVoice( $language );
				if ( !$voice ) {
					throw new InvalidArgumentException(
						"No default voice found for language: $language"
					);
				}
			}
			foreach ( $segmentList->getSegments() as $segment ) {
				$segmentHash = $segment->getHash();
				if ( $segmentHash === null ) {
					throw new InvalidArgumentException(
						"Segment hash is null for message key: $messageKey"
					);
				}

				$this->utteranceGenerator->getUtterance(
				null,
				$voice,
				$language,
				0,
				$segment,
				$messageKey
				);
			}

			$this->output( "Successfully pre-synthesized message with message key: $messageKey\n" );
		} catch ( Exception $e ) {
			$this->output( "Error synthesizing message with message key: $messageKey " . $e->getMessage() . "\n" );
		}
	}
}
 /** @var string This class, required to start via Maintenance. */
$maintClass = PreSynthesizeMessages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
