<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * This used to be a single static method located in
 * {@link FlushUtterancesByExpirationDateOnFileFromFileBackendJob}
 * but due to mocking not allowed in static scope, this had to be refactored
 * to this new class. Feel free to suggest better solutions that can replace
 * this method.
 *
 * @since 0.1.5
 */
class FlushUtterancesByExpirationDateOnFileFromFileBackendJobQueue {
	/**
	 * Queues a job.
	 *
	 * @since 0.1.5
	 */
	public function queueJob() {
		JobQueueGroup::singleton()
			->push( new FlushUtterancesByExpirationDateOnFileFromFileBackendJob(
				Title::newMainPage(), null
			) );
	}
}
