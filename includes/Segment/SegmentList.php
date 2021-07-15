<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * A list of {@link Segment} returned by {@link Segmenter::segmentPage()}
 * combined with consumer helper functions.
 *
 * @since 0.1.10
 */
class SegmentList {

	/** @var Segment[] */
	private $segments;

	/**
	 * @since 0.1.10
	 * @param Segment[] $items
	 */
	public function __construct( array $items ) {
		$this->segments = $items;
	}

	/**
	 * @since 0.1.10
	 * @return Segment[]
	 */
	public function getSegments(): array {
		return $this->segments;
	}

	/**
	 * @since 0.1.10
	 * @param Segment[] $segments
	 */
	public function setSegments( array $segments ): void {
		$this->segments = $segments;
	}

	/**
	 * @since 0.1.10
	 * @param string $hash
	 * @return Segment|null
	 */
	public function findFirstItemByHash( string $hash ) {
		foreach ( $this->getSegments() as $segment ) {
			if ( $segment->getHash() === $hash ) {
				return $segment;
			}
		}
		return null;
	}

	/**
	 * @since 0.1.10
	 * @return array An array with segments as associative array
	 */
	public function toArray(): array {
		$serializedSegments = [];
		foreach ( $this->getSegments() as $segment ) {
			$serializedSegment = [
				'content' => [],
				'startOffset' => $segment->getStartOffset(),
				'endOffset' => $segment->getEndOffset(),
				'hash' => $segment->getHash()
			];
			foreach ( $segment->getContent() as $content ) {
				$serializedSegment['content'][] = [
					'string' => $content->getString(),
					'path' => $content->getPath()
				];
			}
			$serializedSegments[] = $serializedSegment;
		}
		return $serializedSegments;
	}

	/**
	 * @since 0.1.10
	 * @param Segment $segment
	 * @return int Index of $segment in this SegmentList. -1 if not existing.
	 */
	public function indexOf( Segment $segment ): int {
		$index = array_search( $segment, $this->getSegments(), true );
		return $index === false ? -1 : $index;
	}

}
