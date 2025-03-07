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

	/** @var LoggerInterface */
	private $logger;

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var int */
	private $pageId;

	/** @var string|null */
	private $consumerUrl;

	/**
	 * @since 0.1.7
	 * @param Title $title
	 * @param array $params [ 'pageId' => int ]
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'flushUtterancesFromStoreByPageId', $title, $params );
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->utteranceStore = new UtteranceStore();
		$this->pageId = $params['pageId'];
		$this->consumerUrl = $params['consumerUrl'];
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
