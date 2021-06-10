<?php

namespace MediaWiki\Wikispeech\Segment;

use RuntimeException;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Denotes a break between to segments. Added by `Cleaner` and
 * consumed by `Segmenter`.
 *
 * @since 0.0.1
 */
class SegmentBreak implements SegmentContent {

	/**
	 * This class will never be serialized and passed down to the client.
	 * It is not a DTO, it is an internal class filtered out by the Segmenter.
	 * @return array
	 * @throws RuntimeException Always.
	 */
	public function serialize(): array {
		throw new RuntimeException( 'This class is for internal processing only.' );
	}

}
