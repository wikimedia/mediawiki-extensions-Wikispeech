<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Special;

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
 * @group Database
 * @covers \MediaWiki\Wikispeech\Specials\SpecialEditLexicon
 */
class SpecialEditLexiconTest extends SpecialPageTestBase {

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var LexiconStorage */
	private $lexiconStorage;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		$this->languageNameUtils = $this->createStub( LanguageNameUtils::class );
		$this->lexiconStorage = $this->createMock( LexiconStorage::class );
		$this->speechoidConnector = $this->createStub( SpeechoidConnector::class );
		return new SpecialEditLexicon(
			$this->languageNameUtils,
			$this->lexiconStorage,
			$this->speechoidConnector
		);
	}

	public function testGetLanguageOptions_configHasVoices_giveLanguageOptions() {
		$page = $this->newSpecialPage();
		$this->overrideConfigValue(
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
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

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
				'en',
				'monkey',
				$item
			);

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => '',
			'preferred' => false
		] );
	}

	public function testSubmit_existingIdGiven_updateEntry() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

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
			'id' => 123,
			'preferred' => true
		] );
		$this->lexiconStorage->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with(
				'en',
				'monkey',
				$updatedItem
			);
		$this->speechoidConnector
			->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'new transcription' ) );

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => 123,
			'preferred' => true
		] );
	}

	public function testSubmit_entryExistsAndNewSelected_createNewItem() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

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
				'en',
				'monkey',
				$item
			);
		$this->speechoidConnector->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'transcription' ) );

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => '',
			'preferred' => false
		] );
	}

	public function testSubmit_newSelectedAndPreferredIsTrue_createNewItemWithPreferredTrue() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'transcription' ] ],
			'status' => [
				'name' => 'ok'
			],
			'preferred' => true
		] );
		$entry = new LexiconEntry();
		$entry->setItems( [ $item ] );
		$this->lexiconStorage->method( 'getEntry' )
			->willReturn( $entry );
		$this->speechoidConnector->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'transcription' ) );

		$this->lexiconStorage->expects( $this->once() )
			->method( 'createEntryItem' )
			->with(
				'en',
				'monkey',
				$item
			);

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => '',
			'preferred' => true
		] );
	}

	public function testSubmit_preferredFieldIsFalse_preferredRemovedFromProperties() {
		$this->markTestSkipped( 'Re-enable when T347949 is done.' );

		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'transcription' ] ],
			'status' => [
				'name' => 'ok'
			],
			'id' => 123,
			'preferred' => true
		] );
		$entry = new LexiconEntry();
		$entry->setItems( [ $item ] );
		$this->lexiconStorage->method( 'getEntry' )
			->willReturn( $entry );
		$updatedItem = new LexiconEntryItem();
		$updatedItem->setProperties( [
			'strn' => 'monkey',
			'transcriptions' => [ [ 'strn' => 'transcription' ] ],
			'status' => [
				'name' => 'ok'
			],
			'id' => 123
		] );
		$this->lexiconStorage->expects( $this->once() )
			->method( 'updateEntryItem' )
			->with(
				'en',
				'monkey',
				$updatedItem
			);
		$this->speechoidConnector
			->method( 'fromIpa' )
			->with( 'ipa transcription' )
			->willReturn( Status::newGood( 'transcription' ) );

		$page->submit( [
			'language' => 'en',
			'word' => 'monkey',
			'transcription' => 'ipa transcription',
			'id' => 123,
			'preferred' => false
		] );
	}
}
