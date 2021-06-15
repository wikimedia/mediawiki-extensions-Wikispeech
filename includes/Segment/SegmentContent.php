<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.10
 */
interface SegmentContent {

	/**
	 * @since 0.1.10
	 * @return array Object serialized to associative array
	 */
	public function serialize(): array;

}
