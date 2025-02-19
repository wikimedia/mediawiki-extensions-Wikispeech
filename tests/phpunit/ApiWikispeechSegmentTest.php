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

	public function testApiRequest_segmentText_returnSegments() {
		$res = $this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => 'Talk:' . TITLE
		] );
		$this->assertCount( 4, $res[0]['wikispeech-segment']['segments'] );
		$this->assertEquals(
			[
				[
					'startOffset' => 0,
					'endOffset' => 3,
					'content' => [
						[
							'string' => 'Talk',
							'path' => '//h1/span[1]/text()'
						]
					],
					'hash' => '1e6560b6663ac62cafb6778e71dcc66c26a3ccd3960e3956f7194a620fd2d174'
				],
				[
					'startOffset' => 0,
					'endOffset' => 0,
					'content' => [
						[
							'string' => ':',
							'path' => '//h1/span[2]/text()'
						]
					],
					'hash' => 'f3743a0a18e53d13922cc21a70d783d875a12560c22ff8d28bda5f5ca9fe05c3'
				],
				[
					'startOffset' => 0,
					'endOffset' => 8,
					'content' => [
						[
							'string' => 'Test Page',
							'path' => '//h1/span[3]/text()'
						]
					],
					'hash' => 'f35b4a5363b82d289322b5a6e8d22b492dbd4c8b564e28c49f4dae3839892f63'
				],
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
				]
			],
			$res[0]['wikispeech-segment']['segments']
		);
	}

	public function testApiRequest_removeTagsInvalidJson_throwException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not a valid JSON string.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => 'not a JSON string'
		] );
	}

	public function testApiRequest_removeTagsNotAnObject_throwException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '"not a JSON object"'
		] );
	}

	public function testApiRequest_removeTagsInvalidValue_throwException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '{"tag": null}'
		] );
	}

	public function testApiRequest_removeTagsJsonArray_throwException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '[true]'
		] );
	}

	public function testApiRequest_removeTagsInvalidRule_throwException() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( '"removetags" is not of a valid format.' );
		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => TITLE,
			'removetags' => '{"tag": ["valid", false]}'
		] );
	}

	public function testApiRequest_invalidPage_throwException() {
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

	public function testApiRequest_consumerUrlGivenNotInProducerMode_throwException() {
		$this->overrideConfigValue( 'WikispeechProducerMode', false );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Requests from remote wikis are not allowed.' );

		$this->doApiRequest( [
			'action' => 'wikispeech-segment',
			'page' => 'Page',
			'consumer-url' => 'https://consumer.url'
		] );
	}

}
