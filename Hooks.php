<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-3.0+
 */

class WikispeechHooks {
	/**
	 * Conditionally register the unit testing module for the ext.wikispeech module
	 * only if that module is loaded
	 *
	 * @param array $testModules The array of registered test modules
	 * @param ResourceLoader $resourceLoader The reference to the resource loader
	 * @return true
	 */
	public static function onResourceLoaderTestModules( array &$testModules, ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['ext.wikispeech.tests'] = [
			'scripts' => [
				'tests/Wikispeech.test.js'
			],
			'dependencies' => [
				'ext.wikispeech'
			],
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikispeech',
		];
		return true;
	}


}
