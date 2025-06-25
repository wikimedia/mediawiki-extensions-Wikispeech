<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;
use Mediawiki\Title\Title;

/**
 * This used to be a single static method located in
 * {@link FlushUtterancesFromStoreByPageIdJob}
 * but due to mocking not allowed in static scope, this had to be refactored
 * to this new class. Feel free to suggest better solutions that can replace
 * this method.
 *
 * @see UtteranceStore::flushUtterancesByPage()
 * @see FlushUtterancesFromStoreByPageIdJob
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByPageIdJobQueue {
	/**
	 * Queues a job.
	 *
	 * @since 0.1.7
	 * @param int $pageId
	 */
	public function queueJob( $pageId ) {
		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$jobQueueGroup->push(
			new FlushUtterancesFromStoreByPageIdJob(
				Title::newMainPage(),
				[ 'pageId' => $pageId ],
				MediaWikiServices::getInstance()->getService( 'Wikispeech.UtteranceStore' )
			)
		);
	}
}
