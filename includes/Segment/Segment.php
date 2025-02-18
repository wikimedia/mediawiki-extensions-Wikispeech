<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.13 Change `$content` to array of `SegmentContent`
 * @since 0.1.10
 */
class Segment {

	/** @var SegmentContent[] */
	private $content;

	/**
	 * The offset of the first character of the segment, within the text node it appears.
	 * Used to determine start of a segment in the original HTML.
	 * @var int|null
	 */
	private $startOffset;

	/**
	 * The offset of the last character of the segment within the text node it appears.
	 * Used to determine end of a segment in the original HTML.
	 * @var int|null
	 */
	private $endOffset;

	/** @var string|null */
	private $hash;

	/**
	 * @since 0.1.13 Change `$content` to array of `SegmentContent`
	 * @since 0.1.10
	 * @param SegmentContent[] $content
	 * @param int|null $startOffset
	 * @param int|null $endOffset
	 * @param string|null $hash
	 */
	public function __construct(
		array $content = [],
		?int $startOffset = null,
		?int $endOffset = null,
		?string $hash = null
	) {
		$this->content = $content;
		$this->startOffset = $startOffset;
		$this->endOffset = $endOffset;
		$this->hash = $hash;
	}

	/**
	 * @since 0.1.13 Change return value to array of `SegmentContent`
	 * @since 0.1.10
	 * @return SegmentContent[]
	 */
	public function getContent(): array {
		return $this->content;
	}

	/**
	 * @since 0.1.13 Change `$content` to array of `SegmentContent`
	 * @since 0.1.10
	 * @param SegmentContent[] $content
	 */
	public function setContent( array $content ): void {
		$this->content = $content;
	}

	/**
	 * @since 0.1.13 Change `$content` to `SegmentContent`
	 * @since 0.1.10
	 * @param SegmentContent $content
	 */
	public function addContent( SegmentContent $content ): void {
		$this->content[] = $content;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getStartOffset(): ?int {
		return $this->startOffset;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $startOffset
	 */
	public function setStartOffset( ?int $startOffset ): void {
		$this->startOffset = $startOffset;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getEndOffset(): ?int {
		return $this->endOffset;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $endOffset
	 */
	public function setEndOffset( ?int $endOffset ): void {
		$this->endOffset = $endOffset;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getHash(): ?string {
		return $this->hash;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $hash
	 */
	public function setHash( ?string $hash ): void {
		$this->hash = $hash;
	}

}
