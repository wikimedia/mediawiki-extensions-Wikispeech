<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ApiTestCase;
use ApiUsageException;

define( 'TITLE', 'Test_Page' );

/**
 * @group Database
 * @group medium
 * @covers \MediaWiki\Wikispeech\Api\ApiWikispeechSegment
 */
class ApiWikispeechSegmentTest extends ApiTestCase {

	protected function tearDown(): void {
		WikiPageTestUtil::removeCreatedPages();
		parent::tearDown();
	}

	protected function setUp(): void {
		$content = "Text ''italic'' '''bold'''";
		WikiPageTestUtil::addPage( TITLE, $content );
		$talkContent = "Talking about ''italic'' '''bold'''";
		WikiPageTestUtil::addPage( TITLE, $talkContent, NS_TALK );
		parent::setUp();
	}

	public function testSegmentText() {
		$res = $this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => 'Talk:' . TITLE
		] );
		$this->assertCount( 2, $res[0]['wikispeech-segment']['segments'] );
		$this->assertEquals(
			[
				'startOffset' => 0,
				'endOffset' => 13,
				'content' => [
					[
						'string' => 'Talk:Test Page',
						'path' => '//h1/text()'
					]
				],
				'hash' => '50c0083861b4c8bc5e6c1402bbb18ab093cfdf930aa8f5ef9297764e01137a26'
			],
			$res[0]['wikispeech-segment']['segments'][0]
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
				],
				'hash' => 'beeb949bc6c4193ad4903fcf93090dbd4a759b82b5d923cb0136421eb7eee3ca'
			],
			$res[0]['wikispeech-segment']['segments'][1]
		);
	}

	public function testRemoveTagsInvalidJsonThrowsException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not a valid JSON string.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => 'not a JSON string'
		] );
	}

	public function testRemoveTagsNotAnObjectThrowsException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '"not a JSON object"'
		] );
	}

	public function testRemoveTagsInvalidValueThrowsException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '{"tag": null}'
		] );
	}

	public function testRemoveTagsJsonArrayThrowsException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '[true]'
		] );
	}

	public function testRemoveTagsInvalidRuleThrowsException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '{"tag": ["valid", false]}'
		] );
	}

	public function testInvalidPageThrowsException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			"The page you specified doesn't exist."
		);
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => 'Not a page',
			'removetags' => '{}'
		] );
	}

	public function testRequest_consumerUrlGivenNotInProducerMode_throwsException() {
		$this->setMwGlobals( 'wgWikispeechProducerMode', false );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Requests from remote wikis are not allowed.' );

		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => 'Page',
			'consumer-url' => 'https://consumer.url'
		] );
	}

}
