<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsManager;
use PHPUnit\Framework\MockObject\Stub;

/**
 * @covers WikispeechHooks
 */
class WikispeechHooksTest extends MediaWikiTestCase {

	/** @var OutputPage */
	private $out;

	/** @var Stub|SkinTemplate */
	private $skin;

	/** @var HookContainer */
	private $hookContainer;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/** @var PermissionManager */
	private $permissionsManager;

	protected function setUp() : void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgWikispeechSpeechoidUrl' => 'https://server.domain',
			'wgWikispeechVoices' => [ 'en' => 'en-voice' ],
			'wgWikispeechNamespaces' => [ NS_MAIN ],
			'wgWikispeechKeyboardShortcuts' => 'shortcuts',
			'wgWikispeechContentSelector' => 'selector',
			'wgWikispeechSkipBackRewindsThreshold' => 'threshold',
			'wgWikispeechHelpPage' => 'help',
			'wgWikispeechFeedbackPage' => 'feedback',
			// content language
			'wgLanguageCode', 'en'
		] );
		$context = new RequestContext();
		// Interface language
		$context->setLanguage( 'en' );
		$this->out = new OutputPage( $context );
		$title = Title::newFromText( 'Page' );
		$this->out->setTitle( $title );
		$this->out->setRevisionId( $title->getLatestRevId() );

		$this->userOptionsManager = MediaWikiServices::getInstance()
			->getUserOptionsManager();
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechEnable', true );
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechShowPlayer', true );

		$this->permissionsManager = MediaWikiServices::getInstance()
			->getPermissionManager();
		$this->permissionsManager->overrideUserRightsForTesting(
			$this->out->getUser(),
			'wikispeech-listen'
		);
		$this->skin = $this->createStub( SkinTemplate::class );
		$this->skin->method( 'getOutput' )->willReturn( $this->out );
		$this->hookContainer = MediaWikiServices::getInstance()->getHookContainer();
	}

	public function testOnBeforePageDisplayLoadModules() {
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertTrue( $this->configLoaded() );
	}

	private function configLoaded() {
		$config = $this->out->getJsConfigVars();
		return isset( $config['wgWikispeechKeyboardShortcuts'] ) &&
			$config['wgWikispeechKeyboardShortcuts'] == 'shortcuts' &&
			isset( $config['wgWikispeechContentSelector'] ) &&
			$config['wgWikispeechContentSelector'] == 'selector' &&
			isset( $config['wgWikispeechSkipBackRewindsThreshold'] ) &&
			$config['wgWikispeechSkipBackRewindsThreshold'] == 'threshold' &&
			isset( $config['wgWikispeechHelpPage'] ) &&
			$config['wgWikispeechHelpPage'] == 'help' &&
			isset( $config['wgWikispeechFeedbackPage'] ) &&
			$config['wgWikispeechFeedbackPage'] == 'feedback';
	}

	public function testOnBeforePageDisplayDontLoadModulesIfWrongNamespace() {
		$this->out->setTitle( Title::newFromText( 'Page', NS_TALK ) );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfWikispeechDisabled() {
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechEnable', false );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfLackingRights() {
		$this->permissionsManager
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfServerUrlInvalid() {
		$this->setMwGlobals(
			'wgWikispeechSpeechoidUrl',
			'invalid-url'
		);
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfRevisionNotAccessible() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_invalidPageContentLanguage_dontLoadModule() {
		$this->setMwGlobals( 'wgLanguageCode', 'sv' );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_differentInterfaceLanguage_loadModule() {
		$this->out->getContext()->setLanguage( 'sv' );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotEmpty( $this->out->getModules() );
		$this->assertTrue( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_showPlayerNotSet_loadLoader() {
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechShowPlayer', false );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertContains(
			'ext.wikispeech.loader',
			$this->out->getModules()
		);
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertTrue( $this->configLoaded() );
	}

	public function testOnSkinTemplateNavigation_addListenTab() {
		// This stubbing is required to not get an error about Message::text().
		$this->skin->method( 'msg' )->willReturn(
			Message::newFromKey( 'wikispeech-listen' )
		);
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechShowPlayer', false );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_wikispeechDisabled_dontAddListenTab() {
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechEnable', false );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_lackingRights_dontAddListenTab() {
		$this->permissionsManager
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_serverUrlInvalid_dontAddListenTab() {
		$this->setMwGlobals(
			'wgWikispeechSpeechoidUrl',
			'invalid-url'
		);
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_wrongNamespace_dontAddListenTab() {
		$this->out->setTitle( Title::newFromText( 'Page', NS_TALK ) );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_revisionNotAccessible_dontAddListenTab() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_invalidPageContentLanguage_dontAddListenTab() {
		$this->setMwGlobals( 'wgLanguageCode', 'sv' );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}
}
