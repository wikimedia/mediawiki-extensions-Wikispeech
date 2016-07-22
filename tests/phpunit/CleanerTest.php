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
			Util::createStartTag( '<i>' ),
			'Element content',
			new CleanedEndTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	/**
	 * Run several tests to ensure that the cleaning functions neither do
	 * more nor less than they should. This includes: the tested string;
	 * the tested string preceded and followed by strings, that should not
	 * be altered; the tested string twice in a row, joined by a string
	 * that should not be altered; a string that contains no markup, that
	 * should not be altered.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContent The content that is the expected
	 *  output.
	 * @param string $markedUpText The string that contains the markup
	 *  that should be cleaned
	 */

	private function assertTextCleaned(
		$expectedCleanedContent,
		$markedUpText
	) {
		$this->assertEquals(
			$expectedCleanedContent,
			Cleaner::cleanHtml( $markedUpText )
		);
		$this->assertWithPrefixAndSuffix(
			$expectedCleanedContent,
			$markedUpText
		);
		$this->assertWithInfix(
			$expectedCleanedContent,
			$markedUpText
		);
	}

	/**
	 * Make sure that the correct content is given when preceded and
	 * followed by text.
	 *
	 * Pre- and suffix strings are concatenated to the first and last
	 * part respectively, of the expected content if they are
	 * strings. If they are CleanedTags, they are added as new parts.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContent The content that is the expected
	 *  output, excluding pre- and suffix.
	 * @param string $markedUpText The string that contains the markup
	 *  that should be cleaned
	 */

	private function assertWithPrefixAndSuffix(
		$expectedCleanedContent,
		$markedUpText
	) {
		if ( is_string( $expectedCleanedContent[0] ) ) {
			$expectedCleanedContent[0] = 'prefix' . $expectedCleanedContent[0];
		} else {
			array_unshift( $expectedCleanedContent, 'prefix' );
		}
		$lastIndex = count( $expectedCleanedContent ) - 1;
		if ( is_string( $expectedCleanedContent[$lastIndex] ) ) {
			$expectedCleanedContent[$lastIndex] .= 'suffix';
		} else {
			array_push( $expectedCleanedContent, 'suffix' );
		}
		$this->assertEquals(
			$expectedCleanedContent,
			Cleaner::cleanHtml( 'prefix' . $markedUpText . 'suffix' )
		);
	}

	/**
	 * Make sure that the correct content is given when the marked up
	 * text is repeated, with text in between.
	 *
	 * If the first instance of the expected content end with a
	 * string, the infix is added after that. If the second instance
	 * starts with a string, the infix is added before that. If both
	 * cases occur at the same time, the string between the instances
	 * will consist of the last string of first instance, infix and
	 * first string of second instance.
	 *
	 * @since 0.0.1
	 * @param array $expectedCleanedContent The content that will be
	 *  repeated to create the expected output.
	 * @param string $markedUpText The string that contains the markup
	 *  that should be cleaned
	 */

	private function assertWithInfix(
		$expectedCleanedContent,
		$markedUpText
	) {
		$infix = 'infix';
		$firstContent = $expectedCleanedContent;
		if ( is_string( $firstContent[0] ) ) {
			$adjacent = array_pop( $firstContent );
			$infix = $adjacent . $infix;
		}
		$secondContent = $expectedCleanedContent;
		$lastIndex = count( $secondContent ) - 1;
		if ( is_string( $expectedCleanedContent[$lastIndex] ) ) {
			$adjacent = array_shift( $secondContent );
			$infix .= $adjacent;
		}
		$this->assertEquals(
			array_merge( $firstContent, [ $infix ], $secondContent ),
			Cleaner::cleanHtml( $markedUpText . 'infix' . $markedUpText )
		);
	}

	public function testDontAlterStringWithoutMarkup() {
		$markedUpText = 'A string without any fancy markup.';
		$expectedCleanedContent = [ 'A string without any fancy markup.' ];
		$this->assertEquals(
			$expectedCleanedContent,
			Cleaner::cleanHtml( $markedUpText )
		);
	}

	public function testCleanNestedTags() {
		$markedUpText = '<i><b>Nested content</b></i>';
		$expectedCleanedContent = [
			Util::createStartTag( '<i>' ),
			Util::createStartTag( '<b>' ),
			'Nested content',
			new CleanedEndTag( '</b>' ),
			new CleanedEndTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanEmptyElementTags() {
		$markedUpText = '<br />';
		$expectedCleanedContent = [
			new CleanedEmptyElementTag( '<br />' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testRemoveTags() {
		$markedUpText = '<del>removed tag </del>';
		$expectedCleanedContent = [
			Util::createStartTag( '<del>', 'removed tag ' ),
			new CleanedEndTag( '</del>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontAddCleanedTagsForTagsUnderRemovedTags() {
		$markedUpText = '<del><i>nested removed tag</i></del>';
		$expectedCleanedContent = [
			Util::createStartTag( '<del>', '<i>nested removed tag</i>' ),
			new CleanedEndTag( '</del>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testRemoveDoubleNestedTags() {
		$markedUpText = '<del><i><b>double nested removed tag</b></i></del>';
		$expectedCleanedContent = [
			Util::createStartTag(
				'<del>',
				'<i><b>double nested removed tag</i></u>'
			),
			new CleanedEndTag( '</del>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testRemoveTagsWithCertainClass() {
		$markedUpText = '<sup class="reference">Remove this.</sup>';
		$expectedCleanedContent = [
			Util::createStartTag(
				'<sup class="reference">',
				'Remove this.'
			),
			new CleanedEndTag( '</sup>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontRemoveTagsWithoutCertainClass() {
		$markedUpText =
			'<sup>I am not a reference.</sup><sup class="not-a-reference">Neither am I.</sup>';
		$expectedCleanedContent = [
			Util::createStartTag( '<sup>' ),
			'I am not a reference.',
			new CleanedEndTag( '</sup>' ),
			Util::createStartTag( '<sup class="not-a-reference">' ),
			'Neither am I.',
			new CleanedEndTag( '</sup>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testDontRemoveTagsWhichCriteriaAreFalse() {
		$markedUpText = '<h2>Contents</h2>';
		$expectedCleanedContent = [
			Util::createStartTag( '<h2>' ),
			'Contents',
			new CleanedEndTag( '</h2>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleMultipleClasses() {
		$markedUpText =
			'<sup class="reference another-class">Remove this.</sup>';
		$expectedCleanedContent = [
			Util::createStartTag(
				'<sup class="reference another-class">',
				'Remove this.'
			),
			new CleanedEndTag( '</sup>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testCleanNestedTagsWhereSomeAreRemovedAndSomeAreKept() {
		$markedUpText = '<i><b>not removed</b><del>removed</del></i>';
		$expectedCleanedContent = [
			Util::createStartTag( '<i>' ),
			Util::createStartTag( '<b>' ),
			'not removed',
			new CleanedEndTag( '</b>' ),
			Util::createStartTag( '<del>', 'removed' ),
			new CleanedEndTag( '</del>' ),
			new CleanedEndTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleUtf8Characters() {
		$markedUpText = '—';
		$expectedCleanedContent = [ '—' ];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleHtmlEntities() {
		$markedUpText = '6&#160;p.m';
		$expectedCleanedContent = [ '6 p.m' ];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleNewlines() {
		$markedUpText = "<i>Keep this newline\n</i>";
		$expectedCleanedContent = [
			Util::createStartTag( '<i>' ),
			"Keep this newline\n",
			new CleanedEndTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleEndTagFollowedByEmptyElementTag() {
		$markedUpText = '<i>content</i><br />';
		$expectedCleanedContent = [
			Util::createStartTag( '<i>' ),
			'content',
			new CleanedEndTag( '</i>' ),
			new CleanedEmptyElementTag( '<br />' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testHandleEmptyElementTagInsideElement() {
		$markedUpText = '<i>content<br /></i>';
		$expectedCleanedContent = [
			Util::createStartTag( '<i>' ),
			'content',
			new CleanedEmptyElementTag( '<br />' ),
			new CleanedEndTag( '</i>' )
		];
		$this->assertTextCleaned( $expectedCleanedContent, $markedUpText );
	}

	public function testGetTags() {
		$textWithTags = '<i>content</i>';
		$expectedTags = [ [
			[ 'string' => '<i>', 'position' => 0 ],
			[ 'string' => '</i>', 'position' => 10 ]
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
				[ 'string' => '<i>', 'position' => 0 ],
				[ 'string' => '</i>', 'position' => 24 ]
			],
			[
				[ 'string' => '<b>', 'position' => 10 ],
				[ 'string' => '</b>', 'position' => 20 ]
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
				[ 'string' => '<i id="1">', 'position' => 0 ],
				[ 'string' => '</i>', 'position' => 38 ]
			],
			[
				[ 'string' => '<i id="2">', 'position' => 17 ],
				[ 'string' => '</i>', 'position' => 34 ]
			]
		];
		$this->assertEquals(
			$expectedTags,
			Util::call( 'Cleaner', 'getTags', $textWithTags )
		);
	}
}
