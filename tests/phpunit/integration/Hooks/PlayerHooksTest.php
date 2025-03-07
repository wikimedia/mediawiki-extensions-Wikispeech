<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use Mediawiki\Title\Title;
use MediaWiki\User\UserOptionsManager;
use MediaWikiIntegrationTestCase;
use Message;
use OutputPage;
use PHPUnit\Framework\MockObject\Stub;
use RequestContext;
use SkinTemplate;

/**
 * @group Database
 * @covers \MediaWiki\Wikispeech\Hooks\PlayerHooks
 */
class PlayerHooksTest extends MediaWikiIntegrationTestCase {

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

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'WikispeechSpeechoidUrl' => 'https://server.domain',
			'WikispeechVoices' => [ 'en' => 'en-voice' ],
			'WikispeechNamespaces' => [ NS_MAIN ],
			'WikispeechKeyboardShortcuts' => 'shortcuts',
			'WikispeechContentSelector' => 'selector',
			'WikispeechSkipBackRewindsThreshold' => 'threshold',
			'WikispeechHelpPage' => 'help',
			'WikispeechFeedbackPage' => 'feedback',
			// content language
			MainConfigNames::LanguageCode, 'en'
		] );
		$context = new RequestContext();
		// Interface language
		$context->setLanguage( 'en' );
		$this->out = new OutputPage( $context );
		$title = Title::newFromText( 'Page' );
		$this->out->setTitle( $title );
		$this->out->setRevisionId( $title->getLatestRevId() );

		$this->userOptionsManager = $this->getServiceContainer()
			->getUserOptionsManager();
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechEnable', true );
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechShowPlayer', true );

		$this->permissionsManager = $this->getServiceContainer()
			->getPermissionManager();
		$this->permissionsManager->overrideUserRightsForTesting(
			$this->out->getUser(),
			'wikispeech-listen'
		);
		$this->skin = $this->createStub( SkinTemplate::class );
		$this->skin->method( 'getOutput' )->willReturn( $this->out );
		$this->hookContainer = $this->getServiceContainer()->getHookContainer();
	}

	public function testOnBeforePageDisplay_loadModulesAndConfig() {
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

	public function testOnBeforePageDisplay_wrongNamespace_dontLoadModulesOrConfig() {
		$this->out->setTitle( Title::newFromText( 'Page', NS_TALK ) );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_wikispeechDisabled_dontLoadModulesOrConfig() {
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechEnable', false );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_userLacksRights_dontLoadModulesOrConfig() {
		$this->permissionsManager
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_serverUrlInvalid_dontLoadModulesOrConfig() {
		$this->overrideConfigValue( 'WikispeechSpeechoidUrl', 'invalid-url' );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_revisionNotAccessible_dontLoadModulesOrConfig() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_invalidPageContentLanguage_dontLoadModule() {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'sv' );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_differentInterfaceLanguage_loadModule() {
		$this->out->getContext()->setLanguage( 'sv' );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertTrue( $this->configLoaded() );
	}

	public function testOnBeforePageDisplay_actionIsntView_dontLoadModule() {
		$this->out->getRequest()->setVal( 'action', 'not-view' );
		$this->hookContainer->run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertNotContains( 'ext.wikispeech', $this->out->getModules() );
		$this->assertNotContains( 'ext.wikispeech.loader', $this->out->getModules() );
		$this->assertFalse( $this->configLoaded() );
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

	public function testOnSkinTemplateNavigation__Universal_addListenTab() {
		// This stubbing is required to not get an error about Message::text().
		$this->skin->method( 'msg' )->willReturn(
			Message::newFromKey( 'wikispeech-listen' )
		);
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechShowPlayer', false );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation__Universal_wikispeechDisabled_dontAddListenTab() {
		$this->userOptionsManager
			->setOption( $this->out->getUser(), 'wikispeechEnable', false );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation__Universal_lackingRights_dontAddListenTab() {
		$this->permissionsManager
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation__Universal_serverUrlInvalid_dontAddListenTab() {
		$this->overrideConfigValue( 'WikispeechSpeechoidUrl', 'invalid-url' );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation__Universal_wrongNamespace_dontAddListenTab() {
		$this->out->setTitle( Title::newFromText( 'Page', NS_TALK ) );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation__Universal_revisionNotAccessible_dontAddListenTab() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}

	public function testOnSkinTemplateNavigation__Universal_invalidPageContentLanguage_dontAddListenTab() {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'sv' );
		$links = [ 'actions' => [] ];
		$this->hookContainer->run( 'SkinTemplateNavigation::Universal', [ $this->skin, &$links ] );
		$this->assertArrayNotHasKey( 'listen', $links['actions'] );
	}
}
