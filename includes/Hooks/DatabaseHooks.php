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
	implements LoadExtensionSchemaUpdatesHook {
	/**
	 * Creates database tables.
	 *
	 * @param DatabaseUpdater $updater
	 * @since 0.1.8
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$path = dirname( __DIR__ ) . '/../sql';
		$updater->addExtensionTable(
			'wikispeech_utterance',
			"$path/$type/tables-generated.sql"
		);
		if ( $type === 'postgres' ) {
			$updater->modifyExtensionField(
				'wikispeech_utterance', 'wsu_date_stored', "$path/$type/patch-wikispeech_utterance-wsu_date_stored.sql"
			);
		}
	}
}
