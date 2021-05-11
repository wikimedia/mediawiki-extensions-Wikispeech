<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Utterance;

use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWikiUnitTestCase;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @group Database
 * @group medium
 * @covers \MediaWiki\Wikispeech\Utterance\UtteranceStore
 */
class UtteranceStoreRemoteWikiHashTest extends MediaWikiUnitTestCase {

	public function testEvaluateRemoteWikiHash_differentHosts_hashDiffers() {
		$this->assertNotSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://bar.foo:80/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'https://foo.bar:80/wiki' )
		);
	}

	public function testEvaluateRemoteWikiHash_differentProtocols_sameHash() {
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'https://foo.bar:80/wiki' )
		);
	}

	public function testEvaluateRemoteWikiHash_caseInHostDiffers_sameHash() {
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://FOO.bar:80/wiki' )
		);
	}

	public function testEvaluateRemoteWikiHash_differentPorts_hashDiffers() {
		$this->assertNotSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:8080/wiki' )
		);
	}

	public function testEvaluateRemoteWikiHash_noPortSetVsPort80_hashDiffers() {
		$this->assertNotSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki' )
		);
	}

	public function testEvaluateRemoteWikiHash_queryDiffers_sameHash() {
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki?query&value=set' )
		);
	}

	public function testEvaluateRemoteWikiHash_userDiffers_sameHash() {
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://user@foo.bar:80/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar:80/wiki' )
		);
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://user1@foo.bar/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://user2@foo.bar/wiki' )
		);
	}

	public function testEvaluateRemoteWikiHash_passwordDiffers_sameHash() {
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://foo.bar/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://user:secret@foo.bar/wiki' )
		);
		$this->assertSame(
			UtteranceStore::evaluateRemoteWikiHash( 'http://user:password@foo.bar/wiki' ),
			UtteranceStore::evaluateRemoteWikiHash( 'http://user:secret@foo.bar/wiki' )
		);
	}

}
