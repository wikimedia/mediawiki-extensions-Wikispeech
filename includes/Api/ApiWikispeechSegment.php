<?php

namespace MediaWiki\Wikispeech\Api;

use ApiBase;
use ApiMain;
use FormatJson;
use Title;
use WANObjectCache;
use Wikimedia\ParamValidator\ParamValidator;

use MediaWiki\Wikispeech\Segment\Segmenter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

class ApiWikispeechSegment extends ApiBase {

	/** @var WANObjectCache */
	private $cache;

	/**
	 * @since 0.1.7
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param WANObjectCache $cache
	 * @param string $modulePrefix
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		WANObjectCache $cache,
		string $modulePrefix = ''
	) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->cache = $cache;
	}

	/**
	 * Execute an API request.
	 *
	 * @since 0.0.1
	 */
	public function execute() {
		$parameters = $this->extractRequestParams();
		$title = Title::newFromText( $parameters['page'] );
		if ( !$title || $title->isExternal() ) {
			$this->dieWithError( [
				'apierror-invalidtitle',
				wfEscapeWikiText( $parameters['page'] )
			] );
		}
		if ( !$title->exists() ) {
			$this->dieWithError( 'apierror-missingtitle' );
		}
		$result = FormatJson::parse(
			$parameters['removetags'],
			FormatJson::FORCE_ASSOC
		);
		if ( !$result->isGood() ) {
			$this->dieWithError( 'apierror-wikispeech-segment-removetagsinvalidjson' );
		}
		$removeTags = $result->getValue();
		if ( !$this->isValidRemoveTags( $removeTags ) ) {
			$this->dieWithError( 'apierror-wikispeech-segment-removetagsinvalid' );
		}
		$segmenter = new Segmenter( $this->getContext(), $this->cache );
		$segments = $segmenter->segmentPage(
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable T240141
			$title,
			$removeTags,
			$parameters['segmentbreakingtags']
		);
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			[ 'segments' => $segments ]
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
	public function isValidRemoveTags( $removeTags ) {
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
		global $wgWikispeechRemoveTags;
		global $wgWikispeechSegmentBreakingTags;
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
						$wgWikispeechRemoveTags
					)
				],
				'segmentbreakingtags' => [
					ParamValidator::PARAM_TYPE => 'string',
					ParamValidator::PARAM_ISMULTI => true,
					ParamValidator::PARAM_DEFAULT => implode(
						'|',
						$wgWikispeechSegmentBreakingTags
					)
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
			=> 'apihelp-wikispeech-segment-example-2'
		];
	}
}
