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
 * {@link FlushUtterancesFromStoreByLanguageAndVoiceJob}
 * but due to mocking not allowed in static scope, this had to be refactored
 * to this new class. Feel free to suggest better solutions that can replace
 * this method.
 *
 * @see UtteranceStore::flushUtterancesByLanguageAndVoice()
 * @see FlushUtterancesFromStoreByLanguageAndVoiceJob
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByLanguageAndVoiceJobQueue {

	/**
	 * Queues a job.
	 *
	 * @since 0.1.7
	 * @param string $language
	 * @param string|null $voice
	 */
	public function queueJob( $language, $voice = null ) {
		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$jobQueueGroup->push(
			new FlushUtterancesFromStoreByLanguageAndVoiceJob(
				Title::newMainPage(),
				[
					'language' => $language,
					'voice' => $voice
				]
			)
		);
	}
}
