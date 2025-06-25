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
 * @see UtteranceStore::flushUtterancesByPage()
 * @see FlushUtterancesFromStoreByPageIdJobQueue
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByPageIdJob extends Job {

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var LoggerInterface */
	private $logger;

	/** @var int */
	private $pageId;

	/** @var string|null */
	private $consumerUrl;

	/**
	 * @since 0.1.13 add service $utteranceStore to constructor
	 * @since 0.1.7
	 * @param Title $title
	 * @param array $params [ 'pageId' => int ]
	 * @param UtteranceStore $utteranceStore
	 */
	public function __construct( $title, $params, $utteranceStore ) {
		parent::__construct( 'flushUtterancesFromStoreByPageId', $title, $params );
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->pageId = $params['pageId'];
		$this->utteranceStore = $utteranceStore;
	}

	/**
	 * Executed by the job queue.
	 *
	 * @since 0.1.7
	 * @return bool success
	 */
	public function run() {
		$flushedUtterances = $this->utteranceStore->flushUtterancesByPage(
			$this->consumerUrl,
			$this->pageId
		);
		$this->logger->info(
			"Flushed {flushedUtterances} expired utterances from store.",
			[ 'flushedUtterances' => $flushedUtterances ]
		);
		return true;
	}
}
