<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Maintenance;

use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByExpirationJobQueue;
use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByLanguageAndVoiceJobQueue;
use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByPageIdJobQueue;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;

/** @var string MediaWiki installation path */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to manually execute
 * {@link UtteranceStore} flush methods.
 *
 * mwscript extensions/Wikispeech/maintenance/flushUtterances.php
 *
 * Be aware that you probably need to execute using mwscript, not php,
 * in order to be executed as user www-data, who has access to deleting files.
 *
 * @since 0.1.7
 */
class FlushUtterances extends Maintenance {

	/** @var UtteranceStore */
	private $utteranceStore;

	/** @var FlushUtterancesFromStoreByExpirationJobQueue */
	private $flushUtterancesFromStoreByExpirationJobQueue;

	/** @var FlushUtterancesFromStoreByLanguageAndVoiceJobQueue */
	private $flushUtterancesFromStoreByLanguageAndVoiceJobQueue;

	/** @var FlushUtterancesFromStoreByPageIdJobQueue */
	private $flushUtterancesFromStoreByPageIdJobQueue;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Wikispeech' );
		$this->addDescription( 'Flush utterances that expired with age; ' .
			'by language and optionally voice; or page id.' );
		$this->addOption(
			'expire',
			'Flush all utterances that have expired according to configuration.',
			false,
			false,
			'e'
		);
		$this->addOption(
			'language',
			'Flush all utterances with this language.',
			false,
			true,
			'l'
		);
		$this->addOption(
			'voice',
			'Flush all utterances with this voice (language required).',
			false,
			true,
			'v'
		);
		$this->addOption(
			'page',
			'Flush all utterances for all languages and voices on this page (id).',
			false,
			true,
			'p'
		);
		$this->addOption(
			'consumerUrl',
			'Flush this page on given remote wiki (page required).',
			false,
			true,
			'c'
		);
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
		// Non PHP core classes aren't available prior to this point,
		// i.e. we can't initialize the fields in the constructor,
		// and we have to be lenient for mocked instances set by tests.
		if ( !$this->utteranceStore ) {
			$this->utteranceStore = new UtteranceStore();
		}

		$flushedCount = 0;

		$expire = $this->hasOption( 'expire' );
		$language = $this->getOption( 'language', null );
		$voice = $this->getOption( 'voice', null );
		$pageId = $this->getOption( 'page', null );
		$consumerUrl = $this->getOption( 'consumerUrl', null );
		$force = $this->hasOption( 'force' );

		$supportedSetOfOptions = true;
		if ( !$expire && !$language && !$voice && !$pageId ) {
			$supportedSetOfOptions = false;
		} elseif ( $expire && ( $language || $voice || $pageId ) ) {
			$supportedSetOfOptions = false;
		} elseif ( $language && ( $expire || $pageId ) ) {
			$supportedSetOfOptions = false;
		} elseif ( $voice && ( $expire || $pageId ) ) {
			$supportedSetOfOptions = false;
		} elseif ( $pageId && ( $expire || $language || $voice ) ) {
			$supportedSetOfOptions = false;
		}
		if ( !$supportedSetOfOptions ) {
			$this->output( "Unsupported set of options!\n" );
			$this->showHelp();
			return false;
		}

		if ( $expire ) {
			if ( $force ) {
				$flushedCount = $this->utteranceStore
					->flushUtterancesByExpirationDate(
						$this->utteranceStore->getWikispeechUtteranceExpirationTimestamp()
					);
			} else {
				if ( !$this->flushUtterancesFromStoreByExpirationJobQueue ) {
					$this->flushUtterancesFromStoreByExpirationJobQueue
						= new FlushUtterancesFromStoreByExpirationJobQueue();
				}

				$this->flushUtterancesFromStoreByExpirationJobQueue
					->queueJob();
			}
		} elseif ( $language ) {
			if ( $force ) {
				$flushedCount = $this->utteranceStore
					->flushUtterancesByLanguageAndVoice( $language, $voice );
			} else {
				if ( !$this->flushUtterancesFromStoreByLanguageAndVoiceJobQueue ) {
					$this->flushUtterancesFromStoreByLanguageAndVoiceJobQueue
						= new FlushUtterancesFromStoreByLanguageAndVoiceJobQueue();
				}

				$this->flushUtterancesFromStoreByLanguageAndVoiceJobQueue
					->queueJob( $language, $voice );
			}
		} elseif ( $pageId ) {
			if ( $force ) {
				$flushedCount = $this->utteranceStore
					->flushUtterancesByPage( $consumerUrl, intval( $pageId ) );
			} else {
				if ( !$this->flushUtterancesFromStoreByPageIdJobQueue ) {
					$this->flushUtterancesFromStoreByPageIdJobQueue
						= new FlushUtterancesFromStoreByPageIdJobQueue();
				}

				$this->flushUtterancesFromStoreByPageIdJobQueue
					->queueJob( intval( $pageId ) );
			}
		} else {
			// Fallback in case of future bad code in supported set of options.
			$this->output( "Unsupported set of options! (This might be a developer error.)\n" );
			$this->showHelp();
			return false;
		}

		if ( $force ) {
			$this->output( "Flushed $flushedCount utterances.\n" );
		} else {
			$this->output( 'Flush job has been queued and will be executed ' .
				"in accordance with your MediaWiki configuration.\n" );
		}

		return true;
	}

}

/** @var string This class, required to start via Maintenance. */
$maintClass = FlushUtterances::class;

require_once RUN_MAINTENANCE_IF_MAIN;
