<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Title;

/**
 * Information required to load data in a {@link PageProvider} given just a revision id.
 *
 * @since 0.1.10
 */
class PageRevisionProperties {

	/** @var Title */
	private $title;

	/** @var int */
	private $pageId;

	/**
	 * @since 0.1.10
	 * @param Title $title
	 * @param int $pageId
	 */
	public function __construct(
		Title $title,
		int $pageId
	) {
		$this->title = $title;
		$this->pageId = $pageId;
	}

	/**
	 * @since 0.1.10
	 * @return Title
	 */
	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * @since 0.1.10
	 * @param Title $title
	 */
	public function setTitle( Title $title ): void {
		$this->title = $title;
	}

	/**
	 * @since 0.1.10
	 * @return int
	 */
	public function getPageId(): int {
		return $this->pageId;
	}

	/**
	 * @since 0.1.10
	 * @param int $pageId
	 */
	public function setPageId( int $pageId ): void {
		$this->pageId = $pageId;
	}

}
