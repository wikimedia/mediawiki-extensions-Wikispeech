<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Special;

use ConfigFactory;
use HashConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\Specials\SpecialEditLexicon;
use MediaWiki\Wikispeech\SpeechoidConnector;
use SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Wikispeech\Specials\SpecialEditLexicon
 */
class SpecialEditLexiconTest extends SpecialPageTestBase {

	/** @var HashConfig */
	private $config;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		$configFactory = new ConfigFactory();
		$this->config = new HashConfig();
		$configFactory->register(
			'wikispeech',
			function () {
				return $this->config;
			}
		);
		$this->languageNameUtils = $this->createStub( LanguageNameUtils::class );
		$this->lexiconHandler = $this->createMock( LexiconHandler::class );
		$this->speechoidConnector = $this->createStub( SpeechoidConnector::class );
		return new SpecialEditLexicon(
			$configFactory,
			$this->languageNameUtils,
			$this->lexiconHandler,
			$this->speechoidConnector
		);
	}

	public function testGetLanguageOptions_configHasVoices_giveLanguageOptions() {
		$page = $this->newSpecialPage();
		$this->config->set(
			'WikispeechVoices',
			[
				'ar' => [],
				'en' => []
			]
		);
		$wrappedPage = TestingAccessWrapper::newFromObject( $page );
		$map = [
			[ 'en', LanguageNameUtils::AUTONYMS, LanguageNameUtils::ALL, 'English' ],
			[ 'ar', LanguageNameUtils::AUTONYMS, LanguageNameUtils::ALL, 'العربية' ]
		];
		$this->languageNameUtils
			->method( 'getLanguageName' )
			->will( $this->returnValueMap( $map ) );

		$languageOptions = $wrappedPage->getLanguageOptions();

		$this->assertSame(
			[
				'ar - العربية' => 'ar',
				'en - English' => 'en'
			],
			$languageOptions
		);
	}

	public function testOnSubmit_formFilled_addEntryToLexicon() {
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'sampa transcription' ] ],
			'status' => [
				'name' => 'ok'
			]
		] );
		$this->lexiconHandler->expects( $this->once() )
			->method( 'createEntryItem' )
			->with(
				$this->equalTo( 'en' ),
				$this->equalTo( 'monkey' ),
				$this->equalTo( $item )
			);
		$data = [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription'
		];
		$this->speechoidConnector
			->method( 'ipaToSampa' )
			->with( 'ipa transcription' )
			->willReturn( 'sampa transcription' );

		$page->onSubmit( $data );
	}
}
