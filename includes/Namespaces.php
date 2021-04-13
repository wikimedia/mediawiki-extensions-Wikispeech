<?php

namespace MediaWiki\WikispeechSpeechDataCollector;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/*
 * Namespace constants defined in extension.json
 * This is really just to avoid problems with Phan and code editors.
 */
if ( !defined( 'NS_PRONUNCIATION_LEXICON' ) ) {
	define( 'NS_PRONUNCIATION_LEXICON', 5772 );
	define( 'NS_PRONUNCIATION_LEXICON_TALK', 5773 );
}
