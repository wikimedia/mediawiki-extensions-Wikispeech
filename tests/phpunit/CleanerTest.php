<?php

namespace MediaWiki\Wikispeech\Tests;

use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\Cleaner;
use MediaWiki\Wikispeech\Segment\SegmentBreak;
use MediaWikiTestCase;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @covers \MediaWiki\Wikispeech\Segment\Cleaner
 */
class CleanerTest extends MediaWikiTestCase {

	/** @var Cleaner */
	private $cleaner;

	protected function setUp() : void {
		parent::setUp();
		$removeTags = [
			'sup' => 'reference',
			'h2' => false,
			'del' => true,
			'div' => [ 'toc', 'thumb' ]
		];
		$segmentBreakingTags = [
			'hr',
			'a'
		];
		$this->cleaner = new Cleaner(
			$removeTags,
			$segmentBreakingTags
		);
	}

	public function testCleanTags() {
		$markedUpText = '<i>Element content</i>';
		$expectedCleanedContent = [
			new CleanedText( 'Element content' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	/**
	 * Assert cleaning doesn't do more or less than it should.
	 *
	 * Runs several tests to ensure that the cleaning functions
	 * neither do more nor less than they should. This includes:
	 * - the tested string
	 * - the tested string preceded and followed by strings, that
	 *   should not be altered
	 * - the tested string twice in a row, joined by a string that
	 *   should not be altered
	 * Paths aren't tested since they have their separate tests.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContents The content array that is
	 *  the expected output.
	 * @param string $markedUpText The string that contains the markup
	 *  that should be cleaned
	 */
	private function assertTextCleaned(
		$expectedCleanedContents,
		$markedUpText
	) {
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
		$this->assertWithPrefixAndSuffix(
			$expectedCleanedContents,
			$markedUpText
		);
		$this->assertWithInfix(
			$expectedCleanedContents,
			$markedUpText
		);
	}

	/**
	 * Assert two arrays of `CleanedContent`s have matching strings.
	 *
	 * Checking only the strings makes it more convenient to write
	 * tests where other variables aren't relevant.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContents The content array that is
	 *  the expected output.
	 * @param array $cleanedContents The content array to test.
	 */
	private function assertContentsEqual(
		$expectedCleanedContents,
		$cleanedContents
	) {
		// This is needed to not test path too. Looping over the
		// contents and asserting only the string variable is not
		// possible, as it gives warning:
		// Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed.
		foreach ( $cleanedContents as $cleanedContent ) {
			$cleanedContent->path = null;
		}
		foreach ( $expectedCleanedContents as $expectedCleanedContent ) {
			$expectedCleanedContent->path = null;
		}
		$this->assertEquals(
			$expectedCleanedContents,
			$cleanedContents
		);
	}

	/**
	 * Assert correct output when input is preceded and followed by text.
	 *
	 * Pre- and suffix strings are concatenated to the first and last
	 * `CleanedText` respectively, unless there are tags in the marked
	 * up text. In that case, new `CleanedText`s are added.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContents The content array that is
	 *  the expected output, excluding pre- and suffix.
	 * @param string $markedUpText The string that contains the markup
	 *  that should be cleaned
	 */
	private function assertWithPrefixAndSuffix(
		$expectedCleanedContents,
		$markedUpText
	) {
		if ( $markedUpText[0] == '<' ) {
			array_unshift(
				$expectedCleanedContents,
				new CleanedText( 'prefix' )
			);
		} else {
			$expectedCleanedContents[0] =
				new CleanedText(
					'prefix' . $expectedCleanedContents[0]->string
				);
		}
		$lastCharIndex = mb_strlen( $markedUpText ) - 1;
		if ( $markedUpText[$lastCharIndex] == '>' ) {
			array_push(
				$expectedCleanedContents,
				new CleanedText( 'suffix' )
			);
		} else {
			$lastContentIndex = count( $expectedCleanedContents ) - 1;
			$expectedCleanedContents[$lastContentIndex] =
				new CleanedText(
					$expectedCleanedContents[$lastContentIndex]->string
					. 'suffix'
				);
		}
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( 'prefix' . $markedUpText . 'suffix' )
		);
	}

	/**
	 * Assert correct output when input is repeated and separated by string.
	 *
	 * Adds the infix as a `CleanedText` between two copies of
	 * $expectedCleanedContents. If the marked up text doesn't end
	 * with a tag, the infix is added to the end of the first
	 * copy. Similarily, it's added to the beginning if the marked up
	 * text doesn't start with a tag.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContents The content array that
	 *  will be repeated to create the expected output.
	 * @param string $markedUpText The string that contains the markup
	 *  that should be cleaned
	 */
	private function assertWithInfix(
		$expectedCleanedContents,
		$markedUpText
	) {
		$infix = new CleanedText( 'infix' );
		$firstContents = $expectedCleanedContents;
		$lastCharIndex = mb_strlen( $markedUpText ) - 1;
		if ( $markedUpText[$lastCharIndex] != '>' ) {
			$adjacent = array_pop( $firstContents );
			$infix->string = $adjacent->string . $infix->string;
		}
		$secondContents = $expectedCleanedContents;
		if ( $markedUpText[0] != '<' ) {
			$adjacent = array_shift( $secondContents );
			$infix->string .= $adjacent->string;
		}
		$this->assertContentsEqual(
			array_merge( $firstContents, [ $infix ], $secondContents ),
			$this->cleaner->cleanHtml( $markedUpText . 'infix' . $markedUpText )
		);
	}

	public function testDontAlterStringsWithoutMarkup() {
		$markedUpText = 'A string without any fancy markup.';
		$expectedCleanedContent = [
			new CleanedText( 'A string without any fancy markup.' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContent,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanNestedTags() {
		$markedUpText = '<i><b>Nested content</b></i>';
		$expectedCleanedContent = [
			new CleanedText( 'Nested content' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanEmptyElementTags() {
		$markedUpText = '<br />';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testRemoveTags() {
		$markedUpText = '<del>removed tag </del>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testRemoveNestedTags() {
		$markedUpText = '<del><i>nested removed tag</i></del>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testRemoveDoubleNestedTags() {
		$markedUpText = '<del><i><b>double nested removed tag</b></i></del>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testRemoveTagsWithCertainClass() {
		$markedUpText = '<sup class="reference">Remove this.</sup>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testRemoveTagsWithOneOfClasses() {
		$markedUpText = '<div class="toc">Remove this.</div><div class="thumb">Also this.</div>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testDontRemoveTagsWithoutCertainClass() {
		$markedUpText =
			'<sup>I am not a reference.</sup><sup class="not-a-reference">Neither am I.</sup>';
		$expectedCleanedContent = [
			new CleanedText( 'I am not a reference.' ),
			new CleanedText( 'Neither am I.' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontRemoveTagsWhoseCriteriaAreFalse() {
		$markedUpText = '<h2>Contents</h2>';
		$expectedCleanedContent = [
			new CleanedText( 'Contents' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testAddSegmentBreaksForTags() {
		$markedUpText =
			'prefix<a>content</a>suffix';
		$expectedCleanedContents = [
			new CleanedText( 'prefix' ),
			new SegmentBreak(),
			new CleanedText( 'content' ),
			new SegmentBreak(),
			new CleanedText( 'suffix' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testAddSegmentBreaksForEmptyTags() {
		$markedUpText =
			'before<hr />after';
		$expectedCleanedContents = [
			new CleanedText( 'before' ),
			new SegmentBreak(),
			new CleanedText( 'after' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testAddSegmentBreaksForNestedTags() {
		$markedUpText =
			'<a>before<hr />after</a>';
		$expectedCleanedContents = [
			new CleanedText( 'before' ),
			new SegmentBreak(),
			new CleanedText( 'after' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testAddSegmentBreaksAfterNestedTags() {
		$markedUpText =
			'<a><hr />inside</a>after';
		$expectedCleanedContents = [
			new CleanedText( 'inside' ),
			new SegmentBreak(),
			new CleanedText( 'after' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testDontAddMultipleConsecutiveSegmentBreaks() {
		$markedUpText =
			'before<hr /><hr />after';
		$expectedCleanedContents = [
			new CleanedText( 'before' ),
			new SegmentBreak(),
			new CleanedText( 'after' ),
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testDontAddSegmentBreaksAtStartOrEnd() {
		$markedUpText =
			'<a>content</a>';
		$expectedCleanedContents = [
			new CleanedText( 'content' ),
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testHandleMultipleClasses() {
		$markedUpText =
			'<sup class="reference another-class">Remove this.</sup>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanNestedTagsWhereSomeAreRemovedAndSomeAreKept() {
		$markedUpText = '<i><b>not removed</b><del>removed</del></i>';
		$expectedCleanedContent = [
			new CleanedText( 'not removed' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleUtf8Characters() {
		$markedUpText = '—';
		$expectedCleanedContent = [ new CleanedText( '—' ) ];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleHtmlEntities() {
		$markedUpText = '6&#160;p.m';
		$expectedCleanedContent = [ new CleanedText( '6 p.m' ) ];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleNewlines() {
		$markedUpText = "<i>Keep this newline\n</i>";
		$expectedCleanedContent = [
			new CleanedText( "Keep this newline\n" )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleEndTagFollowedByEmptyElementTag() {
		$markedUpText = '<i>content</i><br />';
		$expectedCleanedContent = [
			new CleanedText( 'content' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleEmptyElementTagInsideElement() {
		$markedUpText = '<i>content<br /></i>';
		$expectedCleanedContent = [
			new CleanedText( 'content' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testIgnoreComments() {
		$markedUpText = '<!-- A comment. -->';
		$expectedCleanedContent = [];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testGeneratePaths() {
		$markedUpText = '<i>level one<br /><b>level two</b></i>level zero';
		$expectedCleanedContent = [
			new CleanedText( 'level one', './i/text()' ),
			new CleanedText( 'level two', './i/b/text()' ),
			new CleanedText( 'level zero', './text()' )
		];
		$this->assertEquals(
			$expectedCleanedContent,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testGeneratePathsNestedOfSameType() {
		$markedUpText = '<i id="1">one<i id="2">two</i></i>';
		$expectedCleanedContent = [
			new CleanedText( 'one', './i/text()' ),
			new CleanedText( 'two', './i/i/text()' )
		];
		$this->assertEquals(
			$expectedCleanedContent,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testGeneratePathsNodesOnSameLevel() {
		$markedUpText = 'level zero<br />also level zero';
		$expectedCleanedContent = [
			new CleanedText( 'level zero', './text()[1]' ),
			new CleanedText( 'also level zero', './text()[2]' )
		];
		$this->assertEquals(
			$expectedCleanedContent,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}
}
