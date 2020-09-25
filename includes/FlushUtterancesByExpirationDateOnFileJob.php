<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.7
 */
class FlushUtterancesByExpirationDateOnFileJob extends Job {

	/**
	 * @since 0.1.7
	 * @return bool success
	 */
	public function run() {
		$utteranceStore = new UtteranceStore();
		$utteranceStore->flushUtterancesByExpirationDateOnFile();
		return true;
	}

}
