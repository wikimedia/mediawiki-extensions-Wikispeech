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
				'wgWikispeechVoices' => [ 'en' => 'en-voice' ]
		] );
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$this->out = new OutputPage( $context );
		$title = Title::newFromText( 'Page' );
		$this->out->setTitle( $title );
		$this->out->setRevisionId( $title->getLatestRevId() );
		$this->out->getUser()->setOption( 'wikispeechEnable', true );
		MediaWikiServices::getInstance()
			->getPermissionManager()
			->overrideUserRightsForTesting(
				$this->out->getUser(),
				'wikispeech-listen'
			);
		$skinFactory = MediaWikiServices::getInstance()->getSkinFactory();
		$this->skin = $skinFactory->makeSkin( 'fallback' );
	}

	public function testOnBeforePageDisplayLoadModules() {
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertContains( 'ext.wikispeech', $this->out->getModules() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfWrongNamespace() {
		$this->out->setTitle( Title::newFromText( 'Page', NS_TALK ) );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfDisabled() {
		$this->out->getUser()->setOption( 'wikispeechEnable', false );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfLackingRights() {
		MediaWikiServices::getInstance()
			->getPermissionManager()
			->overrideUserRightsForTesting( $this->out->getUser(), [] );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfServerUrlInvalid() {
		$this->setMwGlobals(
			'wgWikispeechSpeechoidUrl',
			'invalid-url'
		);
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfRevisionNotAccessible() {
		$inaccessibleRevisionId = $this->out->getTitle()->getLatestRevId() - 1;
		$this->out->setRevisionId( $inaccessibleRevisionId );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
	}

	public function testOnBeforePageDisplayDontLoadModulesIfInvalidPageLanguage() {
		$this->out->getContext()->setLanguage( 'sv' );
		Hooks::run( 'BeforePageDisplay', [ &$this->out, $this->skin ] );
		$this->assertEmpty( $this->out->getModules() );
	}
}
