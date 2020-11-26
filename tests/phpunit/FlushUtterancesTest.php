<?php

declare( strict_types = 1 );

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

use MediaWiki\Wikispeech\FlushUtterances;
use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByExpirationJobQueue;
use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByLanguageAndVoiceJobQueue;
use MediaWiki\Wikispeech\Utterance\FlushUtterancesFromStoreByPageIdJobQueue;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;

// files in maintenance/ are not autoloaded to avoid accidental usage, so load explicitly
require_once __DIR__ . '/../../maintenance/flushUtterances.php';

/**
 * @covers \MediaWiki\Wikispeech\FlushUtterances
 *
 * @since 0.1.7
 * @license GPL-2.0-or-later
 */
class FlushUtterancesTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return FlushUtterances::class;
	}

	// ███████╗██╗  ██╗██████╗ ██╗██████╗ ███████╗
	// ██╔════╝╚██╗██╔╝██╔══██╗██║██╔══██╗██╔════╝
	// █████╗   ╚███╔╝ ██████╔╝██║██████╔╝█████╗
	// ██╔══╝   ██╔██╗ ██╔═══╝ ██║██╔══██╗██╔══╝
	// ███████╗██╔╝ ██╗██║     ██║██║  ██║███████╗
	// ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝

	public function testExpire_force_executeSuccessful() {
		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->expects( $this->once() )
			->method( 'flushUtterancesByExpirationDate' )
			->willReturn( 0 );
		$this->maintenance->utteranceStore = $utteranceStoreMock;
		$this->maintenance->loadWithArgv( [
			'--force',
			'--expire'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testExpire_queue_executeSuccessful() {
		$jobQueue = $this->createMock( FlushUtterancesFromStoreByExpirationJobQueue::class );
		$jobQueue
			->expects( $this->once() )
			->method( 'queueJob' );
		$this->maintenance->flushUtterancesFromStoreByExpirationJobQueue = $jobQueue;
		$this->maintenance->loadWithArgv( [
			'--expire'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testExpire_expireAndOtherParameters_executeFails() {
		$this->maintenance->loadWithArgv( [
			'--expire',
			'--language', 'sv'
		] );
		$this->assertFalse( $this->maintenance->execute() );

		$this->maintenance->loadWithArgv( [
			'--expire',
			'--voice', 'anna'
		] );
		$this->assertFalse( $this->maintenance->execute() );

		$this->maintenance->loadWithArgv( [
			'--expire',
			'--page', '1'
		] );
		$this->assertFalse( $this->maintenance->execute() );
	}

	// ██████╗  █████╗  ██████╗ ███████╗
	// ██╔══██╗██╔══██╗██╔════╝ ██╔════╝
	// ██████╔╝███████║██║  ███╗█████╗
	// ██╔═══╝ ██╔══██║██║   ██║██╔══╝
	// ██║     ██║  ██║╚██████╔╝███████╗
	// ╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚══════╝

	public function testPage_force_executeSuccessful() {
		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->expects( $this->once() )
			->method( 'flushUtterancesByPage' )
			->with(
				$this->equalTo( 1 )
			)
			->willReturn( 0 );
		$this->maintenance->utteranceStore = $utteranceStoreMock;
		$this->maintenance->loadWithArgv( [
			'--force',
			'--page', '1'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testPage_queue_executeSuccessful() {
		$jobQueue = $this->createMock( FlushUtterancesFromStoreByPageIdJobQueue::class );
		$jobQueue
			->expects( $this->once() )
			->method( 'queueJob' )
			->with(
				$this->equalTo( 1 )
			);
		$this->maintenance->flushUtterancesFromStoreByPageIdJobQueue = $jobQueue;
		$this->maintenance->loadWithArgv( [
			'--page', '1'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testPage_pageAndOtherParameters_executeFails() {
		$this->maintenance->loadWithArgv( [
			'--page', '1',
			'--language', 'sv'
		] );
		$this->assertFalse( $this->maintenance->execute() );

		$this->maintenance->loadWithArgv( [
			'--page', '1',
			'--voice', 'anna'
		] );
		$this->assertFalse( $this->maintenance->execute() );

		$this->maintenance->loadWithArgv( [
			'--page', '1',
			'--expire'
		] );
		$this->assertFalse( $this->maintenance->execute() );
	}

	// ██╗      █████╗ ███╗   ██╗ ██████╗ ██╗   ██╗ █████╗  ██████╗ ███████╗
	// ██║     ██╔══██╗████╗  ██║██╔════╝ ██║   ██║██╔══██╗██╔════╝ ██╔════╝
	// ██║     ███████║██╔██╗ ██║██║  ███╗██║   ██║███████║██║  ███╗█████╗
	// ██║     ██╔══██║██║╚██╗██║██║   ██║██║   ██║██╔══██║██║   ██║██╔══╝
	// ███████╗██║  ██║██║ ╚████║╚██████╔╝╚██████╔╝██║  ██║╚██████╔╝███████╗
	// ╚══════╝╚═╝  ╚═╝╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ╚═╝  ╚═╝ ╚═════╝ ╚══════╝

	public function testLanguage_force_executeSuccessful() {
		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->expects( $this->once() )
			->method( 'flushUtterancesByLanguageAndVoice' )
			->willReturn( 0 )
			->with(
				$this->equalTo( 'sv' ),
				$this->equalTo( null )
			);
		$this->maintenance->utteranceStore = $utteranceStoreMock;
		$this->maintenance->loadWithArgv( [
			'--force',
			'--language', 'sv'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testLanguage_queue_executeSuccessful() {
		$jobQueue = $this->createMock( FlushUtterancesFromStoreByLanguageAndVoiceJobQueue::class );
		$jobQueue
			->expects( $this->once() )
			->method( 'queueJob' )
			->with(
				$this->equalTo( 'sv' ),
				$this->equalTo( null )
			);
		$this->maintenance->flushUtterancesFromStoreByLanguageAndVoiceJobQueue = $jobQueue;
		$this->maintenance->loadWithArgv( [
			'--language', 'sv'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testLanguage_languageAndOtherParameters_executeFails() {
		$this->maintenance->loadWithArgv( [
			'--language', 'sv',
			'--page', '1'
		] );
		$this->assertFalse( $this->maintenance->execute() );

		$this->maintenance->loadWithArgv( [
			'--language', 'sv',
			'--expire'
		] );
		$this->assertFalse( $this->maintenance->execute() );
	}

	// ██╗   ██╗ ██████╗ ██╗ ██████╗███████╗
	// ██║   ██║██╔═══██╗██║██╔════╝██╔════╝
	// ██║   ██║██║   ██║██║██║     █████╗
	// ╚██╗ ██╔╝██║   ██║██║██║     ██╔══╝
	// .╚████╔╝ ╚██████╔╝██║╚██████╗███████╗
	// ..╚═══╝   ╚═════╝ ╚═╝ ╚═════╝╚══════╝

	public function testVoice_force_executeSuccessful() {
		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->expects( $this->once() )
			->method( 'flushUtterancesByLanguageAndVoice' )
			->with(
				$this->equalTo( 'sv' ),
				$this->equalTo( 'anna' )
			)
			->willReturn( 0 );
		$this->maintenance->utteranceStore = $utteranceStoreMock;
		$this->maintenance->loadWithArgv( [
			'--force',
			'--language', 'sv',
			'--voice', 'anna'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testVoice_queue_executeSuccessful() {
		$jobQueue = $this->createMock( FlushUtterancesFromStoreByLanguageAndVoiceJobQueue::class );
		$jobQueue
			->expects( $this->once() )
			->method( 'queueJob' )
			->with(
				$this->equalTo( 'sv' ),
				$this->equalTo( 'anna' )
			);
		$this->maintenance->flushUtterancesFromStoreByLanguageAndVoiceJobQueue = $jobQueue;
		$this->maintenance->loadWithArgv( [
			'--language', 'sv',
			'--voice', 'anna'
		] );
		$this->assertTrue( $this->maintenance->execute() );
	}

	public function testVoice_languageMissing_executeFails() {
		$this->maintenance->loadWithArgv( [
			'--voice', 'anna',
		] );
		$this->assertFalse( $this->maintenance->execute() );
	}

	public function testVoice_languageAndOtherParameters_executeFails() {
		$this->maintenance->loadWithArgv( [
			'--language', 'sv',
			'--voice', 'anna',
			'--page', '1'
		] );
		$this->assertFalse( $this->maintenance->execute() );

		$this->maintenance->loadWithArgv( [
			'--language', 'sv',
			'--voice', 'anna',
			'--expire'
		] );
		$this->assertFalse( $this->maintenance->execute() );
	}

}
