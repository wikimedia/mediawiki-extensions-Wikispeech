<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Mediawiki\Title\Title;
use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByExpirationJob;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByExpirationJob
 *
 * @since 0.1.7
 */
class FlushUtterancesFromStoreByExpirationJobTest extends MediaWikiIntegrationTestCase {

	/**
	 * Runs a test on an empty UtteranceStore and makes sure no errors occur
	 * due to date formatting error and what not.
	 *
	 * Actual flush testing takes place in {@link UtteranceStoreTest}.
	 *
	 * @since 0.1.7
	 */
	public function testWithEmptyUtteranceStore_runJob_returnsTrue() {
		$this->overrideConfigValues( [
			'WikispeechMinimumMinutesBetweenFlushExpiredUtterancesJobs' => 60
		] );

		/** @var FlushUtterancesFromStoreByExpirationJob|TestingAccessWrapper $job */
		$job = TestingAccessWrapper::newFromObject(
			new FlushUtterancesFromStoreByExpirationJob(
				Title::newMainPage(),
				[]
			)
		);
		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->expects( $this->once() )
			->method( 'flushUtterancesByExpirationDate' )
			->willReturn( 0 );
		$job->utteranceStore = $utteranceStoreMock;

		// Execute the job
		$this->assertTrue( $job->run() );
	}

}
