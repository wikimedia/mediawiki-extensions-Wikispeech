<?php

/**
 * @file
 * @ingroup Extensions
 * @group Database
 * @group medium
 * @license GPL-2.0+
 */

require_once __DIR__ . '/../../includes/ApiWikispeech.php';
require_once 'Util.php';

define( 'TITLE', 'Test_Page' );

class ApiWikispeechTest extends ApiTestCase {
	public function addDBDataOnce() {
		$content = "Text ''italic'' '''bold'''";
		$this->addPage( TITLE, $content );
		$talkContent = "Talking about ''italic'' '''bold'''";
		$this->addPage( TITLE, $talkContent, NS_TALK );
	}

	private function addPage( $titleString, $content, $namespace=NS_MAIN ) {
		$title = Title::newFromText( $titleString, $namespace );
		$page = WikiPage::factory( $title );
		$status = $page->doEditContent(
			ContentHandler::makeContent(
				$content,
				$title,
				CONTENT_MODEL_WIKITEXT
			),
			''
		);
		if ( !$status->isOk() ) {
			$this->fail( "Failed to create $title: " . $status->getWikiText( false, false, 'en' ) );
		}
	}

	public function testCleanText() {
		$res = $this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'cleanedtext'
		] );
		$this->assertEquals(
			"Test Page\nText italic bold",
			$res[0]['wikispeech']['cleanedtext']
		);
	}

	public function testCleanTextHandleSegmentBreaks() {
		$title = 'Break';
		$content = 'Text with<br/ >break.';
		$this->addPage( $title, $content );
		$res = $this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => $title,
			'output' => 'cleanedtext',
			'segmentbreakingtags' => 'br'
		] );
		$this->assertEquals(
			"Break\nText with\nbreak.",
			$res[0]['wikispeech']['cleanedtext']
		);
	}

	public function testSegmentText() {
		$res = $this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => 'Talk:' . TITLE,
			'output' => 'segments'
		] );
		$this->assertEquals( 2, count( $res[0]['wikispeech']['segments'] ) );
		$this->assertEquals(
			[
				'startOffset' => 0,
				'endOffset' => 13,
				'content' => [
					[
						'string' => 'Talk:Test Page',
						'path' => '//h1[@id="firstHeading"]//text()'
					]
				]
			],
			$res[0]['wikispeech']['segments'][0]
		);
		$this->assertEquals(
			[
				'startOffset' => 0,
				'endOffset' => 3,
				'content' => [
					[
						'string' => 'Talking about ',
						'path' => './div/p/text()[1]'
					],
					[
						'string' => 'italic',
						'path' => './div/p/i/text()'
					],
					[
						'string' => ' ',
						'path' => './div/p/text()[2]'
					],
					[
						'string' => 'bold',
						'path' => './div/p/b/text()'
					]
				]
			],
			$res[0]['wikispeech']['segments'][1]
		);
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage "removetags" is not a valid JSON string.
	 */

	public function testRemoveTagsInvalidJsonThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'cleanedtext',
			'removetags' => 'not a JSON string'
		] );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage "removetags" is not of a valid format.
	 */

	public function testRemoveTagsNotAnObjectThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'cleanedtext',
			'removetags' => '"not a JSON object"'
		] );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage "removetags" is not of a valid format.
	 */

	public function testRemoveTagsInvalidValueThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'cleanedtext',
			'removetags' => '{"tag": null}'
		] );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage "removetags" is not of a valid format.
	 */

	public function testRemoveTagsJsonArrayThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'cleanedtext',
			'removetags' => '[true]'
		] );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage "removetags" is not of a valid format.
	 */

	public function testRemoveTagsInvalidRuleThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'cleanedtext',
			'removetags' => '{"tag": ["valid", false]}'
		] );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage There is no revision with ID
	 */

	public function testInvalidPageThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => 'Not a page',
			'output' => 'cleanedtext',
			'removetags' => '{}'
		] );
	}

	/**
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessage The parameter "output" may not be empty.
	 */

	public function testNoOutputFormatThrowsException() {
		$this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => '',
			'removetags' => '{}'
		] );
	}

	public function testSegmentTextHandleDisplayTitle() {
		$title = 'Title';
		$content = '{{DISPLAYTITLE:title}}Some content text.';
		$this->addPage( $title, $content );
		$res = $this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => $title,
			'output' => 'segments'
		] );
		$this->assertEquals( 2, count( $res[0]['wikispeech']['segments'] ) );
		$this->assertEquals(
			[
				'startOffset' => 0,
				'endOffset' => 4,
				'content' => [
					[
						'string' => 'title',
						'path' => '//h1[@id="firstHeading"]//text()'
					]
				]
			],
			$res[0]['wikispeech']['segments'][0]
		);
		$this->assertEquals(
			[
				'startOffset' => 0,
				'endOffset' => 17,
				'content' => [
					[
						'string' => 'Some content text.',
						'path' => './div/p/text()'
					]
				]
			],
			$res[0]['wikispeech']['segments'][1]
		);
	}
}
