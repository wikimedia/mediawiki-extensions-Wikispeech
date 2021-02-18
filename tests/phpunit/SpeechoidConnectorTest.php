<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWikiIntegrationTestCase;

/**
 * @group medium
 * @covers \MediaWiki\Wikispeech\SpeechoidConnector
 */
class SpeechoidConnectorTest extends MediaWikiIntegrationTestCase {

	public function testListDefaultVoicePerLanguage_speechoidHasDefaultVoices_givesVoicesPerLanguage() {
		$defaultVoicesJson = '[
  {
    "default_textprocessor": "marytts_textproc_sv",
    "default_voice": "stts_sv_nst-hsmm",
    "lang": "sv"
  },
  {
    "default_textprocessor": "marytts_textproc_nb",
    "default_voice": "stts_no_nst-hsmm",
    "lang": "nb"
  },
  {
    "default_textprocessor": "marytts_textproc_en",
    "default_voice": "cmu-slt-hsmm",
    "lang": "en"
  },
  {
    "default_textprocessor": "marytts_textproc_ar",
    "default_voice": "ar-nah-hsmm",
    "lang": "ar"
  }
]';
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

	public function testFindLexiconByLanguage_speechoidHasTextProcessors_givesLexiconsPerLanguage() {
		$textProcessorsJson = '[
  {
    "components": [
      {
        "call": "marytts_preproc",
        "mapper": {
          "from": "sv-se_ws-sampa",
          "to": "sv-se_sampa_mary"
        },
        "module": "adapters.marytts_adapter"
      },
      {
        "call": "lexLookup",
        "lexicon": "sv_se_nst_lex:sv-se.nst",
        "module": "adapters.lexicon_client"
      }
    ],
    "config_file": "wikispeech_server/conf/voice_config_marytts.json",
    "default": true,
    "lang": "sv",
    "name": "marytts_textproc_sv"
  },
  {
    "components": [
      {
        "call": "marytts_preproc",
        "mapper": {
          "from": "nb-no_ws-sampa",
          "to": "nb-no_sampa_mary"
        },
        "module": "adapters.marytts_adapter"
      },
      {
        "call": "lexLookup",
        "lexicon": "no_nob_nst_lex:nb-no.nst",
        "module": "adapters.lexicon_client"
      }
    ],
    "config_file": "wikispeech_server/conf/voice_config_marytts.json",
    "default": true,
    "lang": "nb",
    "name": "marytts_textproc_nb"
  },
  {
    "components": [
      {
        "call": "marytts_preproc",
        "mapper": {
          "from": "en-us_ws-sampa",
          "to": "en-us_sampa_mary"
        },
        "module": "adapters.marytts_adapter"
      },
      {
        "call": "lexLookup",
        "lexicon": "en_am_cmu_lex:en-us.cmu",
        "module": "adapters.lexicon_client"
      }
    ],
    "config_file": "wikispeech_server/conf/voice_config_marytts.json",
    "default": true,
    "lang": "en",
    "name": "marytts_textproc_en"
  },
  {
    "components": [
      {
        "call": "marytts_preproc",
        "mapper": {
          "from": "ar_ws-sampa",
          "to": "ar_sampa_mary"
        },
        "module": "adapters.marytts_adapter"
      },
      {
        "call": "lexLookup",
        "lexicon": "ar_ar_tst_lex:ar-test",
        "module": "adapters.lexicon_client"
      }
    ],
    "config_file": "wikispeech_server/conf/voice_config_marytts.json",
    "default": true,
    "lang": "ar",
    "name": "marytts_textproc_ar"
  }
]';
		$deserializedTextProcessors = FormatJson::parse(
			$textProcessorsJson,
			FormatJson::FORCE_ASSOC
		)->getValue();

		$connectorMock = $this->createPartialMock(
			SpeechoidConnector::class,
			[ 'requestTextProcessors' ]
		);
		$connectorMock
			->expects( $this->exactly( 4 ) )
			->method( 'requestTextProcessors' )
			->willReturn( $deserializedTextProcessors );

		$this->assertSame( 'sv_se_nst_lex:sv-se.nst', $connectorMock->findLexiconByLanguage( 'sv' ) );
		$this->assertSame( 'sv_se_nst_lex:sv-se.nst', $connectorMock->findLexiconByLanguage( 'SV' ) );
		$this->assertSame( 'en_am_cmu_lex:en-us.cmu', $connectorMock->findLexiconByLanguage( 'en' ) );
		$this->assertNull( $connectorMock->findLexiconByLanguage( 'fr' ) );
	}

}
