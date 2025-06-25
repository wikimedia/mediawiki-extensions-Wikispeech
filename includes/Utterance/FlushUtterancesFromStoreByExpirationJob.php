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
 * @see UtteranceStore::flushUtterancesByExpirationDate()
 * @see FlushUtterancesFromStoreByExpirationJobQueue
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByExpirationJob extends Job {

	/** @var LoggerInterface */
	private $logger;

	/** @var UtteranceStore */
	private $utteranceStore;

	/**
	 * @since 0.1.13 add service $utteranceStore to constructor
	 * @since 0.1.7
	 * @param Title $title
	 * @param array|null $params Ignored
	 * @param UtteranceStore $utteranceStore
	 */
	public function __construct( $title, $params, $utteranceStore ) {
		parent::__construct( 'flushUtterancesFromStoreByExpiration', $title, $params );
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->utteranceStore = $utteranceStore;
	}

	/**
	 * Executed by the job queue.
	 *
	 * @since 0.1.7
	 * @return bool success
	 */
	public function run() {
		$flushedUtterances = $this->utteranceStore->flushUtterancesByExpirationDate(
			$this->utteranceStore->getWikispeechUtteranceExpirationTimestamp()
		);
		$this->logger->info( __METHOD__ . ': ' .
			"Flushed {flushedUtterances} expired utterances from store.",
			[ 'flushedUtterances' => $flushedUtterances ]
		);
		// @note consider flushing a configurable limited batch of utterances,
		// and queue a new job immediately here in case we flushed exactly that
		// amount of utterances.
		// Also see https://phabricator.wikimedia.org/T255104
		return true;
	}

}
