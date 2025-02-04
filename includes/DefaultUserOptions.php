<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

 /**
  * @since 0.1.11
  */

class DefaultUserOptions {

	/**
	 * Get default user options when used as a producer
	 *
	 * Used when a consumer loads the gadget module.
	 *
	 * @since 0.1.11
	 * @return array
	 */
	public static function getDefaultUserOptions() {
		global $wgDefaultUserOptions;
		$wikispeechOptions = array_filter(
			$wgDefaultUserOptions,
			static function ( $key ) {
				// Only add options starting with "wikispeech".
				return strpos( $key, 'wikispeech' ) === 0;
			},
			ARRAY_FILTER_USE_KEY
		);
		return $wikispeechOptions;
	}
}
