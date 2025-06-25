<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ConfigException;
use ExternalStoreException;
use FormatJson;
use InvalidArgumentException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Wikispeech\InputTextValidator;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\TextFilter\Sv\SwedishFilter;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\SpeechoidConnectorException;
use MediaWiki\Wikispeech\VoiceHandler;
use Psr\Log\LoggerInterface;

 /**
  * @since 0.1.11
  */

class UtteranceGenerator {

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var VoiceHandler */
	private $voiceHandler;

	/** @var LoggerInterface */
	private $logger;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var InputTextValidator */
	private $InputTextValidator;

	public function __construct(
		SpeechoidConnector $speechoidConnector,
		UtteranceStore $utteranceStore
	) {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->speechoidConnector = $speechoidConnector;

		$this->utteranceStore = $utteranceStore;
	}

	/**
	 * Sets a custom UtteranceStore instance, typically for testing.
	 *
	 * @since 0.1.11
	 * @param UtteranceStore $utteranceStore
	 * @return void
	 */
	public function setUtteranceStore( UtteranceStore $utteranceStore ): void {
		$this->utteranceStore = $utteranceStore;
	}

	/**
	 * Return the utterance corresponding to the request.
	 *
	 * These are either retrieved from storage or synthesize (and then stored).
	 *
	 * @since 0.1.5
	 * @param string|null $consumerUrl
	 * @param string $voice
	 * @param string $language
	 * @param int $pageId
	 * @param Segment $segment
	 * @return array Containing base64 'audio' and synthesisMetadata 'tokens'.
	 * @throws ExternalStoreException
	 * @throws ConfigException
	 * @throws InvalidArgumentException
	 * @throws SpeechoidConnectorException
	 */
	public function getUtterance(
		?string $consumerUrl,
		string $voice,
		string $language,
		int $pageId,
		Segment $segment
	) {
		if ( $pageId !== 0 && !$pageId ) {
			throw new InvalidArgumentException( 'Page ID must be set.' );
		}
		$segmentHash = $segment->getHash();
		if ( $segmentHash === null ) {
			throw new InvalidArgumentException( 'Segment hash must be set.' );
		}
		if ( !$voice ) {
			$voice = $this->voiceHandler->getDefaultVoice( $language );
			if ( !$voice ) {
				throw new ConfigException( "Invalid default voice configuration." );
			}
		}
		$utterance = $this->utteranceStore->findUtterance(
			$consumerUrl,
			$pageId,
			$language,
			$voice,
			$segmentHash
		);

		if ( !$utterance ) {
			$this->logger->debug( __METHOD__ . ': Creating new utterance for {pageId} {segmentHash}', [
				'pageId' => $pageId,
				'segmentHash' => $segment->getHash()
			] );

			// Make a string of all the segment contents.
			$segmentText = '';
			foreach ( $segment->getContent() as $content ) {
				$segmentText .= $content->getString();
			}

			$this->InputTextValidator = new InputTextValidator();
			$this->InputTextValidator->validateText( $segmentText );

			/** @var string $ssml text/xml Speech Synthesis Markup Language */
			$ssml = null;
			if ( $language === 'sv' ) {
				// @todo implement a per language selecting content text filter facade
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
				FormatJson::encode(
					$speechoidResponse['tokens']
				)
			);
			return [
				'audio' => $speechoidResponse['audio_data'],
				'tokens' => $speechoidResponse['tokens']
			];
		}
		$this->logger->debug( __METHOD__ . ': Using cached utterance for {pageId} {segmentHash}', [
			'pageId' => $pageId,
			'segmentHash' => $segmentHash
		] );
		return [
			'audio' => $utterance->getAudio(),
			'tokens' => FormatJson::parse(
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable synthesis metadata is set
				$utterance->getSynthesisMetadata(),
				FormatJson::FORCE_ASSOC
			)->getValue()
		];
	}
}
