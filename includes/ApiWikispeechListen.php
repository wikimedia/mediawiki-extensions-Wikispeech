<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Psr\Log\LoggerInterface;

class ApiWikispeechListen extends ApiBase {

	/** @var Config */
	private $config;

	/** @var WANObjectCache */
	private $cache;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var LoggerInterface */
	private $logger;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var UtteranceStore */
	private $utteranceStore;

	/**
	 * ApiWikispeechListen constructor.
	 *
	 * @since 0.1.5
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param WANObjectCache $cache
	 * @param RevisionStore $revisionStore
	 * @param string $modulePrefix
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		WANObjectCache $cache,
		RevisionStore $revisionStore,
		string $modulePrefix = ''
	) {
		$this->config = $this->getConfig();
		$this->cache = $cache;
		$this->revisionStore = $revisionStore;
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->speechoidConnector = new SpeechoidConnector();
		$this->utteranceStore = new UtteranceStore();
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 * Execute an API request.
	 *
	 * @since 0.1.3
	 */
	public function execute() {
		$inputParameters = $this->extractRequestParams();
		self::validateParameters( $inputParameters );

		$language = $inputParameters['lang'];
		$voice = $inputParameters['voice'];
		if ( isset( $inputParameters['revision'] ) ) {
			$response = $this->getResponseForRevisionAndSegment(
				$voice,
				$language,
				$inputParameters['revision'],
				$inputParameters['segment']
			);
		} else {
			if ( !$voice ) {
				$voice = $this->utteranceStore->getDefaultVoice( $language );
				if ( !$voice ) {
					throw new ConfigException( "Invalid default voice configuration." );
				}
			}
			$speechoidResponse = $this->speechoidConnector->synthesize(
				$language,
				$voice,
				$inputParameters['text']
			);
			$response = [
				'audio' => $speechoidResponse['audio_data'],
				'tokens' => $speechoidResponse['tokens']
			];
		}
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$response
		);
	}

	/**
	 * Given a revision ID and a segment hash retrieve the matching utterance.
	 *
	 * @since 0.1.5
	 * @param string $voice
	 * @param string $language
	 * @param int $revisionId
	 * @param string $segmentHash
	 * @return array
	 */
	private function getResponseForRevisionAndSegment(
		$voice,
		$language,
		$revisionId,
		$segmentHash
	) {
		$revisionRecord = $this->getRevisionRecord( $revisionId );
		$pageId = $revisionRecord->getPageId();
		$title = Title::newFromLinkTarget(
			$revisionRecord->getPageAsLinkTarget()
		);
		$segmenter = new Segmenter( $this->getContext() );
		$segment = $segmenter->getSegment( $title, $segmentHash, $revisionId );

		$response = $this->getUtterance(
			$voice,
			$language,
			$pageId,
			$segment
		);
		return $response;
	}

	/**
	 * Validate input text.
	 *
	 * @since 0.1.5
	 * @param string $text
	 * @throws ApiUsageException
	 */
	private function validateText( $text ) {
		$numberOfCharactersInInput = mb_strlen( $text );
		$maximumNumberOfCharacterInInput =
			$this->config->get( 'WikispeechListenMaximumInputCharacters' );
		if ( $numberOfCharactersInInput > $maximumNumberOfCharacterInInput ) {
			$this->dieWithError( [
				'apierror-wikispeech-listen-invalid-input-too-long',
				$maximumNumberOfCharacterInInput,
				$numberOfCharactersInInput
			] );
		}
	}

	/**
	 * Return the utterance corresponding to the request.
	 *
	 * These are either retrieved from storage or synthesize (and then stored).
	 *
	 * @since 0.1.5
	 * @param string $voice
	 * @param string $language
	 * @param int $pageId
	 * @param array $segment A segments made up of `CleanedTest`bjects
	 * @return array Containing base64 'audio' and synthesisMetadata 'tokens'.
	 * @throws ExternalStoreException
	 * @throws ConfigException
	 * @throws InvalidArgumentException
	 * @throws SpeechoidConnectorException
	 */
	private function getUtterance(
		$voice,
		$language,
		$pageId,
		$segment
	) {
		if ( $pageId !== 0 && !$pageId ) {
			throw new InvalidArgumentException( 'Page ID must be set.' );
		}
		if ( !$segment ) {
			throw new InvalidArgumentException( 'Segment must be set.' );
		}
		if ( !$voice ) {
			$voice = $this->utteranceStore->getDefaultVoice( $language );
			if ( !$voice ) {
				throw new ConfigException( "Invalid default voice configuration." );
			}
		}

		$segmentHash = $segment['hash'];

		$utterance = $this->utteranceStore->findUtterance(
			$pageId,
			$language,
			$voice,
			$segmentHash
		);
		if ( !$utterance ) {
			$this->logger->debug( __METHOD__ . ': Creating new utterance for {pageId} {segmentHash}', [
				'pageId' => $pageId,
				'segmentHash' => $segmentHash
			] );

			// Make a string of all the segment contents.
			$segmentText = '';
			foreach ( $segment['content'] as $content ) {
				$segmentText .= $content->string;
			}
			$this->validateText( $segmentText );

			$speechoidResponse = $this->speechoidConnector->synthesize(
				$language,
				$voice,
				$segmentText
			);
			$this->utteranceStore->createUtterance(
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
			'audio' => $utterance['audio'],
			'tokens' => FormatJson::parse(
				$utterance['synthesisMetadata'],
				FormatJson::FORCE_ASSOC
			)->getValue()
		];
	}

	/**
	 * Validate the parameters for language and voice.
	 *
	 * The parameter values are checked against the extension
	 * configuration. These may differ from what is actually running
	 * on the Speechoid service.
	 *
	 * @since 0.1.3
	 * @param array $parameters Request parameters.
	 * @throws ApiUsageException
	 */
	private function validateParameters( $parameters ) {
		if (
			isset( $parameters['revision'] ) &&
			!isset( $parameters['segment'] )
		) {
			$this->dieWithError( [
				'apierror-invalidparammix-mustusewith',
				'revision',
				'segment'
			] );
		}
		if (
			isset( $parameters['segment'] ) &&
			!isset( $parameters['revision'] )
		) {
			$this->dieWithError( [
				'apierror-invalidparammix-mustusewith',
				'segment',
				'revision'
			] );
		}
		if (
			isset( $parameters['revision'] ) &&
			isset( $parameters['text'] )
		) {
			$this->dieWithError( [
				'apierror-invalidparammix-cannotusewith',
				'text',
				'revision'
			] );
		}
		$voices = $this->config->get( 'WikispeechVoices' );
		$language = $parameters['lang'];

		// Validate language.
		$validLanguages = array_keys( $voices );
		if ( !in_array( $language, $validLanguages ) ) {
			$this->dieWithError( [
				'apierror-wikispeech-listen-invalid-language',
				$language,
				self::makeValuesString( $validLanguages )
			] );
		}

		// Validate voice.
		$voice = $parameters['voice'];
		if ( $voice ) {
			$validVoices = $voices[$language];
			if ( !in_array( $voice, $validVoices ) ) {
				$this->dieWithError( [
					'apierror-wikispeech-listen-invalid-voice',
					$voice,
					self::makeValuesString( $validVoices )
				] );
			}
		}

		// Validate input text.
		$input = $parameters['text'];
		$this->validateText( $input );
	}

	/**
	 * Make a formatted string of values to be used in messages.
	 *
	 * @since 0.1.3
	 * @param array $values Values as strings.
	 * @return string The input strings wrapped in <kbd> tags and
	 *  joined by commas.
	 */
	private static function makeValuesString( $values ) {
		$valueStrings = [];
		foreach ( $values as $value ) {
			array_push(
				$valueStrings,
				"<kbd>$value</kbd>"
			);
		}
		$valuesString = implode( ', ', $valueStrings );
		return $valuesString;
	}

	/**
	 * Get the page id for a revision id.
	 *
	 * @since 0.1.5
	 * @param int $revisionId
	 * @return RevisionRecord
	 * @throws ApiUsageException if the revision is deleted or supressed.
	 */
	private function getRevisionRecord( $revisionId ) {
		$revisionRecord = $this->revisionStore->getRevisionById( $revisionId );
		if ( !$revisionRecord || !$revisionRecord->audienceCan(
			RevisionRecord::DELETED_TEXT,
			RevisionRecord::FOR_THIS_USER,
			$this->getContext()->getUser()
		) ) {
			$this->dieWithError( 'apierror-wikispeech-listen-deleted-revision' );
		}
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable T240141
		return $revisionRecord;
	}

	/**
	 * Specify what parameters the API accepts.
	 *
	 * @since 0.1.3
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			[
				'lang' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
				],
				'text' => [
					ApiBase::PARAM_TYPE => 'string'
				],
				'revision' => [
					ApiBase::PARAM_TYPE => 'integer'
				],
				'segment' => [
					ApiBase::PARAM_TYPE => 'string'
				],
				'voice' => [
					ApiBase::PARAM_TYPE => 'string'
				]

			]
		);
	}

	/**
	 * Give examples of usage.
	 *
	 * @since 0.1.3
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=wikispeech-listen&format=json&lang=en&text=Read this'
			=> 'apihelp-wikispeech-listen-example-1',
			'action=wikispeech-listen&format=json&lang=en&text=Read this&voice=cmu-slt-flite'
			=> 'apihelp-wikispeech-listen-example-2',
			'action=wikispeech-listen&format=json&lang=en&revision=1&segment=hash1234'
			=> 'apihelp-wikispeech-listen-example-3',
		];
	}
}
