<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use DOMElement;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * A table in segments.
 *
 * @since 0.1.15
 */
class Table extends PartOfContent {
	/**
	 * Generate a string that will be read for a table.
	 *
	 * In English it will look like " table: ".
	 *
	 * @since 0.1.15
	 * @return string
	 */
	public function getString() {
		return wfMessage( 'word-separator' )->text() .
			wfMessage( 'wikispeech-poc-table' )->text() .
			wfMessage( 'colon-separator' )->text() .
			wfMessage( 'word-separator' )->text();
	}

	/**
	 * Create an instance if `$element` is a table.
	 *
	 * @since 0.1.15
	 * @param DOMElement $element
	 * @return self | null
	 */
	public static function fromElement( DOMElement $element ): self|null {
		if ( $element->nodeName === 'table' ) {
			return new Table();
		}

		return null;
	}
}
