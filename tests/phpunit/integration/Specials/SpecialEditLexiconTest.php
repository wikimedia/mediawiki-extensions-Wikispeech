<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Special;

use ConfigFactory;
use HashConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Wikispeech\Specials\SpecialEditLexicon;
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
		return new SpecialEditLexicon( $configFactory, $this->languageNameUtils );
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
}
