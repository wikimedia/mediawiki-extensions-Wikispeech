<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use DOMElement;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * A table cell element in segments.
 *
 * @since 0.1.15
 */
class TableCell extends PartOfContent {
	/**
	 * The header of the column that this cell is in.
	 *
	 * @var string
	 */
	private string $columnHeader;

	public function __construct( string $columnHeader ) {
		$this->columnHeader = $columnHeader;
	}

	/**
	 * Generate a string that will be read for a table header.
	 *
	 * In English it will look like " column: <header>: ".
	 *
	 * @since 0.1.15
	 * @return string
	 */
	public function getString() {
		return wfMessage( 'word-separator' )->text() .
			wfMessage( 'wikispeech-poc-table-column' )->text() .
			wfMessage( 'colon-separator' )->text() .
			$this->columnHeader .
			wfMessage( 'colon-separator' )->text() .
			wfMessage( 'word-separator' )->text();
	}

	/**
	 * Create an instance if `$element` is a td.
	 *
	 * Stores the header for the column the cell is in.
	 *
	 * @since 0.1.15
	 * @param DOMElement $element
	 * @return self | null
	 */
	public static function fromElement( DOMElement $element ): self|null {
		if ( $element->nodeName !== 'td' ) {
			return null;
		}

		$row = $element->parentNode;
		$index = 0;
		foreach ( $row->childNodes as $child ) {
			if ( $child === $element ) {
				break;
			}

			$index++;
		}
		$tbody = $row->parentNode;
		$columnHeader = $tbody->firstChild->childNodes->item( $index );
		return new TableCell( $columnHeader->textContent );
	}
}
