<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;

/**
 * @covers WikispeechHooks
 */
class WikispeechHooksTest extends MediaWikiTestCase {
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
		$this->out->getUser()->setOption( 'wikispeechEnable', true );
		$this->out->getUser()->setOption( 'wikispeechShowPlayer', true );
		MediaWikiServices::getInstance()
			->getPermissionManager()
			->overrideUserRightsForTesting(
				$this->out->getUser(),
				'wikispeech-listen'
			);
		$this->skin = $this->createStub( SkinTemplate::class );
		$this->skin->method( 'getOutput' )->willReturn( $this->out );
	}

	public function testOnBeforePageDisplayLoadModules() {
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
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
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfWikispeechDisabled() {
		$this->out->getUser()->setOption( 'wikispeechEnable', false );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfLackingRights() {
		MediaWikiServices::getInstance()
			->getPermissionManager()
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfServerUrlInvalid() {
		$this->setMwGlobals(
			'wgWikispeechSpeechoidUrl',
			'invalid-url'
		);
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfRevisionNotAccessible() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_invalidPageContentLanguage_dontLoadModule() {
		$this->setMwGlobals( 'wgLanguageCode', 'sv' );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_differentInterfaceLanguage_loadModule() {
		$this->out->getContext()->setLanguage( 'sv' );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotEmpty( $this->out->getModules() );
		$this->assertTrue( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_showPlayerNotSet_loadLoader() {
		$this->out->getUser()->setOption( 'wikispeechShowPlayer', false );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
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
		$this->out->getUser()->setOption( 'wikispeechShowPlayer', false );
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_wikispeechDisabled_dontAddListenTab() {
		$this->out->getUser()->setOption( 'wikispeechEnable', false );
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_lackingRights_dontAddListenTab() {
		MediaWikiServices::getInstance()
			->getPermissionManager()
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_serverUrlInvalid_dontAddListenTab() {
		$this->setMwGlobals(
			'wgWikispeechSpeechoidUrl',
			'invalid-url'
		);
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_wrongNamespace_dontAddListenTab() {
		$this->out->setTitle( Title::newFromText( 'Page', NS_TALK ) );
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_revisionNotAccessible_dontAddListenTab() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation_invalidPageContentLanguage_dontAddListenTab() {
		$this->setMwGlobals( 'wgLanguageCode', 'sv' );
		$links = [ 'actions' => [] ];
		Hooks::run( 'SkinTemplateNavigation', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}
}
