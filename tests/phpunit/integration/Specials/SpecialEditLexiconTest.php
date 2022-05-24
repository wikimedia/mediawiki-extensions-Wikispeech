<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Special;

use ConfigFactory;
use HashConfig;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Wikispeech\Lexicon\LexiconEntry;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconStorage;
use MediaWiki\Wikispeech\Specials\SpecialEditLexicon;
use MediaWiki\Wikispeech\SpeechoidConnector;
use SpecialPageTestBase;
use Status;
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
		$this->lexiconStorage = $this->createMock( LexiconStorage::class );
		$this->speechoidConnector = $this->createStub( SpeechoidConnector::class );
		return new SpecialEditLexicon(
			$configFactory,
			$this->languageNameUtils,
			$this->lexiconStorage,
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
			->willReturnMap( $map );

		$languageOptions = $wrappedPage->getLanguageOptions();

		$this->assertSame(
			[
				'ar - العربية' => 'ar',
				'en - English' => 'en'
			],
			$languageOptions
		);
	}

	public function testSubmit_formNotFilled_dontAddEntryToLexicon() {
		$page = $this->newSpecialPage();
		$this->lexiconStorage->expects( $this->never() )
			->method( 'createEntryItem' );
		$page->submit( [] );
	}

	public function testSubmit_formFilled_addEntryToLexicon() {
		$page = $this->newSpecialPage();
		$this->speechoidConnector
			->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'transcription' ) );
		$item = new LexiconEntryItem();
		$item->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'transcription' ] ],
			'status' => [
				'name' => 'ok'
			]
		] );
		$this->lexiconStorage->expects( $this->once() )
			->method( 'createEntryItem' )
			->with(
				$this->equalTo( 'en' ),
				$this->equalTo( 'monkey' ),
				$this->equalTo( $item )
			);

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => ''
		] );
	}

	public function testSubmit_existingIdGiven_updateEntry() {
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'old transcription' ] ],
			'status' => [
				'name' => 'ok'
			],
			'id' => 123
		] );
		$entry = new LexiconEntry();
		$entry->setItems( [ $item ] );
		$this->lexiconStorage->method( 'getEntry' )
			->willReturn( $entry );
		$updatedItem = new LexiconEntryItem();
		$updatedItem->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'new transcription' ] ],
			'status' => [
				'name' => 'ok'
			],
			'id' => 123
		] );
		$this->lexiconStorage->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with(
				$this->equalTo( 'en' ),
				$this->equalTo( 'monkey' ),
				$this->equalTo( $updatedItem )
			);
		$this->speechoidConnector
			->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'new transcription' ) );

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => 123
		] );
	}

	public function testSubmit_entryExistsAndNewSelected_createNewItem() {
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'transcription' ] ],
			'status' => [
				'name' => 'ok'
			]
		] );
		$entry = new LexiconEntry();
		$entry->setItems( [ $item ] );
		$this->lexiconStorage->method( 'getEntry' )
			->willReturn( $entry );
		$this->lexiconStorage->expects( $this->once() )
			->method( 'createEntryItem' )
			->with(
				$this->equalTo( 'en' ),
				$this->equalTo( 'monkey' ),
				$this->equalTo( $item )
			);
		$this->speechoidConnector->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'transcription' ) );

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => ''
		] );
	}
}
