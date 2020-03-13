<?php

use MediaWiki\MediaWikiServices;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

class ApiWikispeech extends ApiBase {

	/**
	 * Execute an API request.
	 *
	 * @since 0.0.1
	 */
	public function execute() {
		$parameters = $this->extractRequestParams();
		if ( empty( $parameters['output'] ) ) {
			$this->dieWithError( [ 'apierror-paramempty', 'output' ] );
		}
		list( $displayTitle, $pageContent, $revisionId ) =
			$this->getTitleContentRevision( $parameters['page'] );
		$result = FormatJson::parse(
			$parameters['removetags'],
			FormatJson::FORCE_ASSOC
		);
		if ( !$result->isGood() ) {
			$this->dieWithError( [
				'apierror-wikispeech-removetagsinvalidjson',
				''
			] );
		}
		$removeTags = $result->getValue();
		if ( !$this->isValidRemoveTags( $removeTags ) ) {
			$this->dieWithError( [
				'apierror-wikispeech-removetagsinvalid',
				''
			] );
		}
		$this->processPageContent(
			$displayTitle,
			$pageContent,
			$revisionId,
			$parameters['output'],
			$removeTags,
			$parameters['segmentbreakingtags']
		);
	}

	/**
	 * Get the title and parsed content of the named page.
	 *
	 * @since 0.0.1
	 * @param string $pageTitle The title of the page to get content from.
	 * @return array An array containing the displayed title HTML and
	 *  the parsed content for the page given in the request to the
	 *  Wikispeech API.
	 * @throws ApiUsageException When dying with an API error.
	 * @throws MWException If failing to create WikiPage from title.
	 */
	private function getTitleContentRevision( $pageTitle ) {
		// Get and validate Title
		$title = Title::newFromText( $pageTitle );
		if ( !$title || $title->isExternal() ) {
			$this->dieWithError( [
				'apierror-invalidtitle',
				wfEscapeWikiText( $pageTitle )
			] );
		}
		if ( !$title->canExist() ) {
			$this->dieWithError( 'apierror-pagecannotexist' );
		}

		// Parse latest revision, using parser cache
		$page = WikiPage::factory( $title );
		$popts = $page->makeParserOptions( $this->getContext() );
		$pout = $page->getParserOutput( $popts );
		if ( !$pout ) {
			$this->dieWithError( [
				'apierror-nosuchrevid',
				$page->getLatest()
			] );
		}

		// Return title, content HTML and revision identity
		return [ $pout->getDisplayTitle(), $pout->getText(), $page->getLatest() ];
	}

	/**
	 * Tests if a variable is valid as "remove tags".
	 *
	 * The variable should be an associative array. Keys should be
	 * strings and values should be either booleans, strings or
	 * sequential arrays containing strings.
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
	 * Process HTML and return it as original, cleaned and/or segmented.
	 *
	 * @since 0.0.1
	 * @param string $displayTitle The title HTML as displayed on the page.
	 * @param string $pageContent The HTML string to process.
	 * @param string $revisionId The revision identity of the page.
	 * @param array $outputFormats Specifies what output formats to
	 *  return. Can be any combination of: "originalcontent",
	 *  "cleanedtext" and "segments".
	 * @param array $removeTags Used by `Cleaner` to remove tags.
	 * @param array $segmentBreakingTags Used by `Segmenter` to break
	 *  segments.
	 */
	public function processPageContent(
		$displayTitle,
		$pageContent,
		$revisionId,
		$outputFormats,
		$removeTags,
		$segmentBreakingTags
	) {
		$values = [];
		if ( in_array( 'originalcontent', $outputFormats ) ) {
			$values['originalcontent'] = $pageContent;
		}

		$cleanedText = null;
		if ( in_array( 'cleanedtext', $outputFormats ) ) {
			// Make a string of all the cleaned text, starting with
			// the title.
			$cleanedTextString = '';
			$cleanedText = $this->getCleanedText(
				$displayTitle,
				$pageContent,
				$revisionId,
				$removeTags,
				$segmentBreakingTags
			);
			foreach ( $cleanedText as $item ) {
				if ( $item instanceof SegmentBreak ) {
					$cleanedTextString .= "\n";
				} elseif ( $item->string != "\n" ) {
					// Don't add text that is only newline.
					$cleanedTextString .= $item->string;
				}
			}
			$values[ 'cleanedtext' ] = trim( $cleanedTextString );
		}

		if ( in_array( 'segments', $outputFormats ) ) {
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$cacheKey = $cache->makeKey( 'Wikispeech.processPageContent.segments', $revisionId );
			$segments = $cache->get( $cacheKey );
			if ( $segments == null ) {
				$segmenter = new Segmenter();
				if ( $cleanedText == null ) {
					$cleanedText = $this->getCleanedText(
						$displayTitle,
						$pageContent,
						$revisionId,
						$removeTags,
						$segmentBreakingTags
					);
				}
				$segments = $segmenter->segmentSentences( $cleanedText );
				$cache->set( $cacheKey, $segments, 3600 );
			}
			$values[ 'segments' ] = $segments;
		}

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$values
		);
	}

	/**
	 * Clean content text and title.
	 *
	 * @param string $displayTitle The title HTML as displayed on the page.
	 * @param string $pageContent The HTML string to process.
	 * @param int $revisionId Revision identity of the page, for cache purposes.
	 * @param array $removeTags Used by `Cleaner` to remove tags.
	 * @param array $segmentBreakingTags Used by `Segmenter` to break
	 *  segments.
	 * @since 0.0.1
	 * @return array Title and content represented as `CleanedText`s
	 *  and `SegmentBreak`s
	 */
	public function getCleanedText(
		$displayTitle,
		$pageContent,
		$revisionId,
		$removeTags,
		$segmentBreakingTags
	) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cacheKey = $cache->makeKey( 'Wikispeech.getCleanedText', $revisionId );
		$cleanedText = $cache->get( $cacheKey );
		if ( $cleanedText == null ) {
			$cleaner = new Cleaner( $removeTags, $segmentBreakingTags );
			$titleSegment = $cleaner->cleanHtml( $displayTitle )[ 0 ];
			$titleSegment->path = '//h1[@id="firstHeading"]//text()';
			$cleanedText = $cleaner->cleanHtml( $pageContent );
			// Add the title as a separate utterance to the start.
			array_unshift( $cleanedText, $titleSegment, new SegmentBreak() );
			$cache->set( $cacheKey, $cleanedText, 3600 );
		}
		return $cleanedText;
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
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
				],
				'output' => [
					ApiBase::PARAM_TYPE => [
						'originalcontent',
						'cleanedtext',
						'segments'
					],
					ApiBase::PARAM_REQUIRED => true,
					ApiBase::PARAM_ISMULTI => true,
					ApiBase::PARAM_HELP_MSG_PER_VALUE => []
				],
				'removetags' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_DFLT => json_encode(
						$wgWikispeechRemoveTags
					)
				],
				'segmentbreakingtags' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_ISMULTI => true,
					ApiBase::PARAM_DFLT => implode(
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
		// phpcs:ignore Generic.Files.LineLength
			'action=wikispeech&format=json&page=Main_Page&output=segments&removetags={"sup": true, "div": "toc"}&segmentbreakingtags=h1|h2'
			=> 'apihelp-wikispeech-example-1',
			'action=wikispeech&format=json&page=Main_Page&output=originalcontent|cleanedtext'
			=> 'apihelp-wikispeech-example-2',
		];
	}
}
