<?php

namespace MediaWiki\Wikispeech\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ApiBase;
use ApiMain;
use ApiMessage;
use Config;
use ConfigFactory;
use IApiMessage;
use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Wikispeech\ConfigurationValidator;
use Message;
use Psr\Log\LoggerInterface;
use User;

/**
 * @since 0.1.11
 */

class ApiHooks implements ApiBeforeMainHook, ApiCheckCanExecuteHook {

	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $config;

	/** @var ConfigurationValidator */
	private $configValidator;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/**
	 * @param ConfigFactory $configFactory
	 * @param PermissionManager $permissionManager
	 * @param HttpRequestFactory $requestFactory
	 */
	public function __construct(
		ConfigFactory $configFactory,
		PermissionManager $permissionManager,
		HttpRequestFactory $requestFactory
	) {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->config = $configFactory->makeConfig( 'wikispeech' );
		$this->configValidator = new ConfigurationValidator( $this->config, $this->logger );
		$this->permissionManager = $permissionManager;
		$this->requestFactory = $requestFactory;
	}

	/**
	 * Calls configuration validation for logging purposes on API calls.
	 *
	 * @param ApiMain &$main
	 * @return bool|void
	 */
	public function onApiBeforeMain( &$main ) {
		$this->configValidator->validateConfiguration();
	}

	/**
	 * Check if the user is allowed to use an API module.
	 *
	 * @param ApiBase $module
	 * @param User $user
	 * @param IApiMessage|Message|string|array &$message
	 * @return bool
	 */
	public function onApiCheckCanExecute( $module, $user, &$message ): bool {
		if (
			$module->getModuleName() === 'wikispeech-listen' &&
			!$this->permissionManager->userHasRight( $user, 'wikispeech-listen' )
		) {
			$message = ApiMessage::create( 'apierror-wikispeech-listen-notallowed' );
			return false;
		}
		return true;
	}
}
