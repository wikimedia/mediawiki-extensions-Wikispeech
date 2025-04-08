<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use InvalidArgumentException;

/**
 * @since 0.1.13
 */
class SegmentMessagesFactory extends SegmentFactory {

	/**
	 * @since 0.1.13
	 * @param string $messageKey
	 * @param string $language
	 * @return SegmentList
	 * @throws InvalidArgumentException If $text or $language is not provided.
	 */
	public function segmentMessage( string $messageKey, string $language ): SegmentList {
		$text = wfMessage( $messageKey )->inLanguage( $language )->text();
		$segment = new Segment( [ new CleanedText( $text ) ] );
		$segment->setHash( md5( $text ) );

		return new SegmentList( [ $segment ] );
	}
}
