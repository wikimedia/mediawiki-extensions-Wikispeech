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
abstract class AbstractPageProvider implements PageProvider {

	/** @var string|null */
	private $displayTitle = null;

	/** @var string|null */
	private $pageContent = null;

	/** @var int|null */
	private $revisionId = null;

	/**
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
	 * @return string|null
	 */
	public function getPageContent(): ?string {
		return $this->pageContent;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $pageContent
	 */
	public function setPageContent( ?string $pageContent ): void {
		$this->pageContent = $pageContent;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getDisplayTitle(): ?string {
		return $this->displayTitle;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $displayTitle
	 */
	public function setDisplayTitle( ?string $displayTitle ): void {
		$this->displayTitle = $displayTitle;
	}

}
