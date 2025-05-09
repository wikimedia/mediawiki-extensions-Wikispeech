<?php

namespace MediaWiki\Wikispeech\Api;

/**
 * @file
 * @ingroup API
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ApiBase;
use ApiMain;
use ApiUsageException;
use Config;
use ConfigException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Wikispeech\InputTextValidator;
use MediaWiki\Wikispeech\Segment\DeletedRevisionException;
use MediaWiki\Wikispeech\Segment\RemoteWikiPageProviderException;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\UtteranceGenerator;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWiki\Wikispeech\VoiceHandler;
use MWException;
use MWTimestamp;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to synthezise text as sounds.
 *
 * Segments referenced by client are expected to have been created using
 * the default configuration settings for segmentBreakingTags and removeTags.
 * If not, segments might be incompatible, causing this API to not find
 * the requested corresponding utterances.
 *
 * @since 0.1.3
 */
class ApiWikispeechListen extends ApiBase {

	/** @var Config */
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

	/** @var UtteranceGenerator */
	private $utteranceGenerator;

	/** @var VoiceHandler */
	private $voiceHandler;

	/** @var ListenMetricsEntry */
	private $listenMetricEntry;

	/**
	 * @since 0.1.5
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param WANObjectCache $cache
	 * @param RevisionStore $revisionStore
	 * @param HttpRequestFactory $requestFactory
	 * @param UtteranceGenerator $utteranceGenerator
	 * @param string $modulePrefix
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		WANObjectCache $cache,
		RevisionStore $revisionStore,
		HttpRequestFactory $requestFactory,
		UtteranceGenerator $utteranceGenerator,
		string $modulePrefix = ''
	) {
		$this->config = $this->getConfig();
		$this->cache = $cache;
		$this->revisionStore = $revisionStore;
		$this->requestFactory = $requestFactory;
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		$this->speechoidConnector = new SpeechoidConnector(
			$this->config,
			$requestFactory
		);
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$this->voiceHandler = new VoiceHandler(
			$this->logger,
			$this->config,
			$this->speechoidConnector,
			$cache
		);
		$this->listenMetricEntry = new ListenMetricsEntry();
		$this->utteranceGenerator = $utteranceGenerator;

		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 * Execute an API request.
	 *
	 * @since 0.1.3
	 */
	public function execute() {
		$started = microtime( true );
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
		if ( isset( $inputParameters['revision'] ) ) {
			$response = $this->getUtteranceForRevisionAndSegment(
				$voice,
				$language,
				$inputParameters['revision'],
				$inputParameters['segment'],
				$inputParameters['consumer-url']
			);
		} else {
			try {
				$speechoidResponse = $this->speechoidConnector->synthesize(
					$language,
					$voice,
					$inputParameters
				);
			} catch ( Throwable $exception ) {
				$this->dieWithException( $exception );
			}
			$response = [
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Phan doesn't understand dieWithException()
				'audio' => $speechoidResponse['audio_data'],
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Phan doesn't understand dieWithException()
				'tokens' => $speechoidResponse['tokens']
			];
		}
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$response
		);

		$charactersInSegment = 0;
		foreach ( $response['tokens'] as $token ) {
			$charactersInSegment += mb_strlen( $token['orth'] );
			// whitespace and sentence ends counts too
			$charactersInSegment += 1;
		}
		$this->listenMetricEntry->setCharactersInSegment( $charactersInSegment );
		$this->listenMetricEntry->setLanguage( $inputParameters['lang'] );
		$this->listenMetricEntry->setVoice( $voice );
		$this->listenMetricEntry->setPageRevisionId( $inputParameters['revision'] );
		$this->listenMetricEntry->setSegmentHash( $inputParameters['segment'] );
		$this->listenMetricEntry->setConsumerUrl( $inputParameters['consumer-url'] );
		$this->listenMetricEntry->setRemoteWikiHash(
			UtteranceStore::evaluateRemoteWikiHash( $inputParameters['consumer-url'] )
		);
		$this->listenMetricEntry->setMillisecondsSpeechInUtterance(
			$response['tokens'][count( $response['tokens'] ) - 1]['endtime']
		);
		$this->listenMetricEntry->setMicrosecondsSpent( intval( 1000000 * ( microtime( true ) - $started ) ) );

		// All other metrics fields has been set in other functions of this class.
		// For now the value of utteranceSynthesized() isn't used.
		if ( !$inputParameters['skip-journal-metrics']
			&& $this->config->get( 'WikispeechListenDoJournalMetrics' ) ) {
			$metricsJournal = new ListenMetricsEntryFileJournal( $this->config );
			try {
				$metricsJournal->appendEntry( $this->listenMetricEntry );
			} catch ( Throwable $exception ) {
				// Catch everything. This should not bother the user!
				$this->logger->warning(
					'Exception caught while appending to metrics journal {exception}',
					[ 'exception' => $exception ]
				);
			}
		}
	}

	/**
	 * Retrieves the matching utterance for a given revision id and segment hash .
	 *
	 * @since 0.1.5
	 * @param string $voice
	 * @param string $language
	 * @param int $revisionId
	 * @param string $segmentHash
	 * @param string|null $consumerUrl URL to the script path on the consumer, if used as a producer.
	 * @return array An utterance
	 */
	private function getUtteranceForRevisionAndSegment(
		string $voice,
		string $language,
		int $revisionId,
		string $segmentHash,
		?string $consumerUrl = null
	): array {
		$segmentPageFactory = new SegmentPageFactory(
			$this->cache,
			// todo inject config factory
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
				->setHttpRequestFactory( $this->requestFactory )
				->setConsumerUrl( $consumerUrl )
				->setRequirePageRevisionProperties( true )
				->segmentPage(
					null,
					$revisionId
				);
		} catch ( RemoteWikiPageProviderException $remoteWikiPageProviderException ) {
			$this->dieWithError( [
				'apierror-wikispeech-listen-failed-getting-page-from-consumer',
				$revisionId,
				$consumerUrl
			] );
		} catch ( DeletedRevisionException $deletedRevisionException ) {
			$this->dieWithError( 'apierror-wikispeech-listen-deleted-revision' );
		}
		$segment = $segmentPageResponse->getSegments()->findFirstItemByHash( $segmentHash );
		if ( $segment === null ) {
			throw new MWException( 'No such segment. ' .
				'Did you perhaps reference a segment that was created using incompatible settings ' .
				'for segmentBreakingTags and/or removeTags?' );
		}
		$pageId = $segmentPageResponse->getPageId();
		if ( $pageId === null ) {
			throw new MWException( 'Did not retrieve page id for the given revision id.' );
		}

		$this->listenMetricEntry->setSegmentIndex( $segmentPageResponse->getSegments()->indexOf( $segment ) );
		$this->listenMetricEntry->setPageId( $pageId );
		$this->listenMetricEntry->setPageTitle( $segmentPageResponse->getTitle()->getText() );

		return $this->utteranceGenerator->getUtterance(
			$consumerUrl,
			$voice,
			$language,
			$pageId,
			$segment
		);
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
			isset( $parameters['consumer-url'] ) &&
			!$this->config->get( 'WikispeechProducerMode' ) ) {
			$this->dieWithError( 'apierror-wikispeech-consumer-not-allowed' );
		}
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
		$this->requireOnlyOneParameter(
			$parameters,
			'revision',
			'text',
			'ipa'
		);
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
		$input = $parameters['text'] ?? '';
		try {
			InputTextValidator::validateText( $input );
		} catch ( RuntimeException $e ) {
			$this->dieWithError(
				[ 'apierror-wikispeech-listen-invalid-input-too-long',
				$this->config->get( 'WikispeechListenMaximumInputCharacters' ), mb_strlen( $input ) ]
			);
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
			$valueStrings[] = "<kbd>$value</kbd>";
		}
		return implode( ', ', $valueStrings );
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
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true
				],
				'text' => [
					ParamValidator::PARAM_TYPE => 'string'
				],
				'ipa' => [
					ParamValidator::PARAM_TYPE => 'string'
				],
				'revision' => [
					ParamValidator::PARAM_TYPE => 'integer'
				],
				'segment' => [
					ParamValidator::PARAM_TYPE => 'string'
				],
				'voice' => [
					ParamValidator::PARAM_TYPE => 'string'
				],
				'consumer-url' => [
					ParamValidator::PARAM_TYPE => 'string'
				],
				'skip-journal-metrics' => [
					ParamValidator::PARAM_TYPE => 'boolean',
					ParamValidator::PARAM_DEFAULT => false
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
			'action=wikispeech-listen&format=json&lang=en&text=Read this&voice=cmu-slt-hsmm'
			=> 'apihelp-wikispeech-listen-example-2',
			'action=wikispeech-listen&format=json&lang=en&revision=1&segment=hash1234'
			=> 'apihelp-wikispeech-listen-example-3',
			// phpcs:ignore Generic.Files.LineLength
			'action=wikispeech-listen&format=json&lang=en&revision=1&segment=hash1234&consumer-url=https://consumer.url/w'
			=> 'apihelp-wikispeech-listen-example-4',
		];
	}
}
