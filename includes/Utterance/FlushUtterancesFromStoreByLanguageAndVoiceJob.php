<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Job;
use MediaWiki\Logger\LoggerFactory;
use Mediawiki\Title\Title;
use Psr\Log\LoggerInterface;

/**
 * @see UtteranceStore::flushUtterancesByLanguageAndVoice()
 * @see FlushUtterancesFromStoreByLanguageAndVoiceJobQueue
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByLanguageAndVoiceJob extends Job {

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var LoggerInterface */
	private $logger;

	/** @var string */
	private $language;

	/** @var string */
	private $voice;

	/**
	 * @since 0.1.13 add service $utteranceStore to constructor
	 * @since 0.1.7
	 * @param Title $title
	 * @param array $params [ 'language' => string, 'voice' => string|null ]
	 * @param UtteranceStore $utteranceStore
	 */
	public function __construct( $title, $params, $utteranceStore ) {
		parent::__construct( 'flushUtterancesFromStoreByLanguageAndVoice', $title, $params );
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->language = $params['language'];
		$this->voice = $params['voice'];
		$this->utteranceStore = $utteranceStore;
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
