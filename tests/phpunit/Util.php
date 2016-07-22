<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class Util {

	/**
	 * Create a CleanedStartTag and set it's $contentLength.
	 *
	 * @since 0.0.1
	 * @param string $tagString The tag string for the CleanedStartTag.
	 * @param string $contentString The content string, used for
	 *  calculating $contentLength for the CleanedStartTag, if not
	 *  null. null by default.
	 * @return CleanedStartTag
	 */

	public static function createStartTag(
		$tagString,
		$contentString=null
	) {
		$cleanedTag = new CleanedStartTag( $tagString );
		if ( $contentString != null ) {
			$cleanedTag->contentLength = strlen( $contentString );
		}
		return $cleanedTag;
	}

	/**
	 * Call a private function.
	 *
	 * Used for testing functions that normally can't be called in
	 * tests. Any arguments beyond $class and $function are sent as
	 * arguments to $function.
	 *
	 * @since 0.0.1
	 * @param string $class The name of the class that holds the function.
	 * @param string $function The name of the function to call
	 * @return Whatever $function returns
	 */

	public static function call( $class, $function ) {
		$reflection = new ReflectionMethod( $class, $function );
		$reflection->setAccessible( true );
		$arguments = array_slice( func_get_args(), 2 );
		return $reflection->invokeArgs( null, $arguments );
	}

}
