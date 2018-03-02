<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

class Util {
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
