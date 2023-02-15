<?php

namespace MediaWiki\Wikispeech\Tests\Integration;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @todo This test (with modifications) has been suggested as a patch for MW core 1.36.
 *  Thus this class should probably be removed on a core version bump in Wikispeech.
 *  See https://phabricator.wikimedia.org/T273769
 * @coversNothing
 * @since 0.1.8
 */
class RegisteredJobsTest extends MediaWikiIntegrationTestCase {

	/**
	 * Asserts that all registered jobs (in core and any loaded extension.json)
	 * indeed are existing classes that extend Job.
	 */
	public function testJobClasses_iterateRegistered_areExistingSubclassesOfJob() {
		$serviceOptions = new ServiceOptions(
			[ 'JobClasses' ],
			MediaWikiServices::getInstance()->getMainConfig()
		);
		foreach ( $serviceOptions->get( 'JobClasses' ) as $name => $defintion ) {
			if ( is_array( $defintion ) ) {
				$class = $defintion['class'];
			} else {
				$class = $defintion;
			}
			$this->assertTrue( class_exists( $class ),
				"Class $class with alias $name does not exist" );
			$this->assertTrue( is_subclass_of( $class, 'Job' ),
				"Class $class with alias $name is not a subclass of Job." );
		}
	}
}
