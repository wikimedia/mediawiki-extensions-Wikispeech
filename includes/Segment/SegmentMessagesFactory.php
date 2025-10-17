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
		$text = wfMessage( $messageKey )->inLanguage( $language )->text();

		$cleanedText = new CleanedText( $text );
		$segmenter = new StandardSegmenter();
		$segments = $segmenter->segmentSentences( [ $cleanedText ] );
		$response = new SegmentMessageResponse();
		$response->setMessageKey( $messageKey );
		$response->setLanguage( $language );
		$response->setSegments( new SegmentList( $segments ) );

		return $response;
	}
}
