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
	/** @var array|null Associative array. Deserialized Speechoid JSON entry */
	private $properties;

	/**
	 * @return string
	 * @since 0.1.9
	 */
	public function __toString(): string {
		return $this->toJson();
	}

	/**
	 * @return array|null
	 * @since 0.1.8
	 */
	public function getProperties(): ?array {
		return $this->properties;
	}

	/**
	 * @param array|null $properties
	 * @since 0.1.8
	 */
	public function setProperties( ?array $properties ): void {
		$this->properties = $properties;
	}

	// access helpers.

	/**
	 * Makes this item look exactly like the source item.
	 *
	 * @param LexiconEntryItem $source
	 * @since 0.1.8
	 */
	public function copyFrom( LexiconEntryItem $source ): void {
		// this might look silly,
		// but will make it easier to migrate to a future ad hoc data model
		$this->setProperties( $source->getProperties() );
	}

	/**
	 * @return string|null
	 * @since 0.1.8
	 */
	public function getSpeechoidIdentity(): ?string {
		$properties = $this->getProperties();
		return $properties !== null && array_key_exists( 'id', $properties )
			? $properties['id'] : null;
	}

	/**
	 * @since 0.1.9
	 * @return string Empty if JSON encoding failed.
	 */
	public function toJson(): string {
		// @todo Handle empty objects properly TT279916
		if ( isset( $this->properties['lemma'] ) ) {
			$this->properties['lemma'] = (object)$this->properties['lemma'];
		}
		$json = FormatJson::encode( $this->properties, true );
		return $json ?: '';
	}
}
