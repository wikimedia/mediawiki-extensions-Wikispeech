<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use FormatJson;

/**
 * Multiple items can exist for the same word. E.g:
 * Records: FooBar Records, the name of a recording studio.
 * Records: John records the problem in the notebook.
 *
 * This class only contains a single deserialized JSON-blob,
 * but in the future this allows for easy extending to
 * a future ad hoc data model.
 *
 * @since 0.1.8
 */
class LexiconEntryItem {
	/** @var \stdClass|null stdClass - object. Deserialized Speechoid JSON entry */
	private $properties;

	/**
	 * @since 0.1.9
	 * @return string
	 */
	public function __toString(): string {
		return $this->toJson();
	}

	/**
	 * @since 0.1.8
	 * @return \stdClass|null
	 */
	public function getProperties(): ?\stdClass {
		return $this->properties;
	}

	/**
	 * @since 0.1.8
	 * @param \stdClass|null $properties
	 */
	public function setProperties( ?\stdClass $properties ): void {
		$this->properties = $properties;
	}

	/**
	 * Get the first transcription for this item
	 *
	 * It's assumed that there is only one transcription. While it's
	 * technically possible to have multiple transcriptions in
	 * Speechoid, it's unclear when this would happen.
	 *
	 * @since 0.1.10
	 * @return string
	 */
	public function getTranscription(): string {
		if ( $this->properties === null ) {
			return '';
		}

		return $this->properties->transcriptions[0]->strn;
	}

	/**
	 * Get preferred for this item
	 *
	 * @since 0.1.10
	 * @return bool The value of preferred or false if not present.
	 */
	public function getPreferred(): bool {
		if (
			$this->properties === null ||
			!property_exists( $this->properties, 'preferred' )
		) {
			return false;
		}
		return $this->properties->preferred;
	}

	/**
	 * Remove preferred for this item
	 *
	 * @since 0.1.10
	 */
	public function removePreferred() {
		if ( $this->properties === null ) {
			return;
		}

		unset( $this->properties->preferred );
	}

	// access helpers.

	/**
	 * Makes this item look exactly like the source item.
	 *
	 * @since 0.1.8
	 * @param LexiconEntryItem $source
	 */
	public function copyFrom( LexiconEntryItem $source ): void {
		// this might look silly,
		// but will make it easier to migrate to a future ad hoc data model
		$this->setProperties( $source->getProperties() );
	}

	/**
	 * @since 0.1.8
	 * @return int|null
	 */
	public function getSpeechoidIdentity(): ?int {
		$properties = $this->getProperties();
		return $properties !== null && property_exists( $properties, 'id' )
			? $properties->id : null;
	}

	/**
	 * @since 0.1.9
	 * @return string Empty if JSON encoding failed.
	 */
	public function toJson(): string {
		$json = FormatJson::encode( $this->properties, true );
		return $json ?: '';
	}
}
