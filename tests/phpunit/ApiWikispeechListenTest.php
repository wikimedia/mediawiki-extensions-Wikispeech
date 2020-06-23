<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @covers ApiWikispeechListen
 */
class ApiWikispeechListenTest extends ApiTestCase {
	protected function setUp() : void {
		parent::setUp();
		// Should be implementable using
		// $wgConfigRegistry['wikispeech'] see T255497
		$this->setMwGlobals( [
			// Make sure we don't send requests to an actual server.
			'wgWikispeechServerUrl' => '',
			'wgWikispeechVoices' => [
				'ar' => [ 'ar-voice' ],
				'en' => [
					'en-voice1',
					'en-voice2'
				],
				'sv' => [ 'sv-voice' ]
			],
			'wgWikispeechListenMaximumInputCharacters' => 60
		] );
	}

	public function testInvalidLanguage() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'"xx" is not a valid language. Should be one of: "ar", "en", "sv".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeechlisten',
			'input' => 'Utterance.',
			'lang' => 'xx'
		] );
	}

	public function testInvalidVoice() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'"invalid-voice" is not a valid voice. Should be one of: "en-voice1", "en-voice2".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeechlisten',
			'input' => 'Utterance.',
			'lang' => 'en',
			'voice' => 'invalid-voice'
		] );
	}

	/**
	 * @since 0.1.5
	 * @throws ApiUsageException
	 */
	public function testValidInputLength() {
		$api = TestingAccessWrapper::newFromObject( new ApiWikispeechListen(
			new ApiMain(), null
		) );
		$api->validateParameters( [
			'action' => 'wikispeechlisten',
			'input' => 'This is a short sentence with less than 60 characters.',
			'lang' => 'en',
			'voice' => ''
		] );
		// What we really want to do here is to assert that
		// ApiUsageException is not thrown.
		$this->assertTrue( true );
	}

	/**
	 * @since 0.1.5
	 * @throws ApiUsageException
	 */
	public function testInvalidInputLength() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			'Input text must not exceed 60 characters, but contained '
			. '64 characters.'
		);
		$api = TestingAccessWrapper::newFromObject( new ApiWikispeechListen(
			new ApiMain(), null
		) );
		$api->validateParameters( [
			'action' => 'wikispeechlisten',
			'input' => 'This is a tiny bit longer sentence with more than 60 characters.',
			'lang' => 'en',
			'voice' => ''
		] );
	}
}
