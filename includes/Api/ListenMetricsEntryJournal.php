<?php

namespace MediaWiki\Wikispeech\Api;

/**
 * @file
 * @ingroup API
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.10
 */
interface ListenMetricsEntryJournal {

	/**
	 * Appends an entry to the current journal.
	 *
	 * @since 0.1.10
	 * @param ListenMetricsEntry $entry
	 */
	public function appendEntry( ListenMetricsEntry $entry ): void;

	/**
	 * Somehow archives the current journal. What this means depends on the implementation.
	 *
	 * @since 0.1.10
	 * @return bool Whether or not the current journal was archived. If false, see log.
	 */
	public function archiveCurrentMetricsJournal(): bool;
}
