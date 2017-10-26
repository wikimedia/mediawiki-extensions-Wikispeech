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

define( 'TITLE', 'Talk:Page' );

class ApiWikispeechTest extends ApiTestCase {
	public function addDBDataOnce() {
		$content = "Text ''italic'' '''bold'''";
		$this->addPage( TITLE, $content );
	}

	private function addPage( $titleString, $content ) {
		$title = Title::newFromText( $titleString );
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
			'Text italic bold',
			$res[0]['wikispeech']['cleanedtext']
		);
	}

	public function testCleanTextHandleSegmentBreaks() {
		$title = 'Talk:Break';
		$content = 'Text with<br/ >break.';
		$this->addPage( $title, $content );
		$res = $this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => $title,
			'output' => 'cleanedtext',
			'segmentbreakingtags' => 'br'
		] );
		$this->assertEquals(
			"Text with\nbreak.",
			$res[0]['wikispeech']['cleanedtext']
		);
	}

	public function testSegmentText() {
		$res = $this->doApiRequest( [
			'action' => 'wikispeech',
			'page' => TITLE,
			'output' => 'segments'
		] );
		$this->assertEquals( 1, count( $res[0]['wikispeech']['segments'] ) );
		$this->assertEquals(
			[
				'startOffset' => 0,
				'endOffset' => 3,
				'content' => [
					[
						'string' => 'Text ',
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
			$res[0]['wikispeech']['segments'][0]
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
}
