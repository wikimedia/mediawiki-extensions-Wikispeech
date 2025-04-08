<?php

declare( strict_types = 1 );

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

 use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
 use MediaWiki\Wikispeech\PreSynthesizeMessages;
 use MediaWiki\Wikispeech\Utterance\UtteranceStore;

// files in maintenance/ are not autoloaded to avoid accidental usage, so load explicitly
require_once __DIR__ . '/../../maintenance/preSynthesizeMessages.php';

/**
 * @covers \MediaWiki\Wikispeech\PreSynthesizeMessages
 *
 * @since 0.1.13
 * @license GPL-2.0-or-later
 */
class PreSynthesizeMessagesTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return PreSynthesizeMessages::class;
	}

	public function testSynthesizeErrorMessage_executeSuccessful() {
		$utteranceStoreMock = $this->createMock( UtteranceStore::class );
		$utteranceStoreMock
			->expects( $this->exactly( 2 ) )
			->method( 'createMessageUtterance' )
			->with(
				$this->isNull(),
				'dummy-key',
				'sv',
				'anna',
				'123123123',
				'DummyBase64Audio=',
				'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}'
			);

		$maintenanceMock = $this->getMockBuilder( PreSynthesizeMessages::class )
			->setConstructorArgs( [ $utteranceStoreMock ] )
			->onlyMethods( [ 'synthesizeErrorMessage' ] )
			->getMock();

		$maintenanceMock
			->method( 'synthesizeErrorMessage' )
			->willReturnCallback( static function () use ( $utteranceStoreMock ) {
				$utteranceStoreMock->createMessageUtterance(
					null,
					'dummy-key',
					'sv',
					'anna',
					'123123123',
					'DummyBase64Audio=',
					'{"tokens": [{"endtime": 0.155, "orth": "i"}, {"endtime": 0.555, "orth": ""}]}'
				);
			} );

		$maintenanceMock->loadWithArgv( [
			'--language', 'sv',
			'--voice', 'anna'
		] );

		$this->assertTrue( $maintenanceMock->execute() );
	}
}
