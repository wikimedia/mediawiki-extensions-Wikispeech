<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.14
 */
class SegmentMessagesFactory extends SegmentFactory {

	/**
	 * @since 0.1.13
	 * @param string $messageKey
	 * @param string $language
	 * @return SegmentMessageResponse
	 */
	public function segmentMessage( string $messageKey, string $language ): SegmentMessageResponse {
		$message = wfMessage( $messageKey )->inLanguage( $language );

		$html = $message->parse();

		$cleanedContent = $this->cleanHtmlDom(
			'',
			$html,
			$this->removeTags ?? $this->config->get( 'WikispeechRemoveTags' ),
			$this->segmentBreakingTags ?? $this->config->get( 'WikispeechSegmentBreakingTags' )
		);

		$segmenter = new StandardSegmenter();
		$segments = $segmenter->segmentSentences( $cleanedContent );

		$response = new SegmentMessageResponse();
		$response->setMessageKey( $messageKey );
		$response->setLanguage( $language );
		$response->setSegments( new SegmentList( $segments ) );

		return $response;
	}

}
