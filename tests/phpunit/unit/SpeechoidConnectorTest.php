<?php

namespace MediaWiki\Wikispeech\Tests\Unit;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */
use HashConfig;
use InvalidArgumentException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\Wikispeech\SpeechoidConnector
 */
class SpeechoidConnectorTest extends MediaWikiUnitTestCase {

	/** @var HashConfig */
	private $config;

	/** @var HttpRequestFactory|MockObject */
	private $requestFactory;

	/** @var SpeechoidConnector|MockObject */
	private $speechoidConnector;

	protected function setUp() : void {
		$this->requestFactory = $this->createMock( HttpRequestFactory::class );
		$this->config = new HashConfig();
		$this->config->set( 'WikispeechSpeechoidResponseTimeoutSeconds', null );
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->speechoidConnector = $this->getMockBuilder( SpeechoidConnector::class )
			->setMethods( [ 'findLexiconByLanguage' ] )
			->setConstructorArgs( [
				$this->config,
				$this->requestFactory
			] )
			->getMock();
	}

	public function testSynthesize_textGiven_sendRequestWithTextAsInput() {
		$this->requestFactory
			->method( 'post' )
			->willReturn( '{"speechoid": "response"}' );
		$this->requestFactory
			->expects( $this->once() )
			->method( 'post' )
			->with(
				$this->equalTo( 'speechoid.url' ),
				$this->equalTo( [ 'postData' => [
					'lang' => 'en',
					'voice' => 'en-voice',
					'input' => 'say this'
				] ] )
			);
		$response = $this->speechoidConnector->synthesize(
			'en',
			'en-voice',
			[ 'text' => 'say this' ]
		);
		$this->assertSame( [ 'speechoid' => 'response' ], $response );
	}

	public function testSynthesize_ipaGiven_sendRequestWithIpaAsInputAndIpaAsType() {
		$this->requestFactory
			->method( 'post' )
			->willReturn( '{"speechoid": "response"}' );
		$this->requestFactory
			->expects( $this->once() )
			->method( 'post' )
			->with(
				$this->equalTo( 'speechoid.url' ),
				$this->equalTo( [ 'postData' => [
					'lang' => 'en',
					'voice' => 'en-voice',
					'input' => 'seɪ.ðɪs',
					'input_type' => 'ipa'
				] ] )
			);
		$response = $this->speechoidConnector->synthesize(
			'en',
			'en-voice',
			[ 'ipa' => 'seɪ.ðɪs' ]
		);
		$this->assertSame( [ 'speechoid' => 'response' ], $response );
	}

	public function testSynthesize_textOrIpaNotInParameters_throwException() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'$parameters must contain one of "text" and "ipa".'
		);

		$this->speechoidConnector->synthesize(
			'en',
			'en-voice',
			[]
		);
	}

	public function testIpaToSampa_ipaGiven_giveSampa() {
		$this->config->set( 'WikispeechSymbolSetUrl', 'symbolset.url' );
		$this->speechoidConnector
			->method( 'findLexiconByLanguage' )
			->willReturn( 'lexicon-name' );
		$this->requestFactory
			->method( 'get' )
			->withConsecutive(
				[ 'speechoid.url/lexserver/lexicon/info/lexicon-name' ],
				[ 'symbolset.url/mapper/map/ipa/target-symbol-set/ipa transcription' ]
			)
			->will( $this->onConsecutiveCalls(
				'{"symbolSetName": "target-symbol-set"}',
				'{"Result": "sampa transcription"}'
			) );

		$sampa = $this->speechoidConnector->ipaToSampa(
			'ipa transcription',
			'en'
		);

		$this->assertSame( 'sampa transcription', $sampa );
	}

	public function testDeleteLexiconEntry_goodLexiconNameAndIdentityGiven_isOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
				"deleted entry id 'identity' from lexicon 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertTrue( $status->isOK() );
	}

	public function testDeleteLexiconEntry_badLexiconNameGiven_isNotOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
			// phpcs:ignore Generic.Files.LineLength.TooLong
				"couldn't parse lexicon ref : : ParseLexRef: failed to split full lexicon name into two colon separated parts: 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertFalse( $status->isOK() );
	}

	public function testDeleteLexiconEntry_nonExistingLexiconNameGiven_isNotOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
			// phpcs:ignore Generic.Files.LineLength.TooLong
				"failed to detele entry id '1' in lexicon 'lexiconName' : DBManager.DeleteEntry: no such db 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertFalse( $status->isOK() );
	}

	public function testDeleteLexiconEntry_nonExistingIdentityGiven_isNotOK() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'speechoid.url' );
		$this->requestFactory
			->method( 'get' )
			->with( 'speechoid.url/lexserver/lexicon/delete_entry/lexiconName/0' )
			->willReturn(
			// phpcs:ignore Generic.Files.LineLength.TooLong
				"failed to detele entry id 'identity' in lexicon 'lexiconName' : dbapi.deleteEntry failed to delete entry with id 'identity' from lexicon 'lexiconName'"
			);
		$status = $this->speechoidConnector->deleteLexiconEntry(
			'lexiconName',
			0
		);
		$this->assertFalse( $status->isOK() );
	}
}
