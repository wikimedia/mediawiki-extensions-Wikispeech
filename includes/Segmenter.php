<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class Segmenter {

	/**
	 * Divide a string into segments, where each segment is a sentence. A
	 * sentence is here defined as a number of tokens ending with a dot (full
	 * stop) or a newline. Headings are also considered sentences.
	 *
	 * @since 0.0.1
	 * @param string $text A string to segment.
	 * @return array The segments found.
	 */

	public function segmentSentences( $text ) {
		$matches = [];
		// Find the indices of all characters that may be sentence final.
		preg_match_all(
			"/(.|\n)/",
			$text,
			$matches,
			PREG_OFFSET_CAPTURE );
		$start = 0;
		$segments = [];
		foreach ( $matches[ 0 ] as $match ) {
			$index = $match[ 1 ];
			if ( self::isSentenceFinal( $text, $index ) ) {
				$length = $index - $start + 1;
				$segment = trim( substr( $text, $start, $length ) );
				if ( $segment != '' ) {
					// Strings that are only whitespaces are not considered
					// sentences.
					array_push( $segments, $segment );
					// Start the next sentence after the sentence final
					// character.
					$start = $index + 1;
				}
			}
		}
		return $segments;
	}

	/**
	 * Tests if a character is at the end of a sentence. Dots in abbreviations
	 * should only be counted when they also are sentence final. For example:
	 * "Monkeys, penguins etc.", but not "Monkeys e.g. baboons".
	 *
	 * @since 0.0.1
	 * @param string $string The string to check in.
	 * @param int $index The the index in $string of the character to check.
	 * @return bool True if the character is sentence final, else false.
	 */

	private function isSentenceFinal( $string, $index ) {
		$character = $string[ $index ];
		$nextCharacter = $string[ $index + 1 ];
		$characterAfterNext = $string[ $index + 2 ];
		if ( $character == "\n" ) {
			// A newline is always sentence final.
			return true;
		} elseif (
			$character == '.' &&
			$nextCharacter == ' ' && self::isUpper( $characterAfterNext ) ||
			$nextCharacter == "\n" ||
			$nextCharacter == ''
		) {
			// A dot is sentence final if it's followed by a space and a
			// capital letter, at the end of line or at the end of string.
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Tests if a string is upper case.
	 *
	 * @since 0.0.1
	 * @param string $string The string to test.
	 * @return bool True if the entire string is upper case, else false.
	 */

	private function isUpper( $string ) {
		return mb_strtoupper( $string, 'UTF-8' ) == $string;
	}

	/**
	 * Split a string by newline.
	 *
	 * @since 0.0.1
	 * @param string $text A string to segment.
	 * @return array The segments found. Segments only containing whitespaces
	 * are discarded.
	 */

	public function segmentParagraphs( $text ) {
		$segments = [];
		foreach ( explode( "\n", $text ) as $segment ) {
			if ( strlen( trim( $segment ) ) > 0 ) {
				array_push( $segments, $segment );
			}
		}
		return $segments;
	}
}
