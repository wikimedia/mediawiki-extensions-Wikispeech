<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Exception;

/**
 * Is used if a user tries to edit a lexicon post with no changes.
 * Then a warning box is shown.
 *
 * @since 0.1.11
 */
class NullEditLexiconException extends Exception {

}
