<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.13
 */

abstract class SegmentResponse {

	/** @var SegmentList|null */
	private $segments = null;

	/**
	 * @since 0.1.10
	 * @return SegmentList|null
	 */
	public function getSegments(): ?SegmentList {
		return $this->segments;
	}

	/**
	 * @since 0.1.10
	 * @param SegmentList|null $segments
	 */
	public function setSegments( ?SegmentList $segments ): void {
		$this->segments = $segments;
	}

}
