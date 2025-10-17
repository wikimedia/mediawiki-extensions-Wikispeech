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
use MediaWiki\Context\IContextSource;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Wikispeech\Api\ListenMetricsEntry;
use MediaWiki\Wikispeech\InputTextValidator;
use MediaWiki\Wikispeech\Segment\Segment;
use MediaWiki\Wikispeech\Segment\SegmentMessagesFactory;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\Segment\TextFilter\Sv\SwedishFilter;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\SpeechoidConnectorException;
use MediaWiki\Wikispeech\VoiceHandler;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WANObjectCache;

/**
 * @since 0.1.11
 */

class UtteranceGenerator {
	/**
	 * @var SegmentPageFactory
	 */
	private $segmentPageFactory;

	/** @var SegmentMessagesFactory */
	private $segmentMessagesFactory;

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

	/** @var IContextSource */
	private $context;

	/** @var WANObjectCache */
	private $cache;

	/**
	 * @since 0.1.13
	 * @param SpeechoidConnector $speechoidConnector
	 * @param UtteranceStore $utteranceStore
	 * @param SegmentPageFactory $segmentPageFactory
	 */
	public function __construct(
		SpeechoidConnector $speechoidConnector,
		UtteranceStore $utteranceStore,
		SegmentPageFactory $segmentPageFactory,
		WANObjectCache $cache,
		SegmentMessagesFactory $segmentMessagesFactory
	) {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->speechoidConnector = $speechoidConnector;
		$this->segmentPageFactory = $segmentPageFactory;
		$this->cache = $cache;
		$this->utteranceStore = $utteranceStore;
		$this->segmentMessagesFactory = $segmentMessagesFactory;
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
	 * @since 0.1.13
	 * @param IContextSource $context
	 */
	public function setContext( IContextSource $context ) {
		$this->context = $context;
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
	 * @param string|null $messageKey
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
		Segment $segment,
		?string $messageKey = null
	) {
		if ( $pageId === 0 && !$messageKey ) {
			throw new InvalidArgumentException( 'Message key must be set when Page ID is 0.' );
		}
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
		if ( $pageId === 0 ) {
			if ( $messageKey === null ) {
				throw new InvalidArgumentException( 'Message key must be set when Page ID is 0.' );
			}
			$utterance = $this->utteranceStore->findMessageUtterance(
				$consumerUrl,
				$messageKey,
				$language,
				$voice,
				$segmentHash
			);
		} else {
			$utterance = $this->utteranceStore->findUtterance(
				$consumerUrl,
				$pageId,
				$language,
				$voice,
				$segmentHash
			);
		}
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
			if ( $pageId === 0 ) {
				$this->utteranceStore->createMessageUtterance(
					$consumerUrl,
					$messageKey,
					$language,
					$voice,
					$segmentHash,
					$speechoidResponse['audio_data'],
					FormatJson::encode( $speechoidResponse['tokens'] )
				);
			} else {
				$this->utteranceStore->createUtterance(
					$consumerUrl,
					$pageId,
					$language,
					$voice,
					$segmentHash,
					$speechoidResponse['audio_data'],
					FormatJson::encode( $speechoidResponse['tokens'] )
				);
			}

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

	/**
	 * Retrieves the matching utterance for a given revision ID and segment hash.
	 *
	 * @since 0.1.13
	 * @param string $voice
	 * @param string $language
	 * @param int $revisionId
	 * @param string $segmentHash
	 * @param string|null $consumerUrl URL to the script path on the consumer,
	 *  if used as a producer.
	 * @param ListenMetricsEntry|null $listenMetricEntry Add page and segment
	 *  information to this entry.
	 * @return array An utterance
	 * @throws RuntimeException
	 */
	public function getUtteranceForRevisionAndSegment(
		string $voice,
		string $language,
		int $revisionId,
		string $segmentHash,
		?string $consumerUrl = null,
		?ListenMetricsEntry $listenMetricEntry = null
	): array {
		$segmentPageResponse = $this->segmentPageFactory
			->setSegmentBreakingTags( null )
			->setRemoveTags( null )
			->setUseSegmentsCache( true )
			->setUseRevisionPropertiesCache( true )
			->setContextSource( $this->context )
			->setConsumerUrl( $consumerUrl )
			->setRequirePageRevisionProperties( true )
			->segmentPage(
				null,
				$revisionId
			);
		$segment = $segmentPageResponse->getSegments()->findFirstItemByHash( $segmentHash );
		if ( $segment === null ) {
			throw new RuntimeException( 'No such segment. ' .
				'Did you perhaps reference a segment that was created using incompatible settings ' .
				'for segmentBreakingTags and/or removeTags?' );
		}
		$pageId = $segmentPageResponse->getPageId();
		if ( $pageId === null ) {
			throw new RuntimeException( 'Did not retrieve page id for the given revision id.' );
		}

		if ( $listenMetricEntry ) {
			$listenMetricEntry->setSegmentIndex(
				$segmentPageResponse->getSegments()->indexOf( $segment )
			);
			$listenMetricEntry->setPageId( $pageId );
			$listenMetricEntry->setPageTitle(
				$segmentPageResponse->getTitle()->getText()
			);
		}

		return $this->getUtterance(
			$consumerUrl,
			$voice,
			$language,
			$pageId,
			$segment
		);
	}

	/**
	 * Retrieves the matching utterance for a given message key.
	 *
	 * @since 0.1.14
	 *
	 * @param string $messageKey
	 * @param string $language
	 * @param string $voice
	 * @param string|null $consumerUrl
	 * @throws RuntimeException
	 * @return Utterance[] $utterancesByHash Map of segment hashes to Utterance objects
	 */
	public function getUtterancesForMessageKey(
		string $messageKey,
		string $language,
		string $voice,
		?string $consumerUrl = null
	): array {
		if ( !wfMessage( $messageKey )->exists() ) {
			throw new InvalidArgumentException( "Invalid message key: $messageKey" );
		}

		$segmentResponse = $this->segmentMessagesFactory->segmentMessage( $messageKey, $language );
		$segmentList = $segmentResponse->getSegments();
		$segments = $segmentList->getSegments();

		$utterancesByHash = [];

		foreach ( $segments as $segment ) {

			$segmentHash = $segment->getHash();
			if ( $segmentHash === null ) {
				throw new RuntimeException( 'Segment hash is null' );
			}

			$utterance = $this->utteranceStore->findMessageUtterance(
			$consumerUrl,
			$messageKey,
			$language,
			$voice,
			$segmentHash,
			false
			);

			if ( $utterance === null ) {
				throw new RuntimeException(
					"No utterance has been synthesized yet for the message key: " . $messageKey .
					". Please run the 'preSynthesizeMessages.php' maintenance script." );
			}

			$utterancesByHash[$segmentHash] = $utterance;
		}

		return $utterancesByHash;
	}

}
