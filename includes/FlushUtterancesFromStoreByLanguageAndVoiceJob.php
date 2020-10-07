<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * @see UtteranceStore::flushUtterancesByLanguageAndVoice()
 * @see FlushUtterancesFromStoreByLanguageAndVoiceJobQueue
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByLanguageAndVoiceJob extends Job {

	/** @var LoggerInterface */
	private $logger;

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var string */
	private $language;

	/** @var string */
	private $voice;

	/**
	 * FlushUtterancesFromStoreByLanguageAndVoiceJob constructor.
	 *
	 * @since 0.1.7
	 * @param Title $title
	 * @param array $params [ 'language' => string, 'voice' => string|null ]
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'flushUtterancesFromStoreByLanguageAndVoice', $title, $params );
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->utteranceStore = new UtteranceStore();
		$this->language = $params['language'];
		$this->voice = $params['voice'];
	}

	/**
	 * Executed by the job queue.
	 *
	 * @since 0.1.7
	 * @return bool success
	 */
	public function run() {
		$flushedUtterances = $this->utteranceStore->flushUtterancesByLanguageAndVoice(
			$this->language,
			$this->voice
		);
		$this->logger->info(
			"Flushed {flushedUtterances} expired utterances from store.",
			[ 'flushedUtterances' => $flushedUtterances ]
		);
		return true;
	}

}
