<?php

namespace MediaWiki\Wikispeech\Tests;

use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWikiIntegrationTestCase;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @group medium
 * @covers \MediaWiki\Wikispeech\SpeechoidConnector
 */
class SpeechoidConnectorTest extends MediaWikiIntegrationTestCase {

	public function testListDefaultVoicePerLanguage_mockedSpeechoidResponse_parsedCorrectly() {
		$defaultVoicesJson = '[' .
			'{"default_textprocessor": "marytts_textproc_sv",' .
			' "default_voice": "stts_sv_nst-hsmm", "lang": "sv"}, ' .
			'{"default_textprocessor": "marytts_textproc_nb",' .
			' "default_voice": "stts_no_nst-hsmm", "lang": "nb"}, ' .
			'{"default_textprocessor": "marytts_textproc_en",' .
			' "default_voice": "cmu-slt-hsmm", "lang": "en"}, ' .
			'{"default_textprocessor": "marytts_textproc_ar",' .
			' "default_voice": "ar-nah-hsmm", "lang": "ar"}' .
			']';
		$connectorMock = $this->createPartialMock(
			SpeechoidConnector::class,
			[ 'requestDefaultVoices' ]
		);
		$connectorMock
			->expects( $this->once() )
			->method( 'requestDefaultVoices' )
			->willReturn( $defaultVoicesJson );

		$defaultVoicePerLanguage = $connectorMock->listDefaultVoicePerLanguage();

		$this->assertCount( 4, $defaultVoicePerLanguage );
		$this->assertEquals( 'stts_sv_nst-hsmm', $defaultVoicePerLanguage['sv'] );
		$this->assertEquals( 'stts_no_nst-hsmm', $defaultVoicePerLanguage['nb'] );
		$this->assertEquals( 'cmu-slt-hsmm', $defaultVoicePerLanguage['en'] );
		$this->assertEquals( 'ar-nah-hsmm', $defaultVoicePerLanguage['ar'] );
	}

}
