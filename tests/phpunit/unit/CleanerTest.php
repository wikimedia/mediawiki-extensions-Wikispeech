<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\CleanedText;
use MediaWiki\Wikispeech\Segment\Cleaner;
use MediaWiki\Wikispeech\Segment\PartOfContent\Link;
use MediaWiki\Wikispeech\Segment\SegmentBreak;
use MediaWiki\Wikispeech\Segment\SegmentContent;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Wikispeech\Segment\Cleaner
 */
class CleanerTest extends MediaWikiUnitTestCase {

	/** @var Cleaner */
	private $cleaner;

	protected function setUp(): void {
		parent::setUp();
		$this->createCleaner( false );
	}

	/**
	 * Add or replace the `Cleaner` instance used in the tests.
	 *
	 * @param bool $partOfContent
	 */
	private function createCleaner( $partOfContent ) {
		$removeTags = [
			'sup' => 'reference',
			'h2' => false,
			'del' => true,
			'div' => [ 'toc', 'thumb' ]
		];
		$segmentBreakingTags = [
			'hr',
			'q'
		];
		$this->cleaner = new Cleaner(
			$removeTags,
			$segmentBreakingTags,
			$partOfContent
		);
	}

	public function testCleanHtml_tags_cleanTags() {
		$markedUpText = '<i>Element content</i>';
		$expectedCleanedContent = [
			new CleanedText( 'Element content', './i/text()' )
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
	 * @param SegmentContent[] $expectedCleanedContents The content array that is
	 *  the expected output.
	 * @param SegmentContent[] $cleanedContents The content array to test.
	 * @param bool $testPaths
	 */
	private function assertContentsEqual(
		array $expectedCleanedContents,
		array $cleanedContents,
		bool $testPaths = true
	) {
		$this->assertSameSize( $expectedCleanedContents, $cleanedContents );
		foreach ( $expectedCleanedContents as $i => $expectedCleanedContent ) {
			$this->assertContentEquals( $expectedCleanedContent, $cleanedContents[$i], $testPaths );
		}
	}

	private function assertContentEquals(
		SegmentContent $expected,
		SegmentContent $value,
		bool $testPath
	) {
		if ( $expected instanceof CleanedText ) {
			$this->assertTrue( $value instanceof CleanedText );
			$this->assertSame( $expected->getString(), $value->getString() );
			if ( $testPath ) {
				$this->assertSame( $expected->getPath(), $value->getPath() );
			}
		} elseif ( $expected instanceof SegmentBreak ) {
			$this->assertTrue( $value instanceof SegmentBreak );
		} elseif ( $expected instanceof Link ) {
			$this->assertTrue( $value instanceof Link );
		} else {
			$this->fail( 'Unexpected instance of class ' . get_class( $value ) );
		}
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
					'prefix' . $expectedCleanedContents[0]->getString()
				);
		}
		$lastCharIndex = mb_strlen( $markedUpText ) - 1;
		if ( $markedUpText[$lastCharIndex] == '>' ) {
			$expectedCleanedContents[] = new CleanedText( 'suffix' );
		} else {
			$lastContentIndex = count( $expectedCleanedContents ) - 1;
			$expectedCleanedContents[$lastContentIndex] =
				new CleanedText(
					$expectedCleanedContents[$lastContentIndex]->getString()
					. 'suffix'
				);
		}
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( 'prefix' . $markedUpText . 'suffix' ),
			false
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
			$infix->setString( $adjacent->getString() . $infix->getString() );
		}
		$secondContents = $expectedCleanedContents;
		if ( $markedUpText[0] != '<' ) {
			$adjacent = array_shift( $secondContents );
			$infix->setString( $infix->getString() . $adjacent->getString() );
		}
		$this->assertContentsEqual(
			array_merge( $firstContents, [ $infix ], $secondContents ),
			$this->cleaner->cleanHtml( $markedUpText . 'infix' . $markedUpText ),
			false
		);
	}

	public function testCleanHtml_stringsWithoutMarkup_dontChange() {
		$markedUpText = 'A string without any fancy markup.';
		$expectedCleanedContent = [
			new CleanedText( 'A string without any fancy markup.', './text()' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContent,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_nestedTags_remove() {
		$markedUpText = '<i><b>Nested content</b></i>';
		$expectedCleanedContent = [
			new CleanedText( 'Nested content', './i/b/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_emptyElementTags_remove() {
		$markedUpText = '<br />';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_tagToRemove_remove() {
		$markedUpText = '<del>removed tag </del>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_tagToRemoveWithChild_remove() {
		$markedUpText = '<del><i>nested removed tag</i></del>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_tagToRemoveWithNestedChildren_remove() {
		$markedUpText = '<del><i><b>double nested removed tag</b></i></del>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_tagsToRemoveWithCertainClass_remove() {
		$markedUpText = '<sup class="reference">Remove this.</sup>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_tagsWithOneOfClasses_remove() {
		$markedUpText = '<div class="toc">Remove this.</div><div class="thumb">Also this.</div>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_tagsWithoutCertainClass_dontRemove() {
		$markedUpText =
			'<sup>I am not a reference.</sup><sup class="not-a-reference">Neither am I.</sup>';
		$expectedCleanedContent = [
			new CleanedText( 'I am not a reference.', './sup[1]/text()' ),
			new CleanedText( 'Neither am I.', './sup[2]/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_tagsWhoseCriteriaAreFalse_dontRemove() {
		$markedUpText = '<h2>Contents</h2>';
		$expectedCleanedContent = [
			new CleanedText( 'Contents', './h2/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_segmentBreakingTags_addSegmentBreaks() {
		$markedUpText =
			'prefix<q>content</q>suffix';
		$expectedCleanedContents = [
			new CleanedText( 'prefix', './text()[1]' ),
			new SegmentBreak(),
			new CleanedText( 'content', './q/text()' ),
			new SegmentBreak(),
			new CleanedText( 'suffix', './text()[2]' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_emptySegmentTags_addSegmentBreaks() {
		$markedUpText =
			'before<hr />after';
		$expectedCleanedContents = [
			new CleanedText( 'before', './text()[1]' ),
			new SegmentBreak(),
			new CleanedText( 'after', './text()[2]' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_nestedSegmentTags_addSegmentBreaksBefore() {
		$markedUpText =
			'<q>before<hr />after</q>';
		$expectedCleanedContents = [
			new CleanedText( 'before', './q/text()[1]' ),
			new SegmentBreak(),
			new CleanedText( 'after', './q/text()[2]' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_nestedSegmentBrakingTags_addSegmentBreaksAfter() {
		$markedUpText =
			'<q><hr />inside</q>after';
		$expectedCleanedContents = [
			new CleanedText( 'inside', './q/text()' ),
			new SegmentBreak(),
			new CleanedText( 'after', './text()' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_consecutiveSegmentBreakingTags_dontAddMultipleConsecutiveSegmentBreaks() {
		$markedUpText =
			'before<hr /><hr />after';
		$expectedCleanedContents = [
			new CleanedText( 'before', './text()[1]' ),
			new SegmentBreak(),
			new CleanedText( 'after', './text()[2]' ),
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_onlySegmentBreakingTag_dontAddSegmentBreaksAtStartOrEnd() {
		$markedUpText =
			'<q>content</q>';
		$expectedCleanedContents = [
			new CleanedText( 'content', './q/text()' ),
		];
		$this->assertContentsEqual(
			$expectedCleanedContents,
			$this->cleaner->cleanHtml( $markedUpText )
		);
	}

	public function testCleanHtml_tagsToRemoveWithMultipleClasses_remove() {
		$markedUpText =
			'<sup class="reference another-class">Remove this.</sup>';
		$this->assertTextCleaned( [], $markedUpText );
	}

	public function testCleanHtml_nestedTagsWithSomeToRemove_onlyRemoveTagsToRemove() {
		$markedUpText = '<i><b>not removed</b><del>removed</del></i>';
		$expectedCleanedContent = [
			new CleanedText( 'not removed', './i/b/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_utf8Characters_keep() {
		$markedUpText = '—';
		$expectedCleanedContent = [ new CleanedText( '—', './text()' ) ];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_htmlEntities_decode() {
		$markedUpText = '6&#160;p.m';
		$expectedCleanedContent = [ new CleanedText( '6 p.m', './text()' ) ];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_newlines_keep() {
		$markedUpText = "<i>Keep this newline\n</i>";
		$expectedCleanedContent = [
			new CleanedText( "Keep this newline\n", './i/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_emptyElementAfterEndTag_remove() {
		$markedUpText = '<i>content</i><br />';
		$expectedCleanedContent = [
			new CleanedText( 'content', './i/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_emptyElementTagInsideElement_remove() {
		$markedUpText = '<i>content<br /></i>';
		$expectedCleanedContent = [
			new CleanedText( 'content', './i/text()' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_comments_ignore() {
		$markedUpText = '<!-- A comment. -->';
		$expectedCleanedContent = [];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanHtml_tags_generatePaths() {
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

	public function testCleanHtml_nestedTagsOfSameType_generatePaths() {
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

	public function testCleanHtml_nodesOnSameLevel_generatePaths() {
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

	/**
	 * @dataProvider partOfContentProvider
	 */
	public function testCleanHtml_includePartsOfContent( string $html, array $cleaned ) {
		$this->createCleaner( true );
		$this->assertTextCleaned(
			$cleaned,
			$html
		);
	}

	public static function partOfContentProvider(): array {
		return [
			'Link' => [
				'text with <a>a link</a> in it',
				[
					new CleanedText( 'text with ', './text()[1]' ),
					new Link(),
					new CleanedText( 'a link', './a/text()' ),
					new CleanedText( ' in it', './text()[2]' )
				]
			],
			'Link with nested elements' => [
				'text with <a>a <b>link</b></a> in it',
				[
					new CleanedText( 'text with ', './text()[1]' ),
					new Link(),
					new CleanedText( 'a ', './a/text()' ),
					new CleanedText( 'link', './a/b/text()' ),
					new CleanedText( ' in it', './text()[2]' )
				]
			],
			'Link at the start of segment' => [
				'<a>a link</a> at the start',
				[
					new Link(),
					new CleanedText( 'a link', './a/text()' ),
					new CleanedText( ' at the start', './text()' )
				]
			]
		];
	}

	/**
	 * @dataProvider noPartOfContentProvider
	 */
	public function testCleanHtml_dontIncludePartOfContent_noExtraContent( string $html, array $cleaned ) {
		$this->assertTextCleaned(
			$cleaned,
			$html
		);
	}

	public static function noPartOfContentProvider(): array {
		return [
			'Link' => [
				'text with <a>a link</a> in it',
				[
					new CleanedText( 'text with ', './text()[1]' ),
					new CleanedText( 'a link', './a/text()' ),
					new CleanedText( ' in it', './text()[2]' )
				]
			],
			'Link with nested elements' => [
				'text with <a>a <b>link</b></a> in it',
				[
					new CleanedText( 'text with ', './text()[1]' ),
					new CleanedText( 'a ', './a/text()' ),
					new CleanedText( 'link', './a/b/text()' ),
					new CleanedText( ' in it', './text()[2]' )
				]
			]
		];
	}

}
