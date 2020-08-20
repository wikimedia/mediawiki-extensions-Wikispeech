<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Class FlushOrphanedUtterancesFromFileBackendMaintenance
 *
 * Maintenance script to manually execute
 * {@link UtteranceStore::flushUtterancesByExpirationDateOnFile()}.
 * Used to clear out orphaned files (i.e. not tracked by utterance database).
 *
 * php extensions/Wikispeech/maintenance/flushUtterancesByExpirationDateOnFileFromFileBackend.php
 *
 * @since 0.1.5
 */
class FlushUtterancesByExpirationDateOnFileFromFileBackend extends Maintenance {

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var FlushUtterancesByExpirationDateOnFileFromFileBackendJobQueue */
	private $jobQueue;

	public function __construct() {
		parent::__construct();
		$this->utteranceStore = new UtteranceStore();
		$this->jobQueue = new FlushUtterancesByExpirationDateOnFileFromFileBackendJobQueue();
		$this->requireExtension( 'Wikispeech' );
		$this->addDescription( 'Flush orphaned utterances from file backend.' );
		$this->addOption(
			'force',
			'Forces flushing in current thread rather than queuing as job.',
			false,
			false,
			'f'
		);
	}

	/**
	 * @return bool success
	 */
	public function execute() {
		$force = $this->hasOption( 'force' );
		if ( $force ) {
			$this->utteranceStore->flushUtterancesByExpirationDateOnFileFromFileBackend();
		} else {
			$this->jobQueue->queueJob();
		}
		return true;
	}

}

$maintClass = FlushUtterancesByExpirationDateOnFileFromFileBackend::class;

require_once RUN_MAINTENANCE_IF_MAIN;
