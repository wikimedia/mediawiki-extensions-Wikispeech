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
				'tests/qunit/ext.wikispeech.highlighter.test.js',
				'tests/qunit/ext.wikispeech.player.test.js',
				'tests/qunit/ext.wikispeech.selectionPlayer.test.js',
				'tests/qunit/ext.wikispeech.storage.test.js',
				'tests/qunit/ext.wikispeech.test.util.js',
				'tests/qunit/ext.wikispeech.ui.test.js'
			],
			'dependencies' => [
				// Despite what it says at
				// https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules,
				// adding 'ext.wikispeech.highlighter' etc. isn't
				// needed and in fact breaks the testing.
				'ext.wikispeech'
			],
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikispeech'
		];
	}

	/**
	 * Investigates whether or not configuration is valid.
	 *
	 * Writes entries to the log in case not valid.
	 *
	 * @since 0.1.3
	 * @return bool true if all configuration passes validation
	 */
	private static function validateConfiguration() {
		$config = MediaWikiServices::getInstance()->
			getConfigFactory()->
			makeConfig( 'wikispeech' );
		$serverUrl = $config->get( 'WikispeechServerUrl' );

		if ( !filter_var( $serverUrl, FILTER_VALIDATE_URL ) ) {
			LoggerFactory::getInstance( 'Wikispeech' )->warning(
				"Configuration value for 'WikispeechServerUrl' is not a valid URL: {value}",
				[ 'value' => $serverUrl ]
			);
			return false;
		}
		return true;
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
	static function onGetPreferences( $user, &$preferences ) {
		self::addWikispeechEnable( $preferences );
		self::addVoicePreferences( $preferences );
		self::addSpeechRatePreferences( $preferences );
	}

	/**
	 * Add preference for enabilng/disabling Wikispeech.
	 *
	 * @param array &$preferences Preferences array.
	 */
	static function addWikispeechEnable( &$preferences ) {
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
	static function addVoicePreferences( &$preferences ) {
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
	static function addSpeechRatePreferences( &$preferences ) {
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
	 * @param string $module
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
}
