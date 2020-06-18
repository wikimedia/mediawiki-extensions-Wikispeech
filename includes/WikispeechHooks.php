<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class WikispeechHooks {

	/**
	 * Conditionally register the unit testing module for the ext.wikispeech
	 * module only if that module is loaded.
	 *
	 * @param array &$testModules The array of registered test modules
	 * @param ResourceLoader $resourceLoader The reference to the resource
	 *  loader
	 */
	public static function onResourceLoaderTestModules(
		array &$testModules,
		ResourceLoader $resourceLoader
	) {
		$testModules['qunit']['ext.wikispeech.test'] = [
			'scripts' => [
				'ext.wikispeech.highlighter.test.js',
				'ext.wikispeech.player.test.js',
				'ext.wikispeech.selectionPlayer.test.js',
				'ext.wikispeech.storage.test.js',
				'ext.wikispeech.test.util.js',
				'ext.wikispeech.ui.test.js'
			],
			'dependencies' => [
				// Despite what it says at
				// https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules,
				// adding 'ext.wikispeech.highlighter' etc. isn't
				// needed and in fact breaks the testing.
				'ext.wikispeech'
			],
			'localBasePath' => __DIR__ . '/../tests/qunit/',
			'remoteExtPath' => 'Wikispeech/tests/qunit/'
		];
	}

	/**
	 * Investigates whether or not configuration is valid.
	 *
	 * Writes all invalid configuration entries to the log.
	 *
	 * @since 0.1.3
	 * @return bool true if all configuration passes validation
	 */
	private static function validateConfiguration() {
		$success = true;
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );

		$serverUrl = $config->get( 'WikispeechServerUrl' );
		if ( !filter_var( $serverUrl, FILTER_VALIDATE_URL ) ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value for 'WikispeechServerUrl' is not a valid URL: {value}",
				[ 'value' => $serverUrl ]
			);
			$success = false;
		}

		$utteranceTimeToLiveDays = $config->get( 'WikispeechUtteranceTimeToLiveDays' );
		if ( !$utteranceTimeToLiveDays ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value for 'WikispeechUtteranceTimeToLiveDays' is missing"
			);
			$success = false;
		}
		$utteranceTimeToLiveDays = intval( $utteranceTimeToLiveDays );
		if ( $utteranceTimeToLiveDays < 0 ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value for 'WikispeechUtteranceTimeToLiveDays' must not be negative."
			);
			$success = false;
		}

		$fileBackendName = $config->get( 'WikispeechUtteranceFileBackendName' );
		if ( $fileBackendName == null ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value 'WikispeechUtteranceFileBackendName' is missing."
			);
			// this is not a failure. It will fall back on default, but admin should be aware.
		} elseif ( !is_string( $fileBackendName ) ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value 'WikispeechUtteranceFileBackendName' is not a string value."
			);
			$success = false;
		}

		$fileBackendContainerName = $config->get( 'WikispeechUtteranceFileBackendContainerName' );
		if ( $fileBackendContainerName == null ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value 'WikispeechUtteranceFileBackendContainerName' is missing."
			);
			$success = false;
		} elseif ( !is_string( $fileBackendContainerName ) ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value 'WikispeechUtteranceFileStore.type' is not a string value."
			);
			$success = false;
		}

		return $success;
	}

	/**
	 * Hook for BeforePageDisplay.
	 *
	 * Enables JavaScript.
	 *
	 * @param OutputPage $out The OutputPage object.
	 * @param Skin $skin Skin object that will be used to generate the page,
	 *  added in MediaWiki 1.13.
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$namespace = $out->getTitle()->getNamespace();
		$config = MediaWikiServices::getInstance()->
			getConfigFactory()->
			makeConfig( 'wikispeech' );
		$validNamespaces = $config->get( 'WikispeechNamespaces' );
		$validLanguages = array_keys( $config->get( 'WikispeechVoices' ) );
		if ( $out->getUser()->getOption( 'wikispeechEnable' ) &&
			 $out->getUser()->isAllowed( 'wikispeech-listen' ) &&
			 self::validateConfiguration() &&
			 in_array( $namespace, $validNamespaces ) &&
			 $out->isRevisionCurrent() &&
			 in_array( $out->getLanguage()->getCode(), $validLanguages )
		) {
			$out->addModules( [
				'ext.wikispeech'
			] );
			$out->addJsConfigVars( [
				'wgWikispeechKeyboardShortcuts' => $config->get( 'WikispeechKeyboardShortcuts' ),
				'wgWikispeechContentSelector' => $config->get( 'WikispeechContentSelector' ),
				'wgWikispeechSkipBackRewindsThreshold' => $config->get( 'WikispeechSkipBackRewindsThreshold' ),
				'wgWikispeechHelpPage' => $config->get( 'WikispeechHelpPage' ),
				'wgWikispeechFeedbackPage' => $config->get( 'WikispeechFeedbackPage' )
			] );
		}
	}

	/**
	 * Hook for ApiBeforeMain.
	 *
	 * Calls configuration validation for logging purposes on API calls,
	 * but doesn't stop the use of the API due to invalid configuration.
	 * Generally a user would not call the API at this point as the module
	 * wouldn't actually have been added in onBeforePageDisplay.
	 *
	 * @since 0.1.3
	 * @param ApiMain &$main The ApiMain instance being used.
	 */
	public static function onApiBeforeMain( &$main ) {
		self::validateConfiguration();
	}

	/**
	 * Conditionally register static configuration variables for the
	 * ext.wikispeech module only if that module is loaded.
	 *
	 * @param array &$vars The array of static configuration variables.
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgWikispeechServerUrl;
		$vars[ 'wgWikispeechServerUrl' ] =
			$wgWikispeechServerUrl;
		global $wgWikispeechNamespaces;
		$vars['wgWikispeechNamespaces'] =
			$wgWikispeechNamespaces;
	}

	/**
	 * Add Wikispeech options to Special:Preferences.
	 *
	 * @param User $user current User object.
	 * @param array &$preferences Preferences array.
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		self::addWikispeechEnable( $preferences );
		self::addVoicePreferences( $preferences );
		self::addSpeechRatePreferences( $preferences );
	}

	/**
	 * Add preference for enabilng/disabling Wikispeech.
	 *
	 * @param array &$preferences Preferences array.
	 */
	private static function addWikispeechEnable( &$preferences ) {
		$preferences['wikispeechEnable'] = [
			'type' => 'toggle',
			'label-message' => 'prefs-wikispeech-enable',
			'section' => 'wikispeech'
		];
	}

	/**
	 * Add preferences for selecting voices per language.
	 *
	 * @param array &$preferences Preferences array.
	 */
	private static function addVoicePreferences( &$preferences ) {
		global $wgWikispeechVoices;
		foreach ( $wgWikispeechVoices as $language => $voices ) {
			$languageKey = 'wikispeechVoice' . ucfirst( $language );
			$mwLanguage = Language::factory( 'en' );
			$languageName = $mwLanguage->getVariantname( $language );
			$options = [ 'Default' => '' ];
			foreach ( $voices as $voice ) {
				$options[$voice] = $voice;
			}
			$preferences[$languageKey] = [
				'type' => 'select',
				'label' => $languageName,
				'section' => 'wikispeech/wikispeech-voice',
				'options' => $options
			];
		}
	}

	/**
	 * Add preferences for selecting speech rate.
	 *
	 * @param array &$preferences Preferences array.
	 */
	private static function addSpeechRatePreferences( &$preferences ) {
		$options = [
			'400%' => 4.0,
			'200%' => 2.0,
			'150%' => 1.5,
			'100%' => 1.0,
			'75%' => 0.75,
			'50%' => 0.5
		];
		$preferences['wikispeechSpeechRate'] = [
			'type' => 'select',
			'label-message' => 'prefs-wikispeech-speech-rate',
			'section' => 'wikispeech/wikispeech-voice',
			'options' => $options
		];
	}

	/**
	 * Check if the user is allowed to use a API module.
	 *
	 * @since 0.1.3
	 * @param ApiBase $module
	 * @param User $user
	 * @param ApiMessage &$message
	 * @return bool
	 */
	public static function onApiCheckCanExecute( $module, $user, &$message ) {
		if (
			$module->getModuleName() == 'wikispeechlisten' &&
			!$user->isAllowed( 'wikispeech-listen' )
		) {
			$message = ApiMessage::create(
				'apierror-wikispeechlisten-notallowed'
			);
			return false;
		}
		return true;
	}

	/**
	 * Creates utterance database tables.
	 *
	 * @since 0.1.5
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'wikispeech_utterance',
			__DIR__ . '/../sql/wikispeech_utterance_v1.sql'
		);
	}

}
