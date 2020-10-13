<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use DateTime;

use MediaWikiTestCase;
use WANObjectCache;
use MediaWiki\MediaWikiServices;

use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByExpirationJobQueue;

/**
 * @covers \MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByExpirationJobQueue
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByExpirationJobQueueTest extends MediaWikiTestCase {

	/** @var WANObjectCache A disabled WAN cache, backed by EmptyBagOStuff. */
	private $cache;

	/** @var string */
	private $cacheKey;

	protected function setUp() : void {
		parent::setUp();
		$this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$this->cacheKey = $this->cache->makeKey(
			FlushUtterancesFromStoreByExpirationJobQueue::$cacheKeyClass,
			FlushUtterancesFromStoreByExpirationJobQueue::$cacheKeyComponentPreviousDateTimeQueued
		);
	}

	/**
	 * Activates a WANCache previously disabled by MediaWikiTestCase.
	 *
	 * Any previous instance of WANObjectCache picked up (e.g. the class field
	 * instantiated in the constructor) will not be changed by calling this
	 * function. It will still be backed by an EmptyBagOStuff and thus
	 * not compatible with changes that require setting values to the cache.
	 *
	 * @since 0.1.7
	 */
	private function activateWanCache() {
		$this->setMwGlobals( [
			'wgMainWANCache' => 'hash',
			'wgWANObjectCaches' => [
				'hash' => [
					'class'    => WANObjectCache::class,
					'cacheId'  => 'hash',
					'channels' => []
				]
			]
		] );
		$this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
	}

	/**
	 * @since 0.1.7
	 */
	public function testIsTimeToQueueJob_withoutWANCache_nothingCachedAndNeverTimeToQueue() {
		$this->setMwGlobals( [
			'wgWikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' => 0
		] );

		$queue = new FlushUtterancesFromStoreByExpirationJobQueue();

		$this->assertFalse( $this->cache->get( $this->cacheKey ) );

		// At this point in time the cache has never been touched
		// and is thus in the state of a job never been queued.
		// I.e. it's still not time to queue job,
		// as at least one call to this method is required
		// to initially set a cache value.
		// This avoids the queuing going berserk when cache is disabled.
		// In order to execute an initial cache value has to be set,
		// which this function call will do.
		$this->assertFalse( $queue->isTimeToQueueJob() );

		// But since WANCache is disabled, nothing was set in the cache
		$this->assertFalse( $this->cache->get( $this->cacheKey ) );

		// And since nothing set in cache, it's still not time to queue the job.
		$this->assertFalse( $queue->isTimeToQueueJob() );
	}

	/**
	 * @since 0.1.7
	 */
	public function testIsTimeToQueueJob_withWANCache_cacheValueSet() {
		$this->setMwGlobals( [
			'wgWikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' => 30
		] );

		$this->activateWanCache();
		$queue = new FlushUtterancesFromStoreByExpirationJobQueue();

		$this->assertFalse( $this->cache->get( $this->cacheKey ) );

		// At this point in time the cache has never been touched
		// and is thus in the state of a job never been queued.
		// I.e. it's still not time to queue job,
		// as at least one call to this method is required
		// to initially set a cache value.
		// This avoids the queuing going berserk when cache is disabled.
		// In order to execute an initial cache value has to be set,
		// which this function call will do.
		$this->assertFalse( $queue->isTimeToQueueJob() );

		// The cache state is now that we queued a job right now, even though we didn't.
		// This is to avoid queueing on each request when cache is offline.
		$this->assertNotFalse( $this->cache->get( $this->cacheKey ) );
	}

	/**
	 * @since 0.1.7
	 */
	public function testIsTimeToQueueJob_configFalsy_isAlwaysFalse() {
		$this->setMwGlobals( [
			'wgWikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' => 0
		] );

		$this->activateWanCache();
		$queue = new FlushUtterancesFromStoreByExpirationJobQueue();

		// Simulate in cache that job was queued one year ago.
		$aYearAgo = new DateTime();
		$aYearAgo->modify( '-1 year' );
		$this->cache->set( $this->cacheKey, $aYearAgo );
		$this->assertFalse( $queue->isTimeToQueueJob() );
	}

	/**
	 * @since 0.1.7
	 */
	public function testIsTimeToQueueJob_enoughTimeHasNotPassed_isFalse() {
		$this->setMwGlobals( [
			'wgWikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' => 30
		] );

		$this->activateWanCache();
		$queue = new FlushUtterancesFromStoreByExpirationJobQueue();

		// Simulate in cache that job was queued now.
		$this->cache->set( $this->cacheKey, new DateTime() );

		// It's not been 30 minutes
		$this->assertFalse( $queue->isTimeToQueueJob() );
	}

	/**
	 * @since 0.1.7
	 */
	public function testIsTimeToQueueJob_enoughTimeHasPassed_isTrue() {
		$this->setMwGlobals( [
			'wgWikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' => 30
		] );

		$this->activateWanCache();
		$queue = new FlushUtterancesFromStoreByExpirationJobQueue();

		// Simulate in cache that job was queued 60 minutes ago.
		$anHourAgo = new DateTime();
		$anHourAgo->modify( '-1 hour' );
		$this->cache->set( $this->cacheKey, $anHourAgo );

		$this->assertTrue( $queue->isTimeToQueueJob() );
	}

}
