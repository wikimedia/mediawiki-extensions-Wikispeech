<?php

namespace MediaWiki\Wikispeech\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Action;
use ApiBase;
use ApiMain;
use ApiMessage;
use Config;
use ConfigFactory;
use Exception;
use IApiMessage;
use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\SkinTemplateNavigationHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\VoiceHandler;
use Message;
use OutputPage;
use Psr\Log\LoggerInterface;
use Skin;
use SkinTemplate;
use User;
use WANObjectCache;

/**
 * @since 0.1.8
 */
class ApiHooks implements
	ApiBeforeMainHook,
	BeforePageDisplayHook,
	ResourceLoaderGetConfigVarsHook,
	GetPreferencesHook,
	ApiCheckCanExecuteHook,
	SkinTemplateNavigationHook
{
	/** @var Config */
	private $config;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var LoggerInterface */
	private $logger;

	/** @var WANObjectCache */
	private $mainWANObjectCache;

	/** @var LanguageFactory */
	private $languageFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/**
	 * @since 0.1.8
	 * @param ConfigFactory $configFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param WANObjectCache $mainWANObjectCache
	 * @param LanguageFactory $languageFactory
	 * @param PermissionManager $permissionManager
	 * @param HttpRequestFactory $requestFactory
	 */
	public function __construct(
		ConfigFactory $configFactory,
		UserOptionsLookup $userOptionsLookup,
		WANObjectCache $mainWANObjectCache,
		LanguageFactory $languageFactory,
		PermissionManager $permissionManager,
		HttpRequestFactory $requestFactory
	) {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->config = $configFactory->makeConfig( 'wikispeech' );
		$this->userOptionsLookup = $userOptionsLookup;
		$this->mainWANObjectCache = $mainWANObjectCache;
		$this->languageFactory = $languageFactory;
		$this->permissionManager = $permissionManager;
		$this->requestFactory = $requestFactory;
	}

	/**
	 * Investigates whether or not configuration is valid.
	 *
	 * Writes all invalid configuration entries to the log.
	 *
	 * @since 0.1.8
	 * @return bool true if all configuration passes validation
	 */
	private function validateConfiguration() {
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
					'\'WikispeechUtteranceFileStore\' is not a string value.'
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
	 * @since 0.1.8
	 * @param OutputPage $out The OutputPage object.
	 * @param Skin $skin Skin object that will be used to generate the page,
	 *  added in MediaWiki 1.13.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->shouldWikispeechRun( $out ) ) {
			return;
		}
		$showPlayer = $this->userOptionsLookup->getOption(
			$out->getUser(), 'wikispeechShowPlayer'
		);
		if ( $showPlayer ) {
			$this->logger->info( __METHOD__ . ': Loading player.' );
			$out->addModules( [ 'ext.wikispeech' ] );
		} else {
			$this->logger->info( __METHOD__ . ': Adding option to load player.' );
			$out->addModules( [ 'ext.wikispeech.loader' ] );
		}
		$out->addJsConfigVars( [
			'wgWikispeechKeyboardShortcuts' => $this->config->get( 'WikispeechKeyboardShortcuts' ),
			'wgWikispeechContentSelector' => $this->config->get( 'WikispeechContentSelector' ),
			'wgWikispeechSkipBackRewindsThreshold' =>
				$this->config->get( 'WikispeechSkipBackRewindsThreshold' ),
			'wgWikispeechHelpPage' => $this->config->get( 'WikispeechHelpPage' ),
			'wgWikispeechFeedbackPage' => $this->config->get( 'WikispeechFeedbackPage' )
		] );
	}

	/**
	 * Checks if Wikispeech should run.
	 *
	 * Returns true if all of the following are true:
	 * * User has enabled Wikispeech in the settings
	 * * User is allowed to listen to pages
	 * * Wikispeech configuration is valid
	 * * Wikispeech is enabled for the page's namespace
	 * * Revision is current
	 * * Page's language is enabled for Wikispeech
	 * * The action is "view"
	 *
	 * @since 0.1.8
	 * @param OutputPage $out
	 * @return bool
	 */
	private function shouldWikispeechRun( OutputPage $out ) {
		$wikispeechEnabled = $this->userOptionsLookup
			->getOption( $out->getUser(), 'wikispeechEnable' );
		if ( !$wikispeechEnabled ) {
			$this->logger->info( __METHOD__ . ': Not loading Wikispeech: disabled by user.' );
			return false;
		}

		$userIsAllowed = $this->permissionManager
			->userHasRight( $out->getUser(), 'wikispeech-listen' );
		if ( !$userIsAllowed ) {
			$this->logger->info( __METHOD__ .
				': Not loading Wikispeech: user lacks right "wikispeech-listen".' );
			return false;
		}

		if ( !$this->validateConfiguration() ) {
			$this->logger->info( __METHOD__ . ': Not loading Wikispeech: config invalid.' );
			return false;
		}

		$namespace = $out->getTitle()->getNamespace();
		$validNamespaces = $this->config->get( 'WikispeechNamespaces' );
		if ( !in_array( $namespace, $validNamespaces ) ) {
			$this->logger->info( __METHOD__ . ': Not loading Wikispeech: unsupported namespace.' );
			return false;
		}

		if ( !$out->isRevisionCurrent() ) {
			$this->logger->info( __METHOD__ . ': Not loading Wikispeech: non-current revision.' );
			return false;
		}

		if ( $namespace == NS_MEDIA || $namespace < 0 ) {
			// cannot get pageContentLanguage of e.g. a Special page or a
			// virtual page. These should all use the interface language.
			$pageContentLanguage = $out->getLanguage();
		} else {
			$pageContentLanguage = $out->getWikiPage()->getTitle()->getPageLanguage();
		}
		$validLanguages = array_keys( $this->config->get( 'WikispeechVoices' ) );
		if ( !in_array( $pageContentLanguage->getCode(), $validLanguages ) ) {
			$this->logger->info( __METHOD__ . ': Not loading Wikispeech: unsupported language.' );
			return false;
		}

		$actionName = Action::getActionName( $out );
		if ( $actionName !== 'view' ) {
			$this->logger->info( __METHOD__ . ': Not loading Wikispeech: unsupported action.' );
			return false;
		}

		return true;
	}

	/**
	 * Calls configuration validation for logging purposes on API calls,
	 * but doesn't stop the use of the API due to invalid configuration.
	 * Generally a user would not call the API at this point as the module
	 * wouldn't actually have been added in onBeforePageDisplay.
	 *
	 * @since 0.1.8
	 * @param ApiMain &$main
	 * @return bool|void
	 */
	public function onApiBeforeMain( &$main ) {
		$this->validateConfiguration();
	}

	/**
	 * Conditionally register static configuration variables for the
	 * ext.wikispeech module only if that module is loaded.
	 *
	 * @since 0.1.8
	 * @param array &$vars The array of static configuration variables.
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgWikispeechSpeechoidUrl'] = $config->get( 'WikispeechSpeechoidUrl' );
		$vars['wgWikispeechNamespaces'] = $config->get( 'WikispeechNamespaces' );
	}

	/**
	 * Add Wikispeech options to Special:Preferences.
	 *
	 * @since 0.1.8
	 * @param User $user current User object.
	 * @param array &$preferences Preferences array.
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$speechoidConnector = new SpeechoidConnector(
			$this->config,
			$this->requestFactory
		);
		$voiceHandler = new VoiceHandler(
			$this->logger,
			$this->config,
			$speechoidConnector,
			$this->mainWANObjectCache
		);
		$preferences['wikispeechEnable'] = [
			'type' => 'toggle',
			'label-message' => 'prefs-wikispeech-enable',
			'section' => 'wikispeech'
		];
		$preferences['wikispeechShowPlayer'] = [
			'type' => 'toggle',
			'label-message' => 'prefs-wikispeech-show-player',
			'section' => 'wikispeech'
		];
		$this->addVoicePreferences( $preferences, $voiceHandler );
		$this->addSpeechRatePreferences( $preferences );
	}

	/**
	 * Add preferences for selecting voices per language.
	 *
	 * @since 0.1.8
	 * @param array &$preferences Preferences array.
	 * @param VoiceHandler $voiceHandler
	 */
	private function addVoicePreferences( &$preferences, $voiceHandler ) {
		$wikispeechVoices = $this->config->get( 'WikispeechVoices' );
		foreach ( $wikispeechVoices as $language => $voices ) {
			$languageKey = 'wikispeechVoice' . ucfirst( $language );
			$mwLanguage = $this->languageFactory->getLanguage( 'en' );
			$languageName = $mwLanguage->getVariantname( $language );
			$options = [];
			try {
				$defaultVoice = $voiceHandler->getDefaultVoice( $language );
				$options["Default ($defaultVoice)"] = '';
			} catch ( Exception $e ) {
				$options["Default"] = '';
			}
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
	 * @since 0.1.8
	 * @param array &$preferences Preferences array.
	 */
	private function addSpeechRatePreferences( &$preferences ) {
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
	 * @since 0.1.8
	 * @param ApiBase $module
	 * @param User $user
	 * @param IApiMessage|Message|string|array &$message
	 * @return bool
	 */
	public function onApiCheckCanExecute( $module, $user, &$message ) {
		if (
			$module->getModuleName() == 'wikispeech-listen' &&
			!$this->permissionManager->userHasRight( $user, 'wikispeech-listen' )
		) {
			$message = ApiMessage::create(
				'apierror-wikispeech-listen-notallowed'
			);
			return false;
		}
		return true;
	}

	/**
	 * Add tab for activating Wikispeech player.
	 *
	 * @since 0.1.8
	 * @param SkinTemplate $skinTemplate The skin template on which
	 *  the UI is built.
	 * @param array &$links Navigation links.
	 */
	public function onSkinTemplateNavigation( $skinTemplate, &$links ): void {
		$out = $skinTemplate->getOutput();
		if ( $this->shouldWikispeechRun( $out ) ) {
			$links['actions']['listen'] = [
				'class' => 'ext-wikispeech-listen',
				'text' => $skinTemplate->msg( 'wikispeech-listen' )->text(),
				'href' => 'javascript:void(0)'
			];
		}
	}

	/**
	 * Get default user options when used as a producer
	 *
	 * Used when a consumer loads the gadget module.
	 *
	 * @since 0.1.9
	 * @return array
	 */
	public static function getDefaultUserOptions() {
		global $wgDefaultUserOptions;
		$wikispeechOptions = array_filter(
			$wgDefaultUserOptions,
			static function ( $key ) {
				// Only add options starting with "wikispeech".
				return strpos( $key, 'wikispeech' ) === 0;
			},
			ARRAY_FILTER_USE_KEY
		);
		return $wikispeechOptions;
	}
}
