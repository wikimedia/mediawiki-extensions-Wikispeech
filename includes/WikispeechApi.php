<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class WikispeechApi extends ApiBase {

	/**
	 * Execute an API request.
	 *
	 * @since 0.0.1
	 */

	function execute() {
		$parameters = $this->extractRequestParams();
		if ( empty( $parameters['output'] ) ) {
			$this->dieWithError( [ 'apierror-paramempty', 'output' ] );
		}
		$pageContent = $this->getPageContent( $parameters['page'] );
		$this->processPageContent(
			$pageContent,
			$parameters['output'],
			json_decode( $parameters['removetags'], true ),
			$parameters['segmentbreakingtags']
		);
	}

	/**
	 * Process HTML and return it as original, cleaned and/or segmented.
	 *
	 * @since 0.0.1
	 * @param string $html The HTML string to process.
	 * @param array $outputFormats Specifies what output formats to
	 *  return. Can be any combination of: "originalcontent",
	 *  "cleanedtext" and "segments".
	 * @param string $removeTags Used by `Cleaner` to remove tags.
	 * @param array $segmentBreakingTags Used by `Segmenter` to break
	 *  segments.
	 * @return array An array containing the output from the processes
	 *  specified by $outputFormats:
	 *  * "originalcontent": The input HTML string.
	 *  * "cleanedtext": The cleaned HTML, as a string.
	 *  * "segments": Cleaned and segmented HTML as an array.
	 */

	public function processPageContent(
		$pageContent,
		$outputFormats,
		$removeTags,
		$segmentBreakingTags
	) {
		$values = [];
		if ( in_array( 'originalcontent', $outputFormats ) ) {
			$values['originalcontent'] = $pageContent;
		}

		$cleaner = new Cleaner( $removeTags, $segmentBreakingTags );
		$cleanedText = null;
		if ( in_array( 'cleanedtext', $outputFormats ) ) {
			$cleanedText = $cleaner->cleanHtml( $pageContent );
			// Make a string of all the cleaned text.
			$cleanedTextString = '';
			foreach ( $cleanedText as $item ) {
				if ( $item instanceof SegmentBreak ) {
					$cleanedTextString .= "\n";
				} elseif ( $item->string != "\n" ) {
					// Don't add text that is only newline.
					$cleanedTextString .= $item->string;
				}
			}
			$values['cleanedtext'] = trim( $cleanedTextString );
		}

		if ( in_array( 'segments', $outputFormats ) ) {
			$segmenter = new Segmenter();
			if ( $cleanedText == null ) {
				$cleanedText = $cleaner->cleanHtml( $pageContent );
			}
			$segments = $segmenter->segmentSentences( $cleanedText );
			$values['segments'] = $segments;
		}

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$values
		);
	}

	/**
	 * Get the parsed content of the named page.
	 *
	 * @since 0.0.1
	 * @param string $pageTitle The title of the page to get content
	 *  from.
	 * @return string The parsed content for the page given in the
	 *  request to the Wikispeech API.
	 */

	private function getPageContent( $pageTitle ) {
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
			$this->dieWithError( [ 'apierror-nosuchrevid', $page->getLatest() ] );
		}

		// Return HTML
		return $pout->getText();
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
					ApiBase::PARAM_DFLT => json_encode( $wgWikispeechRemoveTags )
				],
				'segmentbreakingtags' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_ISMULTI => true,
					ApiBase::PARAM_DFLT => implode( $wgWikispeechSegmentBreakingTags, '|' )
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
		// @codingStandardsIgnoreStart
			'action=wikispeech&format=json&page=Main_Page&output=segments&removetags={"sup": true, "div": "toc"}&segmentbreakingtags=h1|h2'
		// @codingStandardsIgnoreEnd
			=> 'apihelp-wikispeech-example-1',
			'action=wikispeech&format=json&page=Main_Page&output=originalcontent|cleanedtext'
			=> 'apihelp-wikispeech-example-2',
		];
	}
}
