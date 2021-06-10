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
class Segment {

	/** @var CleanedText[] */
	private $content;

	/** @var int|null */
	private $startOffset;

	/** @var int|null */
	private $endOffset;

	/** @var string|null */
	private $hash;

	/**
	 * @since 0.1.10
	 * @param CleanedText[] $content
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
	 * @since 0.1.10
	 * @return CleanedText[]
	 */
	public function getContent(): array {
		return $this->content;
	}

	/**
	 * @since 0.1.10
	 * @param CleanedText[] $content
	 */
	public function setContent( array $content ): void {
		$this->content = $content;
	}

	/**
	 * @since 0.1.10
	 * @param CleanedText $content
	 */
	public function addContent( CleanedText $content ): void {
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

	/**
	 * @since 0.1.10
	 * @return array Object serialized to associative array
	 */
	public function serialize(): array {
		$serializedContent = [];
		foreach ( $this->getContent() as $content ) {
			$serializedContent[] = $content->serialize();
		}
		return [
			'content' => $serializedContent,
			'startOffset' => $this->getStartOffset(),
			'endOffset' => $this->getEndOffset(),
			'hash' => $this->getHash()
		];
	}

	/**
	 * @since 0.1.10
	 * @param Segment[] $segments
	 * @return array
	 */
	public static function serializeArray( array $segments ): array {
		$serializedSegments = [];
		foreach ( $segments as $segment ) {
			$serializedSegments[] = $segment->serialize();
		}
		return $serializedSegments;
	}

}
