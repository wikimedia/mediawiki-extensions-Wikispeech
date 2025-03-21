<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;
use RuntimeException;

class InputTextValidator {

	/**
	 * Validate input text.
	 *
	 * @since 0.1.11
	 * @param string $text
	 * @throws RuntimeException
	 */
	public static function validateText( string $text ): void {
		$numberOfCharactersInInput = mb_strlen( $text );
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$maximum = $config->get( 'WikispeechListenMaximumInputCharacters' );
		if ( $numberOfCharactersInInput > $maximum ) {
			throw new RuntimeException( "Too long: $numberOfCharactersInInput > $maximum" );
		}
	}
}
