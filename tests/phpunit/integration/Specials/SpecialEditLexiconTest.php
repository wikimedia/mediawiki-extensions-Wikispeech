<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Special;

use Exception;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Status\Status;
use MediaWiki\Wikispeech\Lexicon\LexiconEntry;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconStorage;
use MediaWiki\Wikispeech\Specials\SpecialEditLexicon;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use OutputPage;
use PermissionsError;
use SpecialPageTestBase;
use User;

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

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var User */
	private $user;

	/** @var SpecialEditLexicon */
	private $page;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::Server, '//wiki.test' );
	}

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		$this->languageNameUtils = $this->createStub( LanguageNameUtils::class );
		$this->lexiconStorage = $this->createMock( LexiconStorage::class );
		$this->speechoidConnector = $this->createStub( SpeechoidConnector::class );
		$this->utteranceStore = $this->createStub( UtteranceStore::class );

		$this->page = new SpecialEditLexicon(
			$this->languageNameUtils,
			$this->lexiconStorage,
			$this->speechoidConnector,
			$this->utteranceStore
		);
		if ( $this->user ) {
			$this->page->getContext()->setUser( $this->user );
		}

		return $this->page;
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
		$item->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'transcription' ] ],
			'status' => (object)[ 'name' => 'ok' ]
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
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'old transcription' ] ],
			'status' => (object)[
				'name' => 'ok'
			],
			'id' => 123
		] );
		$entry = new LexiconEntry();
		$entry->setItems( [ $item ] );
		$this->lexiconStorage->method( 'getEntry' )
			->willReturn( $entry );
		$updatedItem = new LexiconEntryItem();
		$updatedItem->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'new transcription' ] ],
			'status' => (object)[
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
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'transcription' ] ],
			'status' => (object)[
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
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'transcription' ] ],
			'status' => (object)[
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
		$page = $this->newSpecialPage();
		$item = new LexiconEntryItem();
		$item->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'transcription' ] ],
			'status' => (object)[
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
		$updatedItem->setProperties( (object)[
			'strn' => 'monkey',
			'transcriptions' => [ (object)[ 'strn' => 'transcription' ] ],
			'status' => (object)[
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

	public function testExecute_autoLoginOnNotLoggedInLackingRight_redirectToLoginPage() {
		$this->user = null;
		$this->setGroupPermissions( '*', 'wikispeech-edit-lexicon', false );
		$this->overrideConfigValue( 'WikispeechEditLexiconAutoLogin', true );
		$this->overrideConfigValue( MainConfigNames::Script, '/wiki/index.php' );
		$request = new FauxRequest( [
			'language' => 'en',
			'word' => 'monkey'
		] );

		$this->executeSpecialPage( '', $request );

		$this->assertSame(
            // phpcs:ignore Generic.Files.LineLength
			'http://wiki.test/wiki/index.php?title=Special:UserLogin&returnto=Special%3AEditLexicon&returntoquery=language%3Den%26word%3Dmonkey',
			$this->page->getOutput()->getRedirect()
		);
	}

	public function testExecute_autoLoginOffNotLoggedInLackingRight_permissionError() {
		$this->user = null;
		$this->setGroupPermissions( '*', 'wikispeech-edit-lexicon', false );
		$this->overrideConfigValue( 'WikispeechEditLexiconAutoLogin', false );

		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage();
	}

	public function testExecute_speechoidConnectorConnectionFails_showErrorPage() {
		$this->setGroupPermissions( '*', 'wikispeech-edit-lexicon', true );

		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )
			->method( 'showErrorPage' )
			->with( 'error', 'wikispeech-edit-lexicon-speechoid-down' );

		$page = $this->newSpecialPage();

		$page->getContext()->setOutput( $outputPage );

		$this->speechoidConnector->method( 'requestLexicons' )
			->willThrowException( new Exception() );

		$page->execute( '' );
	}

	public function testSubmitFlushesUtterances_pageAndConsumerUrlGiven_utterancesForPageFlushed() {
		$mockUtteranceStore = $this->createMock( UtteranceStore::class );
		$mockUtteranceStore->expects( $this->once() )
			->method( 'flushUtterancesByPage' )
			->with( 'http://localhost:8080/wiki', 123 );

		$mockLexiconStorage = $this->createMock( LexiconStorage::class );
		$mockLexiconStorage->method( 'createEntryItem' );

		$mockStatus = $this->createMock( Status::class );
		$mockStatus->method( 'isOk' )->willReturn( true );
		$mockStatus->method( 'getValue' )->willReturn( 'some-sampa' );

		$mockSpeechoidConnector = $this->createMock( SpeechoidConnector::class );
		$mockSpeechoidConnector->method( 'fromIpa' )->willReturn( $mockStatus );

		$special = new SpecialEditLexicon(
		$this->createMock( LanguageNameUtils::class ),
		$mockLexiconStorage,
		$mockSpeechoidConnector,
		$mockUtteranceStore
		);

		$data = [
			'language' => 'sv',
			'word' => 'hej',
			'id' => '',
			'transcription' => 'hɛj',
			'preferred' => true,
			'page' => 123,
			'consumerUrl' => 'http://localhost:8080/wiki'
		];

		$special->submit( $data );
	}
}
