<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * Used for dividing text into segments, that can then be sent to the
 * Speechoid service. Also calculates values for variables that are needed
 * for highlighting.
 *
 * @since 0.0.1
 */

class Segmenter {

	/**
	 * @var IContextSource $context
	 */
	private $context;

	/**
	 * An array to which finished segments are added.
	 *
	 * @var array $segments
	 */

	private $segments;

	/**
	 * The segment that is currently being built.
	 *
	 * @var array $segments
	 */

	private $currentSegment;

	/**
	 * @since 0.0.1
	 * @param IContextSource $context
	 */
	public function __construct( $context ) {
		$this->context = $context;
		$this->segments = [];
		$this->currentSegment = [
			'content' => [],
			'startOffset' => null,
			'endOffset' => null
		];
	}

	/**
	 * Split the content of a page into segments.
	 *
	 * Non-latest revisions are only handled if already in the cache.
	 *
	 * @since 0.1.5
	 * @param Title $title
	 * @param array|null $removeTags HTML tags that should not be
	 *  included, defaults to config variable "WikispeechRemoveTags".
	 * @param array|null $segmentBreakingTags HTML tags that mark
	 *  segment breaks, defaults to config variable
	 *  "WikispeechSegmentBreakingTags".
	 * @param int|null $revisionId Revision to be segmented
	 * @return array A list of segments each made up of `CleanedTest`
	 *  objects and with start and end offset.
	 * @throws MWException If failing to create WikiPage from title or an
	 *  invalid or non-cached and outdated revision was provided.
	 */
	public function segmentPage(
		$title,
		$removeTags = null,
		$segmentBreakingTags = null,
		$revisionId = null
	) {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		if ( $removeTags === null ) {
			$removeTags = $config->get( 'WikispeechRemoveTags' );
		}
		if ( $segmentBreakingTags === null ) {
			$segmentBreakingTags = $config->get( 'WikispeechSegmentBreakingTags' );
		}
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$page = WikiPage::factory( $title );
		if ( $revisionId == null ) {
			$revisionId = $page->getLatest();
		}
		$cacheKey = $cache->makeKey(
			'Wikispeech.segments',
			$revisionId,
			var_export( $removeTags, true ),
			implode( '-', $segmentBreakingTags ) );
		$segments = $cache->get( $cacheKey );
		if ( $segments === false ) {
			LoggerFactory::getInstance( 'Wikispeech' )
				->info(
					__METHOD__ . ': Segmenting page: {title}',
					[ 'title' => $title ]
				);
			if ( $revisionId != $page->getLatest() ) {
				throw new MWException( 'An outdated or invalid revision id was provided' );
			}
			$cleanedText =
				$this->cleanPage( $page, $removeTags, $segmentBreakingTags );
			$segments = $this->segmentSentences( $cleanedText );
			$cache->set( $cacheKey, $segments, 3600 );
		}
		return $segments;
	}

	/**
	 * Clean content text and title.
	 *
	 * @since 0.1.5
	 * @param WikiPage $page
	 * @param array $removeTags HTML tags that should not be included.
	 * @param array $segmentBreakingTags HTML tags that mark segment breaks.
	 * @return array Title and content represented as `CleanedText`s
	 *  and `SegmentBreak`s
	 */
	protected function cleanPage(
		$page,
		$removeTags,
		$segmentBreakingTags
	) {
		// Clean HTML.
		$cleanedText = null;
		// Parse latest revision, using parser cache.
		$popts = $page->makeParserOptions( $this->context );
		$pout = $page->getParserOutput( $popts );
		$displayTitle = $pout->getDisplayTitle();
		$pageContent = $pout->getText();
		$cleaner = new Cleaner( $removeTags, $segmentBreakingTags );
		$titleSegment = $cleaner->cleanHtml( $displayTitle )[0];
		$titleSegment->path = '//h1[@id="firstHeading"]//text()';
		$cleanedText = $cleaner->cleanHtml( $pageContent );
		// Add the title as a separate utterance to the start.
		array_unshift( $cleanedText, $titleSegment, new SegmentBreak() );
		return $cleanedText;
	}

	/**
	 * Divide a cleaned content array into segments, one for each sentence.
	 *
	 * A segment is an array with the keys "content", "startOffset"
	 * and "endOffset". "content" is an array of `CleanedText`s and
	 * `SegmentBreak`s. "startOffset" is the offset of the first
	 * character of the segment, within the text node it
	 * appears. "endOffset" is the offset of the last character of the
	 * segment, within the text node it appears. These are used to
	 * determine start and end of a segment in the original HTML.
	 *
	 * A sentence is here defined as a sequence of tokens ending with
	 * a dot (full stop).
	 *
	 * @since 0.0.1
	 * @param array $cleanedContent An array of items returned by
	 *  `Cleaner::cleanHtml()`.
	 * @return array An array of segments, each containing the
	 *  `CleanedText's in that segment.
	 */
	private function segmentSentences( $cleanedContent ) {
		foreach ( $cleanedContent as $item ) {
			if ( $item instanceof CleanedText ) {
				$this->addSegments( $item );
			} elseif ( $item instanceof SegmentBreak ) {
				$this->finishSegment();
			}
		}
		if ( $this->currentSegment['content'] ) {
			// Add the last segment, unless it's empty.
			$this->finishSegment();
		}
		return $this->segments;
	}

	/**
	 * Add segments for a string.
	 *
	 * Looks for sentence final strings (strings which a sentence ends
	 * with). When a sentence final string is found, it's sentence is
	 * added to the $currentSegment.
	 *
	 * @since 0.0.1
	 * @param CleanedText $text The text to segment.
	 */
	private function addSegments( $text ) {
		$nextStartOffset = 0;
		do {
			$endOffset = $this->addSegment( $text, $nextStartOffset );
			// The earliest the next segments can start is one after
			// the end of the current one.
			$nextStartOffset = $endOffset + 1;
		} while ( $nextStartOffset < mb_strlen( $text->string ) - 1 );
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
	 * @since 0.0.1
	 * @param CleanedText $text The text to segment.
	 * @param int $startOffset The offset where the next sentence can
	 *  start, at the earliest. If the sentence has leading
	 *  whitespaces, this will be moved forward.
	 * @return int The offset of the last character in the
	 *   sentence. If the sentence didn't end yet, this is the last
	 *   character of $text.
	 */
	private function addSegment( $text, $startOffset = 0 ) {
		if ( $this->currentSegment['startOffset'] === null ) {
			// Move the start offset ahead by the number of leading
			// whitespaces. This means that whitespaces before or
			// between segments aren't included.
			$leadingWhitespacesLength = self::getLeadingWhitespacesLength(
				mb_substr( $text->string, $startOffset )
			);
			$startOffset += $leadingWhitespacesLength;
		}
		// Get the offset for the next sentence final character.
		$endOffset = self::getSentenceFinalOffset(
			$text->string,
			$startOffset
		);
		// If no sentence final character is found, add the rest of
		// the text and remember that this segment isn't ended.
		$ended = true;
		if ( $endOffset === null ) {
			$endOffset = mb_strlen( $text->string ) - 1;
			$ended = false;
		}
		$sentence = mb_substr(
			$text->string,
			$startOffset,
			$endOffset - $startOffset + 1
		);
		if ( $sentence !== '' && $sentence !== "\n" ) {
			// Don't add `CleanedText`s with the empty string or only
			// newline.
			$sentenceText = new CleanedText(
				$sentence,
				$text->path
			);
			$this->currentSegment['content'][] = $sentenceText;
			if ( $this->currentSegment['startOffset'] === null ) {
				// Record the start offset if this is the first text
				// added to the segment.
				$this->currentSegment['startOffset'] = $startOffset;
			}
			$this->currentSegment['endOffset'] = $endOffset;
			if ( $ended ) {
				$this->finishSegment();
			}
		}
		return $endOffset;
	}

	/**
	 * Get the number of whitespaces at the start of a string.
	 *
	 * @since 0.0.1
	 * @param string $string The string to count leading whitespaces
	 *  for.
	 * @return int The number of whitespaces at the start of $string.
	 */
	private static function getLeadingWhitespacesLength( $string ) {
		$trimmedString = ltrim( $string );
		return mb_strlen( $string ) - mb_strlen( $trimmedString );
	}

	/**
	 * Get the offset of the first sentence final character in a string.
	 *
	 * @since 0.0.1
	 * @param string $string The string to look in.
	 * @param int $offset The offset to start looking from.
	 * @return int|null The offset of the first sentence final character
	 *  that was found, if any, else null.
	 */
	private static function getSentenceFinalOffset( $string, $offset ) {
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
		} while ( !self::isSentenceFinal( $string, $offset ) );
		return $offset;
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
	 * @param int $index The index in $string of the character to check.
	 * @return bool True if the character is sentence final, else false.
	 */
	private static function isSentenceFinal( $string, $index ) {
		$character = mb_substr( $string, $index, 1 );
		$nextCharacter = null;
		if ( mb_strlen( $string ) > $index + 1 ) {
			$nextCharacter = mb_substr( $string, $index + 1, 1 );
		}
		$characterAfterNext = null;
		if ( mb_strlen( $string ) > $index + 2 ) {
			$characterAfterNext = mb_substr( $string, $index + 2, 1 );
		}
		if ( $character == '.' && (
				!$nextCharacter ||
				$nextCharacter == "\n" || (
					$nextCharacter == ' ' && (
						!$characterAfterNext ||
						self::isUpper( $characterAfterNext ) ) ) )
		) {
			// A dot is sentence final if it's at the end of string or line
			// or followed by a space and a capital letter.
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
		return mb_strtoupper( $string ) == $string;
	}

	/**
	 * Add the current segment to the array of segments.
	 *
	 * Creates a new, empty segment as the new current segment.
	 *
	 * @since 0.0.1
	 */
	private function finishSegment() {
		if ( count( $this->currentSegment['content'] ) ) {
			$this->currentSegment['hash'] = $this->evaluateHash( $this->currentSegment );
			$this->segments[] = $this->currentSegment;
		}
		// Create a fresh segment to add following text to.
		$this->currentSegment = [
			'content' => [],
			'startOffset' => null,
			'endOffset' => null
		];
	}

	/**
	 * Used to evaluate hash of segments, the primary key for stored utterances.
	 *
	 * @since 0.1.4
	 * @param array $segment The segment array of {@see CleanedText} to be evaluated.
	 * @return string SHA256 message digest
	 */
	public function evaluateHash( $segment ) {
		$context = hash_init( 'sha256' );
		$content = $segment['content'];
		foreach ( $content as $part ) {
			hash_update( $context, $part->string );
			hash_update( $context, "\n" );
		}
		$hash = hash_final( $context );
		// Uncommenting below block can be useful during creation of
		// new test cases as you might need to figure out hashes.
		//LoggerFactory::getInstance( 'Segmenter' )
		//	->info( __METHOD__ . ': {segement} : {hash}', [
		//		'segment' => $segment,
		//		'hash' => $hash
		//	] );
		return $hash;
	}

	/**
	 * Get a segment from a page.
	 *
	 * @since 0.1.5
	 * @param Title $title
	 * @param string $hash Hash of the segment to get
	 * @return array The segment matching $hash.
	 */
	public function getSegment( $title, $hash ) {
		$segments = $this->segmentPage( $title );
		foreach ( $segments as $segment ) {
			if ( $segment['hash'] === $hash ) {
				return $segment;
			}
		}
	}
}
