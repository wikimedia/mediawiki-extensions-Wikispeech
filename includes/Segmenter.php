<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class Segmenter {

	/**
	 * Divide a cleaned content array into segments, one for each sentence.
	 *
	 * A segment is an array with the keys "content" and "position". Content is
	 * an array of CleanedTags and strings. Position is the start
	 * position, in the HTML, for the first node in content, i.e. the start
	 * position of the segment.
	 *
	 * A sentence is here defined as a number of tokens ending with a dot (full
	 * stop). Headings are also considered sentences.
	 *
	 * @since 0.0.1
	 * @param array $cleanedContent An array of cleaned content, as returned by
	 *  Cleaner::cleanHtml().
	 * @return array An array of segments, each containing the nodes in that
	 *  segment and the start position in the HTML.
	 */

	public static function segmentSentences( $cleanedContent ) {
		$segments = [];
		$currentSegment = [
			'position' => 0,
			'content' => []
		];
		foreach ( $cleanedContent as $content ) {
			if ( $content instanceof CleanedTag ) {
				// Non-text nodes are always added to the current segment, as
				// they can't contain segment breaks.
				array_push( $currentSegment['content'], $content );
			} else {
				self::addSegments(
					$segments,
					$currentSegment,
					$content
				);
			}
		}
		if ( $currentSegment['content'] ) {
			// Add the last segment, unless it's empty.
			array_push( $segments, $currentSegment );
		}
		return $segments;
	}

	/**
	 * Add segments for a string.
	 *
	 * Looks for sentence final string (strings which a sentence ends
	 * with). When a sentence final string is found, it's sentence is
	 * added to the $currentSegment, which in turn is added to
	 * $segments. An empty array is created as the new
	 * $currentSegment.
	 *
	 * When the end of string is reached, the remaining string is
	 * added to $currentSegment. Subsequent segment parts will be
	 * added to this semgent.
	 *
	 * @since 0.0.1
	 * @param array $segments The segment array to add new segments to.
	 * @param array $currentSegment The segment under construction, to which
	 *  the first found string segment will be added.
	 * @param string $text The string to segment.
	 */

	private static function addSegments(
		&$segments,
		&$currentSegment,
		$text
	) {
		// Find the indices of all characters that may be sentence final.
		preg_match_all(
			"/\./",
			$text,
			$matches,
			PREG_OFFSET_CAPTURE
		);
		$position = 0;
		foreach ( $matches[0] as $match ) {
			$sentenceFinalPosition = $match[1];
			if ( self::isSentenceFinal( $text, $sentenceFinalPosition ) ) {
				$length = $sentenceFinalPosition - $position + 1;
				$segmentText = substr( $text, $position, $length );
				if ( trim( $segmentText ) != '' ) {
					// Don't add segments with only whitespaces.
					array_push( $currentSegment['content'], $segmentText );
					$position = $sentenceFinalPosition + 1;
					array_push( $segments, $currentSegment );
					$nextSegmentPosition = $currentSegment['position'] +
						self::getSegmentLength( $currentSegment['content'] );
					$currentSegment = [
						'position' => $nextSegmentPosition,
						'content' => []
					];
				}
			}
		}
		$remainder = substr( $text, $position );
		if ( $remainder ) {
			// Add any remaining part of the string.
			array_push( $currentSegment['content'], $remainder );
		}
	}

	/**
	 * Test if a character is at the end of a sentence.
	 *
	 * Dots in abbreviations should only be counted when they also are sentence
	 * final. For example:
	 * "Monkeys, penguins etc.", but not "Monkeys e.g. baboons".
	 *
	 * @since 0.0.1
	 * @param string $string The string to check in.
	 * @param int $index The the index in $string of the character to check.
	 * @return bool True if the character is sentence final, else false.
	 */

	private static function isSentenceFinal( $string, $index ) {
		$character = $string[$index];
		$nextCharacter = null;
		if ( strlen( $string ) > $index + 1 ) {
			$nextCharacter = $string[$index + 1];
		}
		$characterAfterNext = null;
		if ( strlen( $string ) > $index + 2 ) {
			$characterAfterNext = $string[$index + 2];
		}
		if (
			$character == '.' &&
			( $nextCharacter == ' ' && self::isUpper( $characterAfterNext ) ||
			$nextCharacter == '' ||
			$nextCharacter == "\n" )
		) {
			// A dot is sentence final if it's followed by a space and a
			// capital letter or at the end of string or line.
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Test if a string is upper case.
	 *
	 * @since 0.0.1
	 * @param string $string The string to test.
	 * @return bool true if the entire string is upper case, else false.
	 */

	private static function isUpper( $string ) {
		return mb_strtoupper( $string, 'UTF-8' ) == $string;
	}

	/**
	 * Calculate the length of a segment, as it is represented in HTML.
	 *
	 * @since 0.0.1
	 * @param array $segment An array of nodes.
	 * @return int The combinded length of the HTML of the nodes in $segment.
	 */

	private static function getSegmentLength( $segment ) {
		$length = 0;
		foreach ( $segment as $content ) {
			if ( $content instanceof CleanedTag ) {
				$length += $content->getLength();
			} else {
				$length += strlen( $content );
			}
		}
		return $length;
	}
}
