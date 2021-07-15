<?php

namespace MediaWiki\Wikispeech\Api;

/**
 * @file
 * @ingroup API
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use InvalidArgumentException;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Adds journal entries as single lines of JSON in a file on the filesystem.
 *
 * @since 0.1.10
 */
class ListenMetricsEntryFileJournal implements ListenMetricsEntryJournal {

	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $config;

	/**
	 * @since 0.1.10
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->config = $config;
	}

	/**
	 * @since 0.1.10
	 * @param ListenMetricsEntry $entry
	 */
	public function appendEntry( ListenMetricsEntry $entry ): void {
		/**
		 * @var float
		 * One second sounds like much, but there is no queuing here, it's optimistic locking.
		 * We really need to give it a bit of time in case of really heavy user load.
		 */
		$lockTimeoutSeconds = 1;
		$metricsJournalFile = $this->getCurrentMetricsJournalFile();
		$metricsSerializer = new ListenMetricsEntrySerializer();
		$json = json_encode( $metricsSerializer->serialize( $entry ) );
		$fh = fopen( $metricsJournalFile, 'a' );
		if ( $this->flockWithTimeout( $fh, LOCK_EX, $lockTimeoutSeconds * 1000000 ) ) {
			try {
				fwrite( $fh, $json );
				fwrite( $fh, "\n" );
				fflush( $fh );
			} finally {
				flock( $fh, LOCK_UN );
			}
		} else {
			$this->logger->warning( 'Unable to get write lock on {metricsJournalFile}', [
				'file' => $metricsJournalFile
			] );
		}
		fclose( $fh );

		// @todo switch to database?
		// $crud = new ListenMetricsEntryCrud( $this->dbLoadBalancer );
		// $crud->create( $this->listenMetricEntry );
	}

	/**
	 * In case of an empty or missing journal file, the function returns false.
	 * Attempts to rename current metrics journal file with an appended ISO8601 date/timestamp.
	 * If this is successful the function returns true.
	 * It then attempts to gzip-compress and delete the uncompressed file.
	 * Even if any of these actions fails the method will return true
	 * but it will produce warnings in the log.
	 *
	 * @since 0.1.10
	 * @return bool Whether or not the current journal was archived. If false, see log.
	 */
	public function archiveCurrentMetricsJournal(): bool {
		$currentMetricsJournalFile = $this->getCurrentMetricsJournalFile();
		if ( !file_exists( $currentMetricsJournalFile ) ) {
			$this->logger->info( __METHOD__ .
				'Attempted to archive non existing journal file {file}',
				[ 'file' => $currentMetricsJournalFile ]
			);
			return false;
		}
		if ( !filesize( $currentMetricsJournalFile ) ) {
			$this->logger->info( __METHOD__ .
				'Attempted to archive an empty journal file {file}',
				[ 'file' => $currentMetricsJournalFile ]
			);
			return false;
		}

		$archivedMetricsJournalFile = $currentMetricsJournalFile . '.' . date( 'c' );
		/**
		 * @var float
		 * Here we can give it quite a bit of time to lock. No worries.
		 */
		$lockTimeoutSeconds = 10;
		$lockHandler = fopen( $currentMetricsJournalFile, 'r' );
		if ( !$this->flockWithTimeout( $lockHandler, LOCK_EX, 1000000 * $lockTimeoutSeconds ) ) {
			$this->logger->warning( __METHOD__ .
				'Unable to achieve file lock on {file}',
				[ 'file' => $currentMetricsJournalFile ]
			);
			return false;
		}
		try {
			if ( !rename( $currentMetricsJournalFile, $archivedMetricsJournalFile ) ) {
				$this->logger->error( __METHOD__ .
					'Unable to rename existing file {from} to {to}',
					[
						'from' => $currentMetricsJournalFile,
						'to' => $archivedMetricsJournalFile
					]
				);
				return false;
			}
		} finally {
			flock( $lockHandler, LOCK_UN );
			fclose( $lockHandler );
		}

		$gzippedArchivedMetricsJournalFile = $archivedMetricsJournalFile . '.gz';
		// wb9 = write binary, compression level 9
		$out = gzopen( $gzippedArchivedMetricsJournalFile, 'wb9' );
		if ( $out ) {
			$in = fopen( $archivedMetricsJournalFile, 'rb' );
			if ( $in ) {
				$bufferLength = 1024 * 512;
				while ( !feof( $in ) ) {
					gzwrite( $out, fread( $in, $bufferLength ) );
				}
				fclose( $in );
			} else {
				$this->logger->warning( __METHOD__ .
					'Unable to read from file {from}. Archived journal was not compressed.',
					[ 'from' => $archivedMetricsJournalFile ]
				);
				return true;
			}
			gzclose( $out );
		} else {
			$this->logger->warning( __METHOD__ .
				'Unable to open new file {file} for compression output. Archived journal was not compressed.',
				[ 'from' => $archivedMetricsJournalFile ]
			);
			return true;
		}
		if ( !unlink( $archivedMetricsJournalFile ) ) {
			$this->logger->warning( __METHOD__ .
				'Unable to delete uncompressed archived journal file {file}.',
				[ 'file' => $archivedMetricsJournalFile ]
			);
		}
		return true;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	private function getCurrentMetricsJournalFile(): string {
		$metricsJournalFile = $this->config->get( 'WikispeechListenMetricsJournalFile' );
		if ( !$metricsJournalFile ) {
			$metricsJournalFile = "{$this->config->get( 'UploadDirectory' )}/wikispeechListenMetrics.log";
		}
		return $metricsJournalFile;
	}

	/**
	 * https://gist.github.com/CMCDragonkai/a7b446f15094f59083a2
	 *
	 * Acquires a lock using flock, provide it a file stream, the
	 * lock type, a timeout in microseconds, and a sleep_by in microseconds.
	 * PHP's flock does not currently have a timeout or queuing mechanism.
	 * So we have to hack a optimistic method of continuously sleeping
	 * and retrying to acquire the lock until we reach a timeout.
	 * Doing this in microseconds is a good idea, as seconds are too
	 * granular and can allow a new thread to cheat the queue.
	 * There's no actual queue of locks being implemented here, so
	 * it is fundamentally non-deterministic when multiple threads
	 * try to acquire a lock with a timeout.
	 * This means a possible failure is resource starvation.
	 * For example, if there's too many concurrent threads competing for
	 * a lock, then this implementation may allow the second thread to be
	 * starved and allow the third thread to acquire the lock.
	 * The trick here is in the combination of LOCK_NB and $blocking.
	 * The $blocking variable is assigned by reference, it returns 1
	 * when the flock is blocked from acquiring a lock. With LOCK_NB
	 * the flock returns immediately instead of waiting indefinitely.
	 *
	 * @param resource $lockfile Lock file resource that is opened.
	 * @param int $lockType LOCK_EX or LOCK_SH
	 * @param int $timeout_micro In microseconds, where 1 second = 1,000,000 microseconds
	 * @param int $sleep_by_micro Microsecond sleep period, by default 0.01 of a second
	 * @return bool
	 */
	private function flockWithTimeout(
		$lockfile,
		int $lockType,
		int $timeout_micro,
		int $sleep_by_micro = 10000
	): bool {
		// @todo phpcs is not a fan of is_resource. What do we use instead?
		//if ( !is_resource( $lockfile ) ) {
		//	throw new InvalidArgumentException(
		//		'The $lockfile was not a file resource or the resource was closed.'
		//	);
		//}
		if ( $sleep_by_micro < 1 ) {
			throw new InvalidArgumentException(
				'The $sleep_by_micro cannot be less than 1, or else an infinite loop.'
			);
		}
		if ( $timeout_micro < 1 ) {
			$locked = flock( $lockfile, $lockType | LOCK_NB );
		} else {
			$count_micro = 0;
			$locked = true;
			while ( !flock( $lockfile, $lockType | LOCK_NB, $blocking ) ) {
				if ( $blocking ) {
					$count_micro += $sleep_by_micro;
					if ( $count_micro <= $timeout_micro ) {
						usleep( $sleep_by_micro );
					}
				} else {
					$locked = false;
					break;
				}
			}
		}
		return $locked;
	}
}
