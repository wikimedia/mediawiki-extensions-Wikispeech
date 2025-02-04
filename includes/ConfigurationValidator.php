<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use Psr\Log\LoggerInterface;

class ConfigurationValidator {

	/** @var Config */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	public function __construct( Config $config, LoggerInterface $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Investigates whether or not configuration is valid.
	 *
	 * Writes all invalid configuration entries to the log.
	 *
	 * @since 0.1.11
	 * @return bool true if all configuration passes validation
	 */
	public function validateConfiguration(): bool {
		$success = true;

		$speechoidUrl = $this->config->get( 'WikispeechSpeechoidUrl' );
		if ( !filter_var( $speechoidUrl, FILTER_VALIDATE_URL ) ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value for ' .
					'\'WikispeechSpeechoidUrl\' is not a valid URL: {value}',
					[ 'value' => $speechoidUrl ]
				);
			$success = false;
		}
		$speechoidResponseTimeoutSeconds = $this->config
			->get( 'WikispeechSpeechoidResponseTimeoutSeconds' );
		if ( $speechoidResponseTimeoutSeconds &&
			!is_int( $speechoidResponseTimeoutSeconds ) ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value ' .
					'\'WikispeechSpeechoidResponseTimeoutSeconds\' ' .
					'is not a falsy or integer value.'
				);
			$success = false;
		}

		$utteranceTimeToLiveDays = $this->config
			->get( 'WikispeechUtteranceTimeToLiveDays' );
		if ( $utteranceTimeToLiveDays === null ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value for ' .
					'\'WikispeechUtteranceTimeToLiveDays\' is missing.'
				);
			$success = false;
		}
		$utteranceTimeToLiveDays = intval( $utteranceTimeToLiveDays );
		if ( $utteranceTimeToLiveDays < 0 ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value for ' .
					'\'WikispeechUtteranceTimeToLiveDays\' must not be negative.'
				);
			$success = false;
		}

		$minimumMinutesBetweenFlushExpiredUtterancesJobs = $this->config
			->get( 'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' );
		if ( $minimumMinutesBetweenFlushExpiredUtterancesJobs === null ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value for ' .
					'\'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs\' ' .
					'is missing.'
				);
			$success = false;
		}
		$minimumMinutesBetweenFlushExpiredUtterancesJobs = intval(
			$minimumMinutesBetweenFlushExpiredUtterancesJobs
		);
		if ( $minimumMinutesBetweenFlushExpiredUtterancesJobs < 0 ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value for ' .
					'\'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs\'' .
					' must not be negative.'
				);
			$success = false;
		}

		$fileBackendName = $this->config->get( 'WikispeechUtteranceFileBackendName' );
		if ( $fileBackendName === null ) {
			$this->logger
				->warning( __METHOD__ . ':  Configuration value ' .
					'\'WikispeechUtteranceFileBackendName\' is missing.'
				);
			// This is not a failure.
			// It will fall back on default, but admin should be aware.
		} elseif ( !is_string( $fileBackendName ) ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value ' .
					'\'WikispeechUtteranceFileBackendName\' is not a string value.'
				);
			$success = false;
		}

		$fileBackendContainerName = $this->config
			->get( 'WikispeechUtteranceFileBackendContainerName' );
		if ( $fileBackendContainerName === null ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value ' .
					'\'WikispeechUtteranceFileBackendContainerName\' is missing.'
				);
			$success = false;
		} elseif ( !is_string( $fileBackendContainerName ) ) {
			$this->logger
				->warning( __METHOD__ . ': Configuration value ' .
					'\'WikispeechUtteranceFileBackendContainerName\' is not a string value.'
				);
			$success = false;
		}

		return $success;
	}
}
