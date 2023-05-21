<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Utterance;

use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Asserts functionally of paths in file backend,
 * which avoids overloading directories with files.
 *
 * @covers \MediaWiki\Wikispeech\Utterance\UtteranceStore
 */
class UtteranceStoreUrlPathFactoryTest extends MediaWikiUnitTestCase {

	/** @var TestingAccessWrapper|UtteranceStore */
	private $utteranceStore;

	protected function setUp(): void {
		parent::setUp();
		$this->utteranceStore = TestingAccessWrapper::newFromObject( new class extends UtteranceStore {
			public function __construct() {
				// override constructor, we don't want to validate configuration for this test.
			}
		} );
	}

	public static function dataProvider(): array {
		return [
			'1 character integer' => [ '/', 1 ],
			'2 character integer' => [ '/', 12 ],
			'3 character integer' => [ '/', 123 ],
			'4 character integer' => [ '/1/', 1234 ],
			'5 character integer' => [ '/1/2/', 12345 ],
			'6 character integer' => [ '/1/2/3/', 123456 ],
			'7 character integer' => [ '/1/2/3/4/', 1234567 ],
		];
	}

	/**
	 * @dataProvider dataProvider
	 * @param string $path
	 * @param int $utteranceId
	 */
	public function testUrlPathFactory( string $path, int $utteranceId ) {
		$this->assertEquals(
			$path,
			$this->utteranceStore->urlPathFactory( $utteranceId )
		);
	}

}
