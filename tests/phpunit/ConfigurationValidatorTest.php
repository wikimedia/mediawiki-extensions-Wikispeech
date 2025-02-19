<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

 use HashConfig;
 use MediaWiki\Wikispeech\ConfigurationValidator;
 use MediaWikiIntegrationTestCase;
 use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Wikispeech\ConfigurationValidator
 */
class ConfigurationValidatorTest extends MediaWikiIntegrationTestCase {

	/** @var HashConfig */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	/** @var ConfigurationValidator */
	private $validator;

	protected function setUp(): void {
		parent::setUp();

		$this->config = new HashConfig();
		$this->config->set( 'WikispeechSpeechoidUrl', 'https://server.domain' );
		$this->config->set( 'WikispeechSpeechoidResponseTimeoutSeconds', null );
		$this->config->set( 'WikispeechUtteranceTimeToLiveDays', 31 );
		$this->config->set( 'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs', 30 );
		$this->config->set( 'WikispeechUtteranceFileBackendName', '' );
		$this->config->set( 'WikispeechUtteranceFileBackendContainerName', 'wikispeech_utterances' );

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->validator = new ConfigurationValidator( $this->config, $this->logger );
	}

	public function testValidateConfiguration_invalidServerUrl_returnFalse() {
		$this->config->set( 'WikispeechSpeechoidUrl', 'invalid-url' );

		$isValid = $this->validator->validateConfiguration();
		$this->assertFalse( $isValid );
	}

	public function testValidateConfiguration_nonIntegerTimeout_returnFalse() {
		$this->config->set( 'WikispeechSpeechoidResponseTimeoutSeconds', 'not-integer' );

		$isValid = $this->validator->validateConfiguration();
		$this->assertFalse( $isValid );
	}

	public function testValidateConfiguration_negativeUtteranceTimeToLiveDays_returnFalse(): void {
		$this->config->set( 'WikispeechUtteranceTimeToLiveDays', -1 );

		$isValid = $this->validator->validateConfiguration();
		$this->assertFalse( $isValid );
	}

	public function testValidateConfiguration_nullMinutesBetweenFlushJobs_returnFalse(): void {
		$this->config->set( 'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs', null );

		$isValid = $this->validator->validateConfiguration();
		$this->assertFalse( $isValid );
	}

	public function testValidateConfiguration_nullFileBackendName_logWarning(): void {
		$this->config->set( 'WikispeechUtteranceFileBackendName', null );

		$this->logger->expects( $this->once() )
			->method( 'warning' )
			->with( $this->stringContains( "Configuration value 'WikispeechUtteranceFileBackendName' is missing" ) );

		$isValid = $this->validator->validateConfiguration();
		$this->assertTrue( $isValid );
	}

	public function testValidateConfiguration_wrongUnitFileBackendContainerName_returnFalse(): void {
		$this->config->set( 'WikispeechUtteranceFileBackendContainerName', null );

		$isValid = $this->validator->validateConfiguration();
		$this->assertFalse( $isValid );
	}

	public function testValidateConfiguration_validConfiguration_returnTrue() {
		$this->assertTrue( $this->validator->validateConfiguration() );
	}

}
