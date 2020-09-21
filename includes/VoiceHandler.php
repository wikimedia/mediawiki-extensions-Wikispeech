<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */
use Psr\Log\LoggerInterface;

/**
 * Handles voice information.
 *
 * @since 0.1.7
 */
class VoiceHandler {

	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $config;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var WANObjectCache */
	private $cache;

	/**
	 * @since 0.1.7
	 * @param LoggerInterface $logger
	 * @param Config $config
	 * @param SpeechoidConnector $speechoidConnector
	 * @param WANObjectCache $cache
	 */
	public function __construct( $logger, $config, $speechoidConnector, $cache ) {
		$this->logger = $logger;
		$this->config = $config;
		$this->speechoidConnector = $speechoidConnector;
		$this->cache = $cache;
	}

	/**
	 * Picks up the configured default voice for a language, or
	 * fallback on the first registered voice for that language.
	 *
	 * Default voice per language response from Speechoid is cached
	 * for one hour.
	 *
	 * @since 0.1.7
	 * @param string $language
	 * @return string|null Default language or null if language or no
	 *  voices are registered.
	 */
	public function getDefaultVoice( $language ) {
		$cacheKey = $this->cache->makeKey(
			'Wikispeech.voiceHandler.defaultVoicePerLanguage',
			$language
		);
		$defaultVoicePerLanguage = $this->cache->get( $cacheKey );
		if (
			// not set
			$defaultVoicePerLanguage === null ||
			// cache error
			$defaultVoicePerLanguage === false
		) {
			$defaultVoicePerLanguage = $this->speechoidConnector->listDefaultVoicePerLanguage();
			// One hour TTL.  I.e. it will take one hour for a new
			// default language in Speechoid to be selected.
			$this->cache->set(
				$cacheKey,
				$defaultVoicePerLanguage,
				$this->cache::TTL_HOUR
			);
		}
		$registeredVoicesPerLanguage = $this->config->get( 'WikispeechVoices' );
		$defaultVoice = null;
		if ( array_key_exists( $language, $defaultVoicePerLanguage ) ) {
			// is defined as a language in list of default languages
			$defaultVoice = $defaultVoicePerLanguage[$language];
		}
		if ( !$defaultVoice ) {
			// unable to find a default voice for the language
			if ( !array_key_exists( $language, $registeredVoicesPerLanguage ) ) {
				// not a registered language
				$this->logger->error( __METHOD__ . ': ' .
					'Not a registered language: {language}',
					[ 'language' => $language ]
				);
				return null;
			}
			$languageVoices = $registeredVoicesPerLanguage[$language];
			if ( !$languageVoices ) {
				// no voices registered to the language
				$this->logger->error( __METHOD__ . ': ' .
					'No voices registered to language: {language}',
					[ 'language' => $language ]
				);
				return null;
			}
			// falling back on first registered voice as default
			return $languageVoices[0];
		}
		// make sure defaultVoice is a registered voice
		if ( !array_key_exists( $language, $registeredVoicesPerLanguage ) ) {
			// language registered with default voice but not a as
			// language with voices.
			$this->logger->error( __METHOD__ . ': ' .
				'Default voice found but language not registered in config: {language}',
				[ 'language' => $language ]
			);
			return null;
		}
		$languageVoices = $registeredVoicesPerLanguage[$language];
		if ( !in_array( $defaultVoice, $languageVoices ) ) {
			$this->logger->error( __METHOD__ . ': ' .
				'Default voice not registered to language: {voice} {language}',
				[
					'voice' => $defaultVoice,
					'language' => $language
				]
			);
			return null;
		}
		return $defaultVoice;
	}
}
