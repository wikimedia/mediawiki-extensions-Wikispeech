<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @group medium
 * @covers ApiWikispeechListen
 */
class ApiWikispeechListenTest extends ApiTestCase {
	protected function setUp() : void {
		parent::setUp();
		$voices = [
			"ar" => [ "ar-nah-hsmm" ],
			"en" => [
				"dfki-spike-hsmm",
				"cmu-slt-flite"
			],
			"sv" => [ "stts_sv_nst-hsmm" ]
		];
		$wgConfigRegistry['wikispeech'] = function () {
			return new HashConfig( [
				'wgWikispeechVoices' => $voices
			] );
		};
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
			'"invalid-voice" is not a valid voice. Should be one of: "dfki-spike-hsmm", "cmu-slt-flite".'
		);
		$this->doApiRequest( [
			'action' => 'wikispeechlisten',
			'input' => 'Utterance.',
			'lang' => 'en',
			'voice' => 'invalid-voice'
		] );
	}
}
