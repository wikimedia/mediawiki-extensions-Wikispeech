<?php

namespace MediaWiki\Wikispeech\Tests\Unit;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use HashBagOStuff;
use HashConfig;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\VoiceHandler;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\AbstractLogger;

/**
 * @covers \MediaWiki\Wikispeech\VoiceHandler
 */
class VoiceHandlerTest extends MediaWikiUnitTestCase {

	/** @var HashConfig */
	private $config;

	/** @var VoiceHandler */
	private $voiceHandler;

	/** @var MockObject|SpeechoidConnector */
	private $speechoidConnectorMock;

	protected function setUp(): void {
		parent::setUp();
		$logger = $this->createStub( AbstractLogger::class );
		$this->speechoidConnectorMock = $this->createMock(
			SpeechoidConnector::class
		);
		$this->config = new HashConfig();
		$cache = $this->createStub( HashBagOStuff::class );
		$this->voiceHandler = new VoiceHandler(
			$logger,
			$this->config,
			$this->speechoidConnectorMock,
			$cache
		);
	}

	public function testGetDefaultVoice_definedExisting_definedDefaultVoice() {
		$this->config->set(
			'WikispeechVoices',
			[
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'bertil',
					'en' => 'bravo'
				]
			);
		$this->assertEquals( 'bertil', $this->voiceHandler->getDefaultVoice( 'sv' ) );
	}

	public function testGetDefaultVoice_definedNonExisting_fallbackToFirstVoice() {
		$this->config->set(
			'WikispeechVoices',
			[
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'en' => 'bravo'
				]
			);
		$this->assertEquals( 'adam', $this->voiceHandler->getDefaultVoice( 'sv' ) );
	}

	public function testGetDefaultVoice_definedFalsy_fallbackToFirstVoice() {
		$this->config->set(
			'WikispeechVoices',
			[
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ],
				'no' => [ 'anne', 'bergit', 'clark' ],
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->exactly( 3 ) )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => '',
					'en' => false,
					'no' => null
				]
			);
		$this->assertEquals( 'adam', $this->voiceHandler->getDefaultVoice( 'sv' ) );
		$this->assertEquals( 'alpha', $this->voiceHandler->getDefaultVoice( 'en' ) );
		$this->assertEquals( 'anne', $this->voiceHandler->getDefaultVoice( 'no' ) );
	}

	public function testGetDefaultVoice_unsupported_null() {
		$this->config->set(
			'WikispeechVoices',
			[
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'en' => 'bravo'
				]
			);
		$this->assertNull( $this->voiceHandler->getDefaultVoice( 'sv' ) );
	}

	public function testGetDefaultVoice_definedDefaultNonExistingLanguage_null() {
		$this->config->set(
			'WikispeechVoices',
			[
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'adam',
					'en' => 'bravo'
				]
			);
		$this->assertNull( $this->voiceHandler->getDefaultVoice( 'sv' ) );
	}

	public function testGetDefaultVoice_definedDefaultNoVoicesInExistingLanguage_null() {
		$this->config->set(
			'WikispeechVoices',
			[
				'sv' => [],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'adam',
					'en' => 'bravo'
				]
			);
		$this->assertNull( $this->voiceHandler->getDefaultVoice( 'sv' ) );
	}

	public function testGetDefaultVoice_definedDefaultNotRegisteredInLanguage_null() {
		$this->config->set(
			'WikispeechVoices',
			[
				'sv' => [ 'adam', 'bertil', 'cesar' ],
				'en' => [ 'alpha', 'bravo', 'charlie' ]
			]
		);
		$this->speechoidConnectorMock
			->expects( $this->once() )
			->method( 'listDefaultVoicePerLanguage' )
			->willReturn( [
					'sv' => 'david',
					'en' => 'bravo'
				]
			);
		$this->assertNull( $this->voiceHandler->getDefaultVoice( 'sv' ) );
	}

}
