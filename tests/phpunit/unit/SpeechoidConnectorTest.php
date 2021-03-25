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

/**
 * @covers \MediaWiki\Wikispeech\SpeechoidConnector
 */
class SpeechoidConnectorTest extends MediaWikiUnitTestCase {
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
}
