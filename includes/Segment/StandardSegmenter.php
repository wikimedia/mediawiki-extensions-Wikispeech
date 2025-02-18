<?php

namespace MediaWiki\Wikispeech\Segment;

use RuntimeException;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Generic segmenter built for use with swedish, english and arabic language pages.
 *
 * @since 0.1.10
 */
class StandardSegmenter extends Segmenter {

	/**
	 * An array to which finished segments are added.
	 *
	 * @var Segment[]|null
	 */
	private $segments = null;

	/**
	 * The segment that is currently being built.
	 *
	 * @var Segment|null
	 */
	private $currentSegment = null;

	/**
	 * Divide a cleaned content array into segments, one for each sentence.
	 *
	 * A sentence is here defined as a sequence of tokens ending with a dot (full stop).
	 *
	 * @since 0.1.10
	 * @param SegmentContent[] $cleanedContent An array of items returned by `Cleaner::cleanHtml()`.
	 * @return Segment[] An array of segments, each containing the `CleanedText's in that segment.
	 */
	public function segmentSentences( array $cleanedContent ): array {
		$this->segments = [];
		$this->currentSegment = new Segment();
		foreach ( $cleanedContent as $item ) {
			if ( $item instanceof SegmentBreak ) {
				$this->finishSegment();
			} elseif ( $item instanceof SegmentContent ) {
				$this->addContentsToCurrentSegment( $item );
			} else {
				throw new RuntimeException( 'Unsupported instance of SegmentContent' );
			}
		}
		if ( $this->currentSegment->getContent() ) {
			// Add the last segment, unless it's empty.
			$this->finishSegment();
		}
		return $this->segments;
	}

	/**
	 * Add segment contents for a string.
	 *
	 * Looks for sentence final strings (strings which a sentence ends
	 * with). When a sentence final string is found, it's sentence is
	 * added to the $currentSegment.
	 *
	 * @since 0.1.13 `$content` replaces `$text`
	 * @since 0.1.10
	 * @param SegmentContent $content Has the text to segment.
	 */
	private function addContentsToCurrentSegment( SegmentContent $content ) {
		$nextStartOffset = 0;
		do {
			$endOffset = $this->addContentToCurrentSegment( $content, $nextStartOffset );
			// The earliest the next segments can start is one after
			// the end of the current one.
			$nextStartOffset = $endOffset + 1;
		} while ( $nextStartOffset < mb_strlen( $content->getString() ) - 1 );
	}

	/**
	 * Add a sentence, or part thereof, to a segment.
	 *
	 * Finds the next sentence by sentence final characters and adds
	 * them to the segment under construction. If no sentence final
	 * character was found, all the remaining text is added. Stores
	 * start offset when the first text of a segment is added and end
	 * offset when the last is.
	 *
	 * @since 0.1.13 `$content` replaces `$text`
	 * @since 0.1.10
	 * @param SegmentContent $content Has the text to segment.
	 * @param int $startOffset The offset where the next sentence can
	 *  start, at the earliest. If the sentence has leading
	 *  whitespaces, this will be moved forward.
	 * @return int The offset of the last character in the
	 *   sentence. If the sentence didn't end yet, this is the last
	 *   character of $content.
	 */
	private function addContentToCurrentSegment(
		SegmentContent $content,
		int $startOffset = 0
	): int {
		if ( $this->currentSegment->getStartOffset() === null && $content instanceof CleanedText ) {
			// Move the start offset ahead by the number of leading
			// whitespaces. This means that whitespaces before or
			// between segments aren't included.
			$leadingWhitespacesLength = $this->getLeadingWhitespacesLength(
				mb_substr( $content->getString(), $startOffset )
			);
			$startOffset += $leadingWhitespacesLength;
		}
		// Get the offset for the next sentence final character.
		$endOffset = $this->getSentenceFinalOffset(
			$content->getString(),
			$startOffset
		);
		// If no sentence final character is found, add the rest of
		// the text and remember that this segment isn't ended.
		$ended = true;
		if ( $endOffset === null ) {
			$endOffset = mb_strlen( $content->getString() ) - 1;
			$ended = false;
		}
		$sentence = mb_substr(
			$content->getString(),
			$startOffset,
			$endOffset - $startOffset + 1
		);
		if ( $sentence !== '' && $sentence !== "\n" ) {
			// Don't add `CleanedText`s with the empty string or only
			// newline.
			$sentenceContent = clone $content;
			if ( $sentenceContent instanceof CleanedText ) {
				$sentenceContent->setString( $sentence );
			}
			$this->currentSegment->addContent( $sentenceContent );
			if ( $this->currentSegment->getStartOffset() === null ) {
				// Record the start offset if this is the first text
				// added to the segment.
				$this->currentSegment->setStartOffset( $startOffset );
			}
			$this->currentSegment->setEndOffset( $endOffset );
			if ( $ended ) {
				$this->finishSegment();
			}
		}
		return $endOffset;
	}

	/**
	 * Get the number of whitespaces at the start of a string.
	 *
	 * @since 0.1.10
	 * @param string $string The string to count leading whitespaces
	 *  for.
	 * @return int The number of whitespaces at the start of $string.
	 */
	private function getLeadingWhitespacesLength( string $string ): int {
		$trimmedString = preg_replace( '/^\s+/u', '', $string );
		return mb_strlen( $string ) - mb_strlen( $trimmedString );
	}

	/**
	 * Get the offset of the first sentence final character in a string.
	 *
	 * @since 0.1.10
	 * @param string $string The string to look in.
	 * @param int $offset The offset to start looking from.
	 * @return int|null The offset of the first sentence final character
	 *  that was found, if any, else null.
	 */
	private function getSentenceFinalOffset(
		string $string,
		int $offset
	): ?int {
		// For every potentially sentence final character after the
		// first one, we want to start looking from the character
		// after the last one we found. For the first one however, we
		// want to start looking from the character at the offset, to
		// not miss if that is a sentence final character. To only
		// have one loop for both these cases, we need to go back one
		// for the first search.
		$offset--;
		do {
			// Find the next character that may be sentence final.
			$offset = mb_strpos( $string, '.', $offset + 1 );
			if ( $offset === false ) {
				// No character that can be sentence final was found.
				return null;
			}
		} while ( !$this->isSentenceFinal( $string, $offset ) );
		return $offset;
	}

	/**
	 * Test if a character is at the end of a sentence.
	 *
	 * Dots in abbreviations should only be counted when they also are sentence final.
	 * For example:
	 * "Monkeys, penguins etc.", but not "Monkeys e.g. baboons".
	 *
	 * @since 0.1.10
	 * @param string $string The string to check in.
	 * @param int $index The index in $string of the character to check.
	 * @return bool True if the character is sentence final, else false.
	 */
	protected function isSentenceFinal(
		string $string,
		int $index
	): bool {
		$character = mb_substr( $string, $index, 1 );
		$nextCharacter = null;
		if ( mb_strlen( $string ) > $index + 1 ) {
			$nextCharacter = mb_substr( $string, $index + 1, 1 );
		}
		$characterAfterNext = null;
		if ( mb_strlen( $string ) > $index + 2 ) {
			$characterAfterNext = mb_substr( $string, $index + 2, 1 );
		}

		// A dot is sentence final if it's at the end of string or line
		// or followed by a space and a capital letter.

		return self::isSentenceEndingPunctuation( $character ) && (
			!$nextCharacter ||
			$nextCharacter == "\n" || (
				$nextCharacter == ' ' && (
					!$characterAfterNext || (
						self::isLetter( $characterAfterNext ) &&
						self::isUpper( $characterAfterNext ) ) ) ) );
	}

	/**
	 * @since 0.1.10
	 * @param string $string
	 * @return bool If param $string is a sentence ending punctuation.
	 */
	private static function isSentenceEndingPunctuation( string $string ): bool {
		return $string === '.' ||
			$string === '?' ||
			$string === '!';
	}

	/**
	 * Test if a string is upper case.
	 *
	 * @since 0.1.10
	 * @param string $string The string to test.
	 * @return bool true if the entire string is upper case, else false.
	 */
	private static function isUpper( string $string ): bool {
		return mb_strtoupper( $string ) === $string;
	}

	/**
	 * Test if a string is an alphabetical letter of any language
	 *
	 * @since 0.1.10
	 * @param string $string The string to test.
	 * @return bool true if the entire string is an alphabetical letter, else false.
	 */
	private static function isLetter( string $string ): bool {
		return preg_match( '/^\p{L}$/u', $string );
	}

	/**
	 * Add the current segment to the array of segments.
	 *
	 * Creates a new, empty segment as the new current segment.
	 *
	 * @since 0.1.10
	 */
	private function finishSegment() {
		if ( count( $this->currentSegment->getContent() ) ) {
			$this->currentSegment->setHash( $this->evaluateHash( $this->currentSegment ) );
			$this->segments[] = $this->currentSegment;
		}
		// Create a fresh segment to add following text to.
		$this->currentSegment = new Segment();
	}
}
