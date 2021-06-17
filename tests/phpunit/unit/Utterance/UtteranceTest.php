<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Utterance\Utterance;
use MediaWikiUnitTestCase;
use MWTimestamp;

/**
 * Asserts class default values, getters and setters.
 *
 * @covers \MediaWiki\Wikispeech\Utterance\Utterance
 */
class UtteranceTest extends MediaWikiUnitTestCase {

	public function testConstructor() {
		$dateStored = MWTimestamp::getInstance( 20020101000000 );
		$utterance = new Utterance(
			1,
			'DummyRemoteWikiHash',
			2,
			'sv',
			'anna',
			'DummySegmentHash',
			$dateStored
		);
		$this->assertSame( 'DummyRemoteWikiHash', $utterance->getRemoteWikiHash() );
		$this->assertSame( 2, $utterance->getPageId() );
		$this->assertSame( 'sv', $utterance->getLanguage() );
		$this->assertSame( 'anna', $utterance->getVoice() );
		$this->assertSame( 'DummySegmentHash', $utterance->getSegmentHash() );
		$this->assertSame( $dateStored, $utterance->getDateStored() );

		$this->assertNull( $utterance->getAudio() );
		$this->assertNull( $utterance->getSynthesisMetadata() );
	}

	public function testConstructorNoRemoteWikiHash() {
		$dateStored = MWTimestamp::getInstance( 20020101000000 );
		$utterance = new Utterance(
			1,
			null,
			2,
			'sv',
			'anna',
			'DummySegmentHash',
			$dateStored
		);
		$this->assertNull( $utterance->getRemoteWikiHash() );
		$this->assertSame( 2, $utterance->getPageId() );
		$this->assertSame( 'sv', $utterance->getLanguage() );
		$this->assertSame( 'anna', $utterance->getVoice() );
		$this->assertSame( 'DummySegmentHash', $utterance->getSegmentHash() );
		$this->assertSame( $dateStored, $utterance->getDateStored() );

		$this->assertNull( $utterance->getAudio() );
		$this->assertNull( $utterance->getSynthesisMetadata() );
	}

	public function testSettersAndGetters() {
		$utterance = new Utterance(
			0,
			null,
			0,
			'',
			'',
			'',
			MWTimestamp::getInstance( 20000101000000 )
		);

		$utterance->setUtteranceId( 1 );
		$this->assertSame( 1, $utterance->getUtteranceId() );

		$utterance->setAudio( 'audio' );
		$this->assertSame( 'audio', $utterance->getAudio() );

		$utterance->setSynthesisMetadata( 'synthesisMetadata' );
		$this->assertSame( 'synthesisMetadata', $utterance->getSynthesisMetadata() );

		$utterance->setRemoteWikiHash( 'remoteWikiHash' );
		$this->assertSame( 'remoteWikiHash', $utterance->getRemoteWikiHash() );

		$utterance->setPageId( 1 );
		$this->assertSame( 1, $utterance->getPageId() );

		$utterance->setLanguage( 'language' );
		$this->assertSame( 'language', $utterance->getLanguage() );

		$utterance->setVoice( 'voice' );
		$this->assertSame( 'voice', $utterance->getVoice() );

		$utterance->setSegmentHash( 'segmentHash' );
		$this->assertSame( 'segmentHash', $utterance->getSegmentHash() );

		$dateStored = MWTimestamp::getInstance();
		$utterance->setDateStored( $dateStored );
		$this->assertSame( $dateStored, $utterance->getDateStored() );
	}

}
