<?php

namespace MediaWiki\Wikispeech\Hooks;

use Action;
use Config;
use ConfigFactory;
use Exception;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Wikispeech\ConfigurationValidator;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWiki\Wikispeech\VoiceHandler;
use OutputPage;
use Psr\Log\LoggerInterface;
use Skin;
use SkinTemplate;
use User;
use WANObjectCache;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 * @since 0.1.11
 */
class PlayerHooks implements
	BeforePageDisplayHook,
	GetPreferencesHook,
	SkinTemplateNavigation__UniversalHook
{
	/** @var Config */
	private $config;

	/** @var ConfigurationValidator */
	private $configValidator;

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
	 * @since 0.1.11
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
		$this->configValidator = new ConfigurationValidator( $this->config, $this->logger );
		$this->userOptionsLookup = $userOptionsLookup;
		$this->mainWANObjectCache = $mainWANObjectCache;
		$this->languageFactory = $languageFactory;
		$this->permissionManager = $permissionManager;
		$this->requestFactory = $requestFactory;
	}

	/**
	 * Hook for BeforePageDisplay.
	 *
	 * Enables JavaScript.
	 *
	 * @since 0.1.11
	 * @param OutputPage $out The OutputPage object.
	 * @param Skin $skin Skin object that will be used to generate the page,
	 *  added in MediaWiki 1.13.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->shouldWikispeechRun( $out ) ) {
			return;
		}
		$flushUtterances = $this->config->get( 'WikispeechFlushUtterances' );
		$pageId = $out->getTitle()->getArticleID();
		if ( $flushUtterances ) {
			$utteranceStore = new UtteranceStore();
			$utteranceStore->flushUtterancesByPage( null, $pageId );
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
	 * @since 0.1.11
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

		if ( !$this->configValidator->validateConfiguration() ) {
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
			$pageContentLanguage = $out->getTitle()->getPageLanguage();
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
	 *  Conditionally register static configuration variables for the
	 * ext.wikispeech module only if that module is loaded.
	 *
	 * @since 0.1.11
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
	 * @since 0.1.11
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
	 * @since 0.1.11
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
	 * @since 0.1.11
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
	 * Add tab for activating Wikispeech player.
	 *
	 * @since 0.1.11
	 * @param SkinTemplate $skinTemplate The skin template on which
	 *  the UI is built.
	 * @param array &$links Navigation links.
	 */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void { // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
		$out = $skinTemplate->getOutput();
		if ( $this->shouldWikispeechRun( $out ) ) {
			$links['actions']['listen'] = [
				'class' => 'ext-wikispeech-listen',
				'text' => $skinTemplate->msg( 'wikispeech-listen' )->text(),
				'href' => 'javascript:void(0)'
			];
		}
	}
}
