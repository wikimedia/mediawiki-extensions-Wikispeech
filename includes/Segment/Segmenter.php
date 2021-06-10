<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use DOMDocument;
use DOMXPath;
use FormatJson;
use IContextSource;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use Title;
use WANObjectCache;
use WikiPage;

/**
 * Used for dividing text into segments, that can then be sent to the
 * Speechoid service. Also calculates values for variables that are needed
 * for highlighting.
 *
 * @since 0.0.1
 */

class Segmenter {

	/**
	 * @var IContextSource
	 */
	private $context;

	/**
	 * An array to which finished segments are added.
	 *
	 * @var Segment[]
	 */
	private $segments;

	/**
	 * The segment that is currently being built.
	 *
	 * @var Segment
	 */
	private $currentSegment;

	/** @var WANObjectCache */
	private $cache;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/**
	 * @since 0.0.1
	 * @param IContextSource $context
	 * @param WANObjectCache $cache
	 * @param HttpRequestFactory $requestFactory
	 */
	public function __construct(
		IContextSource $context,
		WANObjectCache $cache,
		HttpRequestFactory $requestFactory
	) {
		$this->context = $context;
		$this->cache = $cache;
		$this->requestFactory = $requestFactory;
		$this->segments = [];
		$this->currentSegment = new Segment();
	}

	/**
	 * Split the content of a page into segments.
	 *
	 * Non-latest revisions are only handled if already in the cache.
	 *
	 * @note This provides access to current page content and (cached) older
	 * revisions. No audience checks are applied and it will therefore not fail
	 * due to access restrictions.
	 *
	 * @since 0.1.5
	 * @param Title $title
	 * @param array|null $removeTags HTML tags that should not be
	 *  included, defaults to config variable "WikispeechRemoveTags".
	 * @param array|null $segmentBreakingTags HTML tags that mark
	 *  segment breaks, defaults to config variable
	 *  "WikispeechSegmentBreakingTags".
	 * @param int|null $revisionId Revision to be segmented
	 * @param string|null $consumerUrl URL to the script path on the consumer,
	 *  if used as a producer.
	 * @return Segment[] A list of segments each made up of `CleanedTest`
	 *  objects and with start and end offset.
	 * @throws MWException If failing to create WikiPage from title or an
	 *  invalid or non-cached and outdated revision was provided.
	 */
	public function segmentPage(
		Title $title,
		array $removeTags = null,
		array $segmentBreakingTags = null,
		int $revisionId = null,
		string $consumerUrl = null
	): array {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		if ( $removeTags === null ) {
			$removeTags = $config->get( 'WikispeechRemoveTags' );
		}
		if ( $segmentBreakingTags === null ) {
			$segmentBreakingTags = $config->get( 'WikispeechSegmentBreakingTags' );
		}
		$page = null;
		if ( $consumerUrl ) {
			$request = wfAppendQuery(
				$consumerUrl . '/api.php',
				[
					'action' => 'parse',
					'format' => 'json',
					'page' => $title,
					'prop' => 'text|revid|displaytitle'
				]
			);
			$responseString = $this->requestFactory->get( $request );
			if ( $responseString === null ) {
				throw new MWException( "Failed to get page with title '$title' from consumer on URL $consumerUrl." );
			}
			$response = FormatJson::parse( $responseString )->getValue();
			$displayTitle = $response->parse->displaytitle;
			$pageContent = $response->parse->text->{'*'};
			if ( $revisionId == null ) {
				$revisionId = $response->parse->revid;
			}
		} else {
			$page = WikiPage::factory( $title );
			$popts = $page->makeParserOptions( $this->context );
			$pout = $page->getParserOutput( $popts );
			$displayTitle = $pout->getDisplayTitle();
			$pageContent = $pout->getText();
			if ( $revisionId == null ) {
				$revisionId = $page->getLatest();
			}
		}
		$cacheKey = $this->cache->makeKey(
			'Wikispeech.segments',
			get_class( $this ),
			$consumerUrl ?? 'local',
			$revisionId,
			var_export( $removeTags, true ),
			implode( '-', $segmentBreakingTags ) );
		$segments = $this->cache->get( $cacheKey );
		if ( $segments === false ) {
			LoggerFactory::getInstance( 'Wikispeech' )
				->info(
					__METHOD__ . ': Segmenting page: {title}',
					[ 'title' => $title ]
				);
			if ( !$consumerUrl && $revisionId != $page->getLatest() ) {
				throw new MWException( 'An outdated or invalid revision id was provided' );
			}
			$cleanedText = $this->cleanPage(
				$displayTitle,
				$pageContent,
				$removeTags,
				$segmentBreakingTags
			);
			$segments = $this->segmentSentences( $cleanedText );
			$this->cache->set( $cacheKey, $segments, 3600 );
		}
		return $segments;
	}

	/**
	 * Clean content text and title.
	 *
	 * @since 0.1.5
	 * @param string $displayTitle
	 * @param string $pageContent
	 * @param array $removeTags HTML tags that should not be included.
	 * @param array $segmentBreakingTags HTML tags that mark segment breaks.
	 * @return SegmentContent[] Title and content represented as `CleanedText`s
	 *  and `SegmentBreak`s
	 * @throws MWException If segmented title text is not an instance of CleanedText
	 */
	protected function cleanPage(
		string $displayTitle,
		string $pageContent,
		array $removeTags,
		array $segmentBreakingTags
	): array {
		// Clean HTML.
		$cleanedText = null;
		// Parse latest revision, using parser cache.
		$cleaner = new Cleaner( $removeTags, $segmentBreakingTags );
		$cleanedText = $cleaner->cleanHtml( $pageContent );
		// Create a DOM for the title to get the Xpath, in case there
		// are elements within the title. This happens e.g. when the
		// title is italicized.
		$dom = new DOMDocument();
		$dom->loadHTML(
			'<h1>' . $displayTitle . '</h1>',
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
		);
		$xpath = new DOMXPath( $dom );
		$node = $xpath->evaluate( '//text()' )->item( 0 );
		$titleSegment = $cleaner->cleanHtml( $displayTitle )[0];
		if ( $titleSegment instanceof CleanedText ) {
			$titleSegment->setPath( '/' . $node->getNodePath() );
		} else {
			throw new MWException( 'Segmented title is not an instance of CleanedText!' );
		}
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
	 * @param SegmentContent[] $cleanedContent An array of items returned by
	 *  `Cleaner::cleanHtml()`.
	 * @return Segment[] An array of segments, each containing the
	 *  `CleanedText's in that segment.
	 */
	private function segmentSentences( array $cleanedContent ): array {
		foreach ( $cleanedContent as $item ) {
			if ( $item instanceof CleanedText ) {
				$this->addContentsToCurrentSegment( $item );
			} elseif ( $item instanceof SegmentBreak ) {
				$this->finishSegment();
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
	 * @since 0.0.1
	 * @param CleanedText $text The text to segment.
	 */
	private function addContentsToCurrentSegment( CleanedText $text ) {
		$nextStartOffset = 0;
		do {
			$endOffset = $this->addContentToCurrentSegment( $text, $nextStartOffset );
			// The earliest the next segments can start is one after
			// the end of the current one.
			$nextStartOffset = $endOffset + 1;
		} while ( $nextStartOffset < mb_strlen( $text->getString() ) - 1 );
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
	private function addContentToCurrentSegment(
		CleanedText $text,
		int $startOffset = 0
	): int {
		if ( $this->currentSegment->getStartOffset() === null ) {
			// Move the start offset ahead by the number of leading
			// whitespaces. This means that whitespaces before or
			// between segments aren't included.
			$leadingWhitespacesLength = self::getLeadingWhitespacesLength(
				mb_substr( $text->getString(), $startOffset )
			);
			$startOffset += $leadingWhitespacesLength;
		}
		// Get the offset for the next sentence final character.
		$endOffset = self::getSentenceFinalOffset(
			$text->getString(),
			$startOffset
		);
		// If no sentence final character is found, add the rest of
		// the text and remember that this segment isn't ended.
		$ended = true;
		if ( $endOffset === null ) {
			$endOffset = mb_strlen( $text->getString() ) - 1;
			$ended = false;
		}
		$sentence = mb_substr(
			$text->getString(),
			$startOffset,
			$endOffset - $startOffset + 1
		);
		if ( $sentence !== '' && $sentence !== "\n" ) {
			// Don't add `CleanedText`s with the empty string or only
			// newline.
			$sentenceText = new CleanedText(
				$sentence,
				$text->getPath()
			);
			$this->currentSegment->addContent( $sentenceText );
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
	 * @since 0.0.1
	 * @param string $string The string to count leading whitespaces
	 *  for.
	 * @return int The number of whitespaces at the start of $string.
	 */
	private static function getLeadingWhitespacesLength( string $string ): int {
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
	private static function getSentenceFinalOffset(
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
	private static function isSentenceFinal(
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
	private static function isUpper( string $string ): bool {
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
		if ( count( $this->currentSegment->getContent() ) ) {
			$this->currentSegment->setHash( $this->evaluateHash( $this->currentSegment ) );
			$this->segments[] = $this->currentSegment;
		}
		// Create a fresh segment to add following text to.
		$this->currentSegment = new Segment();
	}

	/**
	 * Used to evaluate hash of segments, the primary key for stored utterances.
	 *
	 * @since 0.1.4
	 * @param Segment $segment The segment to be evaluated.
	 * @return string SHA256 message digest
	 */
	public function evaluateHash( Segment $segment ): string {
		$context = hash_init( 'sha256' );
		foreach ( $segment->getContent() as $part ) {
			hash_update( $context, $part->getString() );
			hash_update( $context, "\n" );
		}
		return hash_final( $context );
		// Uncommenting below block can be useful during creation of
		// new test cases as you might need to figure out hashes.
		//LoggerFactory::getInstance( 'Segmenter' )
		//	->info( __METHOD__ . ': {segement} : {hash}', [
		//		'segment' => $segment,
		//		'hash' => $hash
		//	] );
	}

	/**
	 * Get a segment from a page.
	 *
	 * @since 0.1.5
	 * @param Title $title
	 * @param string $hash Hash of the segment to get.
	 * @param int $revisionId Revision of the page where the segment was found.
	 * @param string|null $consumerUrl URL to the script path on the consumer,
	 *  if used as a producer.
	 * @return Segment|null The segment matching $hash.
	 */
	public function getSegment(
		Title $title,
		string $hash,
		int $revisionId,
		string $consumerUrl = null
	): ?Segment {
		$segments = $this->segmentPage( $title, null, null, $revisionId, $consumerUrl );
		foreach ( $segments as $segment ) {
			if ( $segment->getHash() === $hash ) {
				return $segment;
			}
		}
		return null;
	}
}
