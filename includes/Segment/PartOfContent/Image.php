<?php

namespace MediaWiki\Wikispeech\Segment\PartOfContent;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Represents an image element in segments.
 *
 * This reads the alt-text if present.
 *
 * @since 0.1.15
 */
class Image extends PartOfContent {
	/**
	 * The alt-attribute of an image
	 * @var string|null
	 */
	private ?string $alt;

	/**
	 * The caption of an image
	 * @var string|null
	 */
	private ?string $caption = null;

	/**
	 * @since 0.1.15
	 * @param string|null $alt
	 */
	public function __construct( ?string $alt, ?string $caption = null ) {
		$this->alt = $alt;
		$this->caption = $caption;
	}

	/**
	 * Generate a string that will be read for an image.
	 *
	 * In English it will look like "image: alt text:"
	 *
	 * @since 0.1.15
	 * @return string
	 */
	public function getString() {
		$string =
			wfMessage( 'word-separator' )->text() .
			wfMessage( 'wikispeech-poc-start-image' )->text() .
			wfMessage( 'colon-separator' )->text();

		if ( $this->alt ) {
			$string .=
				wfMessage( 'word-separator' )->text() .
				wfMessage( 'wikispeech-poc-alt-text' )->text() .
				wfMessage( 'colon-separator' )->text() .
				wfMessage( 'word-separator' )->text() .
				$this->alt .
				wfMessage( 'colon-separator' )->text();
		}

		if ( $this->caption ) {
			$string .=
				wfMessage( 'word-separator' )->text() .
				wfMessage( 'wikispeech-poc-caption' )->text() .
				wfMessage( 'colon-separator' )->text() .
				wfMessage( 'word-separator' )->text();
		}

		$string .= wfMessage( 'word-separator' )->text();

		return $string;
	}

	/**
	 * @since 0.1.15
	 * Reads the alt-text if present.
	 * Returns null if no usable alt.
	 *
	 * @param DOMElement $element
	 * @return self|null
	 */
	public static function fromElement( DOMElement $element ): self|null {
		if ( $element->nodeName !== 'img' ) {
			return null;
		}

		$alt = $element->getAttribute( 'alt' );
		if ( $alt === '' ) {
			$alt = null;
		}

		$caption = null;
		if ( $element->ownerDocument instanceof DOMDocument ) {
			$xpath = new DOMXPath( $element->ownerDocument );
			$node = $xpath->evaluate(
			'ancestor::li[1]//div[contains(@class,"gallerytext")][1]',
			$element
			)->item( 0 );

			if ( $node !== null ) {
				$caption = $node->textContent;
			}
		}

		if ( $alt !== null && $caption === $alt ) {
			$alt = null;
		}

		return new self( $alt, $caption );
	}

	/**
	 * This is for testing
	 * @return string|null
	 */
	public function getAlt(): ?string {
		return $this->alt;
	}
}
