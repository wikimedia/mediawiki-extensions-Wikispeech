<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once __DIR__ . '/../../includes/Cleaner.php';

class CleanerTest extends MediaWikiTestCase {
	public function testCleanTags() {
		$markedUpText = '<i>Blonde on Blonde</i>';
		$expectedText = 'Blonde on Blonde';
		$this->assertTextCleaned( $expectedText, $markedUpText );
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
	 * @param string $expectedText The string that is the expected output
	 * from the function named by $function.
	 * @param string $markedUpText The string that contains the markup
	 * that should be cleaned. Used as input to the function named by
	 * $function.
	 */

	private function assertTextCleaned( $expectedText, $markedUpText ) {
		$this->assertEquals(
			$expectedText,
			Cleaner::cleanHtml( $markedUpText )
		);
		$this->assertEquals( 'prefix' . $expectedText . 'suffix',
			Cleaner::cleanHtml( 'prefix' . $markedUpText . 'suffix' ) );
		$this->assertEquals( $expectedText . 'infix' . $expectedText,
			Cleaner::cleanHtml( $markedUpText . 'infix' . $markedUpText ) );
		$this->assertEquals( 'A string without any fancy markup.',
			Cleaner::cleanHtml( 'A string without any fancy markup.' ) );
	}

	public function testCleanNestedTags() {
		$markedUpText = '<i><b>Blonde on Blonde</b></i>';
		$expectedText = 'Blonde on Blonde';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testCleanEmptyTags() {
		$markedUpText = '<img alt="" src="image.png" />';
		$expectedText = '';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testRemoveTagsAltogether() {
		// @codingStandardsIgnoreStart
		$markedUpText = '<table>Remove this table, please.</table>';
		// @codingStandardsIgnoreEnd
		$expectedText = '';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testRemoveTagsWithCertainClass() {
		$markedUpText = '<sup class="reference"><a>[1]</a>Also remove this.</sup>';
		$expectedText = '';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testDontRemoveTagsWithoutCertainClass() {
		// @codingStandardsIgnoreStart
		$markedUpText = '<sup>I am not a reference.</sup><sup class="not-a-reference">Neither am I.</sup>';
		// @codingStandardsIgnoreEnd
		$expectedText = 'I am not a reference.Neither am I.';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testHandleMultipleClasses() {
		// @codingStandardsIgnoreStart
		$markedUpText = '<sup class="reference another-class"><a href="#cite_note-Grayp5-1">[1]</a>Also remove this.</sup>';
		// @codingStandardsIgnoreEnd
		$expectedText = '';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testCleanNestedTagsWhereSomeAreRemovedAndSomeAreKept() {
		// @codingStandardsIgnoreStart
		$markedUpText = '<h2><span class="mw-headline" id="Recording_sessions">Recording sessions</span><mw:editsection page="Test Page" section="1">Recording sessions</mw:editsection></h2>';
		// @codingStandardsIgnoreEnd
		$expectedText = 'Recording sessions';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testHandleUtf8Characters() {
		$markedUpText = '—';
		$expectedText = '—';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}

	public function testHandleHtmlEntities() {
		$markedUpText = '6&#160;p.m';
		$expectedText = '6 p.m';
		$this->assertTextCleaned( $expectedText, $markedUpText );
	}
}
