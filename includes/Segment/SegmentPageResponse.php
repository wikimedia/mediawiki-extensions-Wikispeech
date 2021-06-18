<?php

namespace MediaWiki\Wikispeech\Segment;

use Title;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * The response from {@link SegmentPageFactory::segmentPage()}
 *
 * @since 0.1.10
 */
class SegmentPageResponse {

	/** @var SegmentList|null */
	private $segments = null;

	/** @var Title|null */
	private $title = null;

	/** @var int|null */
	private $revisionId = null;

	/** @var int|null */
	private $pageId = null;

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

	/**
	 * @since 0.1.10
	 * @return Title|null
	 */
	public function getTitle(): ?Title {
		return $this->title;
	}

	/**
	 * @since 0.1.10
	 * @param Title|null $title
	 */
	public function setTitle( ?Title $title ): void {
		$this->title = $title;
	}

	/**
	 * Revision id fetched by page provider
	 *
	 * @see PageProvider::loadData()
	 * @see PageProvider::getRevisionId()
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getRevisionId(): ?int {
		return $this->revisionId;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $revisionId
	 */
	public function setRevisionId( ?int $revisionId ): void {
		$this->revisionId = $revisionId;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getPageId(): ?int {
		return $this->pageId;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $pageId
	 */
	public function setPageId( ?int $pageId ): void {
		$this->pageId = $pageId;
	}

}
