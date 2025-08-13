<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Mediawiki\Title\Title;
use MediaWiki\Wikispeech\Lexicon\LexiconEntry;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconSpeechoidStorage;
use MediaWiki\Wikispeech\Lexicon\LexiconWikiStorage;
use MediaWiki\Wikispeech\PopulateSpeechoidLexiconFromWiki;
use User;

require_once __DIR__ . '/../../maintenance/populateSpeechoidLexiconFromWiki.php';

/**
 * @covers \MediaWiki\Wikispeech\PopulateSpeechoidLexiconFromWiki
 *
 * @since 0.1.13
 * @license GPL-2.0-or-later
 */
class PopulateSpeechoidLexiconFromWikiTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return PopulateSpeechoidLexiconFromWiki::class;
	}

	public function testRecreatesSpeechoidEntry_WhenMissingInStorage() {
		$this->setMwGlobals( 'wgWikispeechVoices', [ 'sv' => [ 'voice' => 'TestVoice' ] ] );

		$language = 'sv';
		$key = 'katt';
		$identity = 12345;

		$item = new LexiconEntryItem();
		$item->setProperties( (object)[
			'id' => $identity,
			'strn' => $key,
			'transcriptions' => [ (object)[ 'strn' => '" k a t' ] ],
			'status' => (object)[ 'name' => 'ok' ]
		] );

		$entry = $this->createMock( LexiconEntry::class );
		$entry->method( 'getItems' )->willReturn( [ $item ] );

		$wikiStorage = $this->createMock( LexiconWikiStorage::class );
		$wikiStorage->method( 'getEntry' )->willReturn( $entry );
		$wikiStorage->expects( $this->once() )
			->method( 'replaceEntryItem' )
			->with( $language, $key, $item );
		$wikiStorage->expects( $this->once() )
			->method( 'saveLexiconEntryRevision' );

		$speechoidStorage = $this->createMock( LexiconSpeechoidStorage::class );
		$speechoidStorage->method( 'getEntry' )->willReturn( null );
		$speechoidStorage->expects( $this->once() )
			->method( 'createEntryItem' )
			->with( $language, $key, $item );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( "$language/$key" );

		$maint = new PopulateSpeechoidLexiconFromWiki();
		$maint->lexiconWikiStorage = $wikiStorage;
		$maint->speechoidStorage = $speechoidStorage;
		$maint->getAllLexiconTitlesForLanguageCallback = static fn ( $lang ) => [ $title ];
		$maint->user = $this->createMock( User::class );

		$maint->execute();
	}

}
