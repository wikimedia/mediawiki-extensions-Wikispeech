<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use DateTime;
use JobQueueGroup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Title;
use WANObjectCache;

/**
 * Periodically flushes out old utterances from the utterance store.
 *
 * This is an important part of the the extension architecture, making sure
 * that utterance audio files and synthesis metadata files are updated
 * when underlying voice synthesis is improved.
 *
 * The job is currently queued at the same time as utterances are created,
 * with a configurable minimum amount of minutes delay between queues by
 * executing {@link FlushUtterancesFromStoreByExpirationJob::maybeQueueJob()}.
 *
 * This is more or less equivalent to a semi dysfunctional cron tab. It might
 * make sense to consider implement queuing the job periodically using a real
 * cron tab, but that would also require more manual non standard work when
 * setting up Wikispeech. It might also cause problems with future upgrades.
 *
 * One downside to the current approach is, that in a highly distributed
 * environment with many active users there might be multiple jobs queued
 * before the WAN cache is synchronized. Executing this job multiple times
 * is however not a problem in it self more than being a bit of waste of
 * resources. In the case where Wikispeech is configured to only flush a
 * limited batch of utterances in each job, it might actually be a good
 * thing that multiple jobs are queued.
 *
 * @see UtteranceStore::flushUtterancesByExpirationDate()
 * @see FlushUtterancesFromStoreByExpirationJob
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByExpirationJobQueue {

	/** @var LoggerInterface */
	private $logger;

	/** @var WANObjectCache */
	private $cache;

	/** @var string */
	private $cacheKey;

	/** @var string WAN cache key class namespace */
	public static $cacheKeyClass = 'Wikispeech.flushUtterancesFromStoreByExpirationJob';

	/** @var string WAN cache key component used by pseudo-crontab */
	public static $cacheKeyComponentPreviousDateTimeQueued = 'previousDateTimeQueued';

	/** @var int */
	private $minimumMinutesBetweenFlushExpiredUtterancesJobs;

	/**
	 * @since 0.1.7
	 */
	public function __construct() {
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		$this->minimumMinutesBetweenFlushExpiredUtterancesJobs = intval(
			$config->get( 'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' )
		);
		$this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$this->cacheKey = $this->cache->makeKey(
			self::$cacheKeyClass,
			self::$cacheKeyComponentPreviousDateTimeQueued
		);
	}

	/**
	 * Queues a job if criteria to do so has been met.
	 *
	 * Convenience method that calls {@link queueJob()}
	 * if {@link isTimeToQueueJob()} returns true.
	 *
	 * @since 0.1.7
	 * @return bool Whether or not job was queued.
	 */
	public function maybeQueueJob() {
		if ( $this->isTimeToQueueJob() ) {
			$this->queueJob();
			return true;
		}
		return false;
	}

	/**
	 * Queues a job.
	 *
	 * @since 0.1.7
	 */
	public function queueJob() {
		$this->cache->set( $this->cacheKey, new DateTime() );
		JobQueueGroup::singleton()->push(
			new FlushUtterancesFromStoreByExpirationJob(
				Title::newMainPage(),
				[]
			)
		);
	}

	/**
	 * Evaluates if the criteria has been met to queue a job.
	 * Currently that means that at least as many minutes as defined in config
	 * has past since previous queueing of the job.
	 *
	 * @since 0.1.7
	 * @return bool Whether or not it's time to queue the flush job
	 */
	public function isTimeToQueueJob() {
		if ( !$this->minimumMinutesBetweenFlushExpiredUtterancesJobs ) {
			return false;
		}
		$previousDateTimeQueued = $this->cache->get( $this->cacheKey );
		$this->logger->debug( __METHOD__ . ': ' .
			'previousDateTimeQueued {previousDateTimeQueued}',
			[ 'previousDateTimeQueued' => $previousDateTimeQueued ]
		);
		if ( !$previousDateTimeQueued ) {
			// Either the cached value was never set or the cache is offline.
			// In case of the latter, avoid queuing jobs on each request
			// by requiring a cached value. This will set the initial cached value.
			$this->cache->set( $this->cacheKey, new DateTime() );
			return false;
		}
		$nextPossibleJobQueueTimestamp = date_add(
			$previousDateTimeQueued,
			date_interval_create_from_date_string(
				$this->minimumMinutesBetweenFlushExpiredUtterancesJobs . ' minutes'
			)
		);
		if ( !$nextPossibleJobQueueTimestamp ) {
			$this->logger->error( __METHOD__ . ': ' .
				'Unable to calculate next possible job queue DateTime.'
			);
			return false;
		}
		return new DateTime() >= $nextPossibleJobQueueTimestamp;
	}
}
