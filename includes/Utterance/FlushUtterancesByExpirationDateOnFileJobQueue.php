<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use JobQueueGroup;
use MediaWiki\MediaWikiServices;
use Title;

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
		if ( method_exists( MediaWikiServices::class, 'getJobQueueGroup' ) ) {
			// MW 1.37+
			$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		} else {
			// @phan-suppress-next-line PhanUndeclaredStaticMethod
			$jobQueueGroup = JobQueueGroup::singleton();
		}
		$jobQueueGroup->push( new FlushUtterancesByExpirationDateOnFileJob(
				Title::newMainPage(),
				[]
			) );
	}
}
