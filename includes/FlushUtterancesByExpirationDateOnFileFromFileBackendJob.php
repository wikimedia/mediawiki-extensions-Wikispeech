<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.5
 */
class FlushUtterancesByExpirationDateOnFileFromFileBackendJob extends Job {

	/**
	 * @return bool success
	 */
	public function run() {
		$utteranceStore = new UtteranceStore();
		$utteranceStore->flushUtterancesByExpirationDateOnFileFromFileBackend();
		return true;
	}

}
