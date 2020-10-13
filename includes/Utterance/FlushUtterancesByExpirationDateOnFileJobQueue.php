<?php

namespace MediaWiki\Wikispeech\Utterance;

use JobQueueGroup;
use Title;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * This used to be a single static method located in
 * {@link FlushUtterancesByExpirationDateOnFileJob}
 * but due to mocking not allowed in static scope, this had to be refactored
 * to this new class. Feel free to suggest better solutions that can replace
 * this method.
 *
 * @since 0.1.7
 */
class FlushUtterancesByExpirationDateOnFileJobQueue {
	/**
	 * Queues a job.
	 *
	 * @since 0.1.7
	 */
	public function queueJob() {
		JobQueueGroup::singleton()
			->push( new FlushUtterancesByExpirationDateOnFileJob(
				Title::newMainPage(), null
			) );
	}
}
