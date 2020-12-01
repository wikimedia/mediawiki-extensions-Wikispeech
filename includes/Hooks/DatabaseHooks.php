<?php

namespace MediaWiki\Wikispeech\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * @since 0.1.8
 */
class DatabaseHooks
	implements LoadExtensionSchemaUpdatesHook
{
	/**
	 * Creates database tables.
	 *
	 * @since 0.1.8
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'wikispeech_utterance',
			__DIR__ . '/../../sql/wikispeech_utterance_v1.sql'
		);
	}
}
