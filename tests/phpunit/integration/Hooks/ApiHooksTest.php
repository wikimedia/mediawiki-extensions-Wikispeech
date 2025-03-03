<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ApiBase;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Wikispeech\Hooks\ApiHooks;
use MediaWikiIntegrationTestCase;
use User;

/**
 * @group Database
 * @covers \MediaWiki\Wikispeech\Hooks\ApiHooks
 */
class ApiHooksTest extends MediaWikiIntegrationTestCase {

	/** @var PermissionManager */
	private $permissionsManager;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var User */
	private $user;

	/** @var ApiHooks */
	private $apiHooks;

	/** @var ApiBase */
	private $module;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsManager = $this->getServiceContainer()->getPermissionManager();
		$configFactory = $this->getServiceContainer()->getConfigFactory();
		$this->requestFactory = $this->getServiceContainer()->getHttpRequestFactory();
		$this->user = $this->getServiceContainer()->getUserFactory()->newAnonymous();
		$this->apiHooks = new ApiHooks( $configFactory, $this->permissionsManager, $this->requestFactory );

		$this->module = $this->createStub( ApiBase::class );
		$this->module->method( 'getModuleName' )->willReturn( 'wikispeech-listen' );
	}

	/**
	 * If user lacks permissions, then return false
	 * @return void
	 */
	public function testOnApiCheckCanExecute_UserLacksPermission_returnFalse() {
		$mockMessage = null;

		$this->permissionsManager->overrideUserRightsForTesting( $this->user, [] );
		$result = $this->apiHooks->onApiCheckCanExecute( $this->module, $this->user, $mockMessage );

		$this->assertFalse( $result );
	}

	/**
	 * If user has permissions, then return true
	 * @return void
	 */
	public function testOnApiCheckCanExecute_UserHasPermission_returnTrue() {
		$mockMessage = null;

		$this->permissionsManager->overrideUserRightsForTesting( $this->user, [ 'wikispeech-listen' ] );
		$result = $this->apiHooks->onApiCheckCanExecute( $this->module, $this->user, $mockMessage );

		$this->assertTrue( $result );
	}
}
