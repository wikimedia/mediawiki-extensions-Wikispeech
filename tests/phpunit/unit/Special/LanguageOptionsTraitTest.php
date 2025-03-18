<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Special;

use MediaWiki\Config\HashConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Wikispeech\Specials\LanguageOptionsTrait;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers MediaWiki\Wikispeech\Specials\LanguageOptionsTrait
 */
class LanguageOptionsTraitTest extends MediaWikiUnitTestCase {

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	public function testGetLanguageOptions_configHasVoices_giveLanguageOptions() {
		$trait = TestingAccessWrapper::newFromObject( $this->getMockForTrait( LanguageOptionsTrait::class ) );
		$config = new HashConfig( [
			'WikispeechVoices' => [
				'ar' => [],
				'en' => []
			]
		] );
		$trait->method( 'getConfig' )->willReturn( $config );
		$trait->languageNameUtils = $this->createStub( LanguageNameUtils::class );
		$map = [
			[ 'en', LanguageNameUtils::AUTONYMS, LanguageNameUtils::ALL, 'English' ],
			[ 'ar', LanguageNameUtils::AUTONYMS, LanguageNameUtils::ALL, 'العربية' ]
		];
		$trait->languageNameUtils
			->method( 'getLanguageName' )
			->willReturnMap( $map );

		$languageOptions = $trait->getLanguageOptions();

		$this->assertSame(
			[
				'ar - العربية' => 'ar',
				'en - English' => 'en'
			],
			$languageOptions
		);
	}
}
