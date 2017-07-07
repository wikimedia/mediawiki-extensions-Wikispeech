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
		if ( $this->getMain()->getVal( 'output' ) == '' ) {
			$this->dieWithError( 'apierror-nooutput' );
		}
		$outputFormats = $this->parseMultiValue(
			'output',
			$this->getMain()->getVal( 'output' ),
			true,
			[ 'originalcontent', 'cleanedtext', 'segments' ]
		);
		$pageTitle = $this->getMain()->getVal( 'page' );
		$pageContent = $this->getPageContent( $pageTitle );
		$removeTags = json_decode(
			$this->getMain()->getVal( 'removetags' ),
			true
		);
		$segmentBreakingTags = $this->parseMultiValue(
			'segmentbreakingtags',
			$this->getMain()->getVal( 'segmentbreakingtags' ),
			true,
			null
		);
		$this->processPageContent(
			$pageContent,
			$outputFormats,
			$removeTags,
			$segmentBreakingTags
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
		$segmenter = new Segmenter();
		if ( in_array( 'segments', $outputFormats ) ) {
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
	 * Request the parsed content from the main API.
	 *
	 * @since 0.0.1
	 * @param string $pageTitle The title of the page to get content
	 *  from.
	 * @return string The parsed content for the page given in the
	 *  request to the Wikispeech API.
	 */

	private function getPageContent( $pageTitle ) {
		$request = new FauxRequest( [
			'action' => 'parse',
			'page' => $pageTitle
		] );
		$api = new ApiMain( $request );
		$api->execute();
		$pageContent = $api->getResult()->getResultData( [ 'parse', 'text' ] );
		return $pageContent;
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
					ApiBase::PARAM_TYPE => 'string'
				],
				'segmentbreakingtags' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_ISMULTI => true
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
