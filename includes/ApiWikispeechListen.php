<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

class ApiWikispeechListen extends ApiBase {

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
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
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

		// @todo Get pageId from input parameters.
		// The only effect we get from using 0 is that there might be more mismatches
		// on segment hashing. It should still work for now though.
		$pageId = 0;
		$language = $inputParameters['lang'];
		$voice = $inputParameters['voice'];
		// @todo Get segmentHash from input parameters.
		// @todo This is a hack to convert input text back to a segment
		// so that we get the correct segment hash value.
		// Will be dropped with the implementation of T248162.
		$segmenter = \Wikimedia\TestingAccessWrapper::newFromObject(
			new Segmenter( new RequestContext() )
		);
		$segments = $segmenter->segmentSentences( [
			new CleanedText( $inputParameters['input'] )
		] );
		$segmentHash = $segments[0]['hash'];
		$response = $this->getUtterance(
			$voice,
			$language,
			$pageId,
			$segmentHash,
			$inputParameters['input']
		);
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$response
		);
	}

	/**
	 * @param string $voice
	 * @param string $language
	 * @param int $pageId
	 * @param string $segmentHash
	 * @param string $segmentText
	 * @return array Containing base64 'audio' and synthesisMetadata 'tokens'.
	 * @throws ExternalStoreException
	 * @throws ConfigException
	 * @throws InvalidArgumentException
	 * @throws SpeechoidConnectorException
	 * @todo Would it make sense if $segmentHash and $segmentText
	 * was replaced by $segmentIndex passed on from the client, leaving
	 * the segmenting etc to this method? That way we wouldn't have to
	 * pass along a bunch of text that never would be used for the cases
	 * where the segment already exists in utterance store.
	 *
	 * @since 0.1.5
	 */
	private function getUtterance(
		$voice,
		$language,
		$pageId,
		$segmentHash,
		$segmentText
	) {
		if ( !$language ) {
			throw new InvalidArgumentException( 'Language must be set.' );
		}
		if ( $pageId !== 0 && !$pageId ) {
			throw new InvalidArgumentException( 'Page ID must be set.' );
		}
		if ( !$segmentHash ) {
			throw new InvalidArgumentException( 'Segment hash must be set.' );
		}
		if ( !$voice ) {
			$voice = $this->utteranceStore->getDefaultVoice( $language );
			if ( !$voice ) {
				throw new ConfigException( "Invalid default voice configuration." );
			}
		}

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
			$speechoidResponseJson = $this->speechoidConnector->synthesize(
				$language,
				$voice,
				$segmentText
			);
			$status = FormatJson::parse(
				$speechoidResponseJson,
				FormatJson::FORCE_ASSOC
			);
			if ( !$status->isOK() ) {
				throw new SpeechoidConnectorException( 'Unexpected response from Speechoid.' );
			}
			$speechoidResponse = $status->getValue();
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
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		$voices = $config->get( 'WikispeechVoices' );
		$language = $parameters['lang'];

		// Validate language.
		$validLanguages = array_keys( $voices );
		if ( !in_array( $language, $validLanguages ) ) {
			$this->dieWithError( [
				'apierror-wikispeechlisten-invalid-language',
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
					'apierror-wikispeechlisten-invalid-voice',
					$voice,
					self::makeValuesString( $validVoices )
				] );
			}
		}

		// Validate input text.
		$input = $parameters['input'];
		$numberOfCharactersInInput = mb_strlen( $input );
		$maximumNumberOfCharacterInInput = $config->get( 'WikispeechListenMaximumInputCharacters' );
		if ( $numberOfCharactersInInput > $maximumNumberOfCharacterInInput ) {
			$this->dieWithError( [
				'apierror-wikispeechlisten-invalid-input-too-long',
				$maximumNumberOfCharacterInInput,
				$numberOfCharactersInInput
			] );
		}
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
				'input' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
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
			'action=wikispeechlisten&format=json&lang=en&input=Read this'
			=> 'apihelp-wikispeechlisten-example-1',
			'action=wikispeechlisten&format=json&lang=en&input=Read this&voice=cmu-slt-flite'
			=> 'apihelp-wikispeechlisten-example-2'
		];
	}
}
