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
}
