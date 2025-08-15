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
use ConfigFactory;
use FormatJson;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Revision\RevisionStore;
use Mediawiki\Title\Title;
use MediaWiki\Wikispeech\Segment\SegmentPageFactory;
use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module to segment a page.
 *
 * @since 0.0.1
 */
class ApiWikispeechSegment extends ApiBase {

	/** @var WANObjectCache */
	private $cache;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var ConfigFactory */
	private $configFactory;

	/**
	 * @since 0.1.7
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param WANObjectCache $cache
	 * @param HttpRequestFactory $requestFactory
	 * @param RevisionStore $revisionStore
	 * @param ConfigFactory $configFactory
	 * @param string $modulePrefix
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		WANObjectCache $cache,
		HttpRequestFactory $requestFactory,
		RevisionStore $revisionStore,
		ConfigFactory $configFactory,
		string $modulePrefix = ''
	) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->cache = $cache;
		$this->requestFactory = $requestFactory;
		$this->revisionStore = $revisionStore;
		$this->configFactory = $configFactory;
	}

	/**
	 * Execute an API request.
	 *
	 * @since 0.0.1
	 */
	public function execute() {
		$parameters = $this->extractRequestParams();
		if (
			isset( $parameters['consumer-url'] ) &&
			!$this->getConfig()->get( 'WikispeechProducerMode' ) ) {
			$this->dieWithError( 'apierror-wikispeech-consumer-not-allowed' );
		}
		$title = Title::newFromText( $parameters['page'] );
		if ( !$title || $title->isExternal() ) {
			$this->dieWithError( [
				'apierror-invalidtitle',
				wfEscapeWikiText( $parameters['page'] )
			] );
		}
		if ( !isset( $parameters['consumer-url'] ) && !$title->exists() ) {
			$this->dieWithError( 'apierror-missingtitle' );
		}
		$result = FormatJson::parse(
			$parameters['removetags'],
			FormatJson::FORCE_ASSOC
		);
		if ( !$result->isGood() ) {
			$this->dieWithError( 'apierror-wikispeech-segment-removetagsinvalidjson' );
		}
		$rawRemoveTags = $result->getValue();

		if ( !self::isValidRemoveTags( $rawRemoveTags ) ) {
			$this->dieWithError( 'apierror-wikispeech-segment-removetagsinvalid' );
		}
		$segmentPageFactory = new SegmentPageFactory(
			$this->cache,
			$this->configFactory
		);
		$segmentPageResponse = $segmentPageFactory
			->setSegmentBreakingTags( $parameters['segmentbreakingtags'] )
			->setRemoveTags( $rawRemoveTags )
			->setUseRevisionPropertiesCache( true )
			->setRequirePageRevisionProperties( false )
			->setUseSegmentsCache( true )
			->setContextSource( $this->getContext() )
			->setRevisionStore( $this->revisionStore )
			->setHttpRequestFactory( $this->requestFactory )
			->setConsumerUrl( $parameters['consumer-url'] )
			->segmentPage(
				$title,
				null
			);

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			[ 'segments' => $segmentPageResponse->getSegments()->toArray() ]
		);
	}

	/**
	 * Tests if a variable is valid as "remove tags".
	 *
	 * The variable should be an associative array. Keys should be
	 * strings and values should be booleans, strings or sequential
	 * arrays containing strings.
	 *
	 * @since 0.0.1
	 * @param mixed $removeTags The variable to test.
	 * @return bool true if $removeTags is valid, else false.
	 */
	public static function isValidRemoveTags( $removeTags ) {
		if ( !is_array( $removeTags ) ) {
			return false;
		}
		foreach ( $removeTags as $tagName => $rule ) {
			if ( !is_string( $tagName ) ) {
				// A key isn't a string.
				return false;
			}
			if ( is_array( $rule ) ) {
				// Rule is a list of class names.
				foreach ( $rule as $className ) {
					if ( !is_string( $className ) ) {
						// Only strings are valid if the rule is
						// an array.
						return false;
					}
				}
			} elseif ( !is_bool( $rule ) && !is_string( $rule ) ) {
				// Rule is not array, string or boolean.
				return false;
			}
		}
		return true;
	}

	/**
	 * Specify what parameters the API accepts.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			[
				'page' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_REQUIRED => true
				],
				'removetags' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_DEFAULT => json_encode(
						$this->getConfig()->get( 'WikispeechRemoveTags' )
					)
				],
				'segmentbreakingtags' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_ISMULTI => true,
					ParamValidator::PARAM_DEFAULT => implode(
						'|',
						$this->getConfig()->get( 'WikispeechSegmentBreakingTags' )
					)
				],
				'consumer-url' => [
					ParamValidator::PARAM_TYPE => 'string'
				]
			]
		);
	}

	/**
	 * Give examples of usage.
	 *
	 * @since 0.0.1
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=wikispeech-segment&format=json&page=Main_Page'
			=> 'apihelp-wikispeech-segment-example-1',
			// phpcs:ignore Generic.Files.LineLength
			'action=wikispeech-segment&format=json&page=Main_Page&removetags={"sup": true, "div": "toc"}&segmentbreakingtags=h1|h2'
			=> 'apihelp-wikispeech-segment-example-2',
			'action=wikispeech-segment&format=json&page=Main_Page&consumer-url=https://consumer.url/w'
			=> 'apihelp-wikispeech-segment-example-3',
		];
	}
}
