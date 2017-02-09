<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once __DIR__ . '/../../includes/Cleaner.php';
require_once 'Util.php';

class CleanerTest extends MediaWikiTestCase {
	protected function setUp() {
		parent::setUp();
		global $wgWikispeechRemoveTags;
		$wgWikispeechRemoveTags = [
			'table' => true,
			'sup' => [ 'class' => 'reference' ],
			'editsection' => true,
			'h2' => false,
			'del' => true
		];
	}

	public function testCleanTags() {
		$markedUpText = '<i>Element content</i>';
		$expectedCleanedContent = [
			new CleanedTag( '<i>' ),
			new CleanedText( 'Element content' ),
			new CleanedTag( '</i>' )
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
			Cleaner::cleanHtml( $markedUpText )
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
	 * part respectively, of the expected content if they are
	 * `Text`s. If they are `CleanedTag`s, they are added as new
	 * parts.
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
		if ( is_a( $expectedCleanedContents[0], 'CleanedText' ) ) {
			$expectedCleanedContents[0] =
				new CleanedText( 'prefix' . $expectedCleanedContents[0]->string );
		} else {
			array_unshift( $expectedCleanedContents, new CleanedText( 'prefix' ) );
		}
		$lastIndex = count( $expectedCleanedContents ) - 1;
		if ( is_a( $expectedCleanedContents[$lastIndex], 'CleanedText' ) ) {
			$expectedCleanedContents[$lastIndex] =
				new CleanedText(
					$expectedCleanedContents[$lastIndex]->string . 'suffix'
				);
		} else {
			array_push( $expectedCleanedContents, new CleanedText( 'suffix' ) );
		}
		$this->assertContentsEqual(
			$expectedCleanedContents,
			Cleaner::cleanHtml( 'prefix' . $markedUpText . 'suffix' )
		);
	}

	/**
	 * Assert correct output when input is repeated and separated by string.
	 *
	 * If the first instance of the expected content ends with a
	 * `Text`, the infix is added after that. If the second instance
	 * starts with a `Text`, the infix is added before that. If both
	 * cases occur at the same time, the `Text` between the instances
	 * will consist of the last `Text` of first instance, infix and
	 * first `Text` of second instance.
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
		$lastIndex = count( $firstContents ) - 1;
		if ( is_a( $firstContents[$lastIndex], 'CleanedText' ) ) {
			$adjacent = array_pop( $firstContents );
			$infix->string = $adjacent->string . $infix->string;
		}
		$secondContents = $expectedCleanedContents;
		if ( is_a( $secondContents[0], 'CleanedText' ) ) {
			$adjacent = array_shift( $secondContents );
			$infix->string .= $adjacent->string;
		}
		$this->assertContentsEqual(
			array_merge( $firstContents, [ $infix ], $secondContents ),
			Cleaner::cleanHtml( $markedUpText . 'infix' . $markedUpText )
		);
	}

	public function testDontAlterStringsWithoutMarkup() {
		$markedUpText = 'A string without any fancy markup.';
		$expectedCleanedContent = [
			new CleanedText( 'A string without any fancy markup.' )
		];
		$this->assertContentsEqual(
			$expectedCleanedContent,
			Cleaner::cleanHtml( $markedUpText )
		);
	}

	public function testCleanNestedTags() {
		$markedUpText = '<i><b>Nested content</b></i>';
		$expectedCleanedContent = [
			new CleanedTag( '<i>' ),
			new CleanedTag( '<b>' ),
			new CleanedText( 'Nested content' ),
			new CleanedTag( '</b>' ),
			new CleanedTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanEmptyElementTags() {
		$markedUpText = '<br />';
		$expectedCleanedContent = [
			new CleanedTag( '<br />' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testRemoveTags() {
		$markedUpText = '<del>removed tag </del>';
		$expectedCleanedContent = [
			new CleanedTag( '<del>' ),
			new CleanedTag( '</del>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontAddCleanedTagsForTagsUnderRemovedTags() {
		$markedUpText = '<del><i>nested removed tag</i></del>';
		$expectedCleanedContent = [
			new CleanedTag( '<del>' ),
			new CleanedTag( '</del>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testRemoveDoubleNestedTags() {
		$markedUpText = '<del><i><b>double nested removed tag</b></i></del>';
		$expectedCleanedContent = [
			new CleanedTag( '<del>' ),
			new CleanedTag( '</del>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testRemoveTagsWithCertainClass() {
		$markedUpText = '<sup class="reference">Remove this.</sup>';
		$expectedCleanedContent = [
			new CleanedTag( '<sup class="reference">' ),
			new CleanedTag( '</sup>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontRemoveTagsWithoutCertainClass() {
		$markedUpText =
			'<sup>I am not a reference.</sup><sup class="not-a-reference">Neither am I.</sup>';
		$expectedCleanedContent = [
			new CleanedTag( '<sup>' ),
			new CleanedText( 'I am not a reference.' ),
			new CleanedTag( '</sup>' ),
			new CleanedTag( '<sup class="not-a-reference">' ),
			new CleanedText( 'Neither am I.' ),
			new CleanedTag( '</sup>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontRemoveTagsWhoseCriteriaAreFalse() {
		$markedUpText = '<h2>Contents</h2>';
		$expectedCleanedContent = [
			new CleanedTag( '<h2>' ),
			new CleanedText( 'Contents' ),
			new CleanedTag( '</h2>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleMultipleClasses() {
		$markedUpText =
			'<sup class="reference another-class">Remove this.</sup>';
		$expectedCleanedContent = [
			new CleanedTag( '<sup class="reference another-class">' ),
			new CleanedTag( '</sup>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanNestedTagsWhereSomeAreRemovedAndSomeAreKept() {
		$markedUpText = '<i><b>not removed</b><del>removed</del></i>';
		$expectedCleanedContent = [
			new CleanedTag( '<i>' ),
			new CleanedTag( '<b>' ),
			new CleanedText( 'not removed' ),
			new CleanedTag( '</b>' ),
			new CleanedTag( '<del>' ),
			new CleanedTag( '</del>' ),
			new CleanedTag( '</i>' )
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
			new CleanedTag( '<i>' ),
			new CleanedText( "Keep this newline\n" ),
			new CleanedTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleEndTagFollowedByEmptyElementTag() {
		$markedUpText = '<i>content</i><br />';
		$expectedCleanedContent = [
			new CleanedTag( '<i>' ),
			new CleanedText( 'content' ),
			new CleanedTag( '</i>' ),
			new CleanedTag( '<br />' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleEmptyElementTagInsideElement() {
		$markedUpText = '<i>content<br /></i>';
		$expectedCleanedContent = [
			new CleanedTag( '<i>' ),
			new CleanedText( 'content' ),
			new CleanedTag( '<br />' ),
			new CleanedTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testGeneratePaths() {
		$markedUpText = '<i>level one<br /><b>level two</b></i>level zero';
		$expectedCleanedContent = [
			new CleanedTag( '<i>' ),
			new CleanedText( 'level one', [ 0, 0 ] ),
			new CleanedTag( '<br />' ),
			new CleanedTag( '<b>' ),
			new CleanedText( 'level two', [ 0, 2, 0 ] ),
			new CleanedTag( '</b>' ),
			new CleanedTag( '</i>' ),
			new CleanedText( 'level zero', [ 1 ] )
		];
		$this->assertEquals(
			$expectedCleanedContent,
			Cleaner::cleanHtml( $markedUpText )
		);
	}

	public function testGeneratePathsNestedOfSameType() {
		$markedUpText = '<i id="1">one<i id="2">two</i></i>';
		$expectedCleanedContent = [
			new CleanedTag( '<i id="1">' ),
			new CleanedText( 'one', [ 0, 0 ] ),
			new CleanedTag( '<i id="2">' ),
			new CleanedText( 'two', [ 0, 1, 0 ] ),
			new CleanedTag( '</i>' ),
			new CleanedTag( '</i>' )
		];
		$this->assertEquals(
			$expectedCleanedContent,
			Cleaner::cleanHtml( $markedUpText )
		);
	}

	public function testGetTags() {
		$textWithTags = '<i>content</i>';
		$expectedTags = [ [
			'<i>',
			'</i>'
		] ];
		$this->assertEquals(
			$expectedTags,
			Util::call( 'Cleaner', 'getTags', $textWithTags )
		);
	}

	public function testGetTagsEmptyElementTag() {
		$textWithTags = '<br />';
		$expectedTags = [ '<br />' ];
		$this->assertEquals(
			$expectedTags,
			Util::call( 'Cleaner', 'getTags', $textWithTags )
		);
	}

	public function testGetTagsEmptyElementTagWithoutSpace() {
		$textWithTags = '<br/>';
		$expectedTags = [ '<br/>' ];
		$this->assertEquals(
			$expectedTags,
			Util::call( 'Cleaner', 'getTags', $textWithTags )
		);
	}

	public function testGetTagsNestedTags() {
		$textWithTags = '<i>content<b>content</b></i>';
		$expectedTags = [
			[
				'<i>',
				'</i>'
			],
			[
				'<b>',
				'</b>'
			]
		];
		$this->assertEquals(
			$expectedTags,
			Util::call( 'Cleaner', 'getTags', $textWithTags )
		);
	}

	public function testGetTagsNestedTagsOfSameType() {
		$textWithTags = '<i id="1">content<i id="2">content</i></i>';
		$expectedTags = [
			[
				'<i id="1">',
				'</i>'
			],
			[
				'<i id="2">',
				'</i>'
			]
		];
		$this->assertEquals(
			$expectedTags,
			Util::call( 'Cleaner', 'getTags', $textWithTags )
		);
	}
}
