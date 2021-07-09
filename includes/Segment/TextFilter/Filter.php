<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * Processes incoming text/plain input,
 * possibly transforming parts of the content,
 * and returns text/xml SSML.
 *
 * @since 0.1.10
 */
abstract class Filter {

	/** @var FilterPart[] */
	private $parts;

	/**
	 * @since 0.1.10
	 * @param string $text text/plain input
	 */
	public function __construct( string $text ) {
		$this->parts = [ new FilterPart( $text ) ];
	}

	/**
	 * @since 0.1.10
	 * @return string|null text/xml SSML output, or null if no rules applied
	 */
	public function process(): ?string {
		if ( count( $this->parts ) === 1 ) {
			if ( $this->parts[0]->getAppliedRule() === null ) {
				// no rules applied, the output is the same as the input.
				return null;
			// @phan-suppress-next-line PhanPluginDuplicateIfStatements T286912
			} else {
				// @todo There is a bug in Wikispeech-server.
				// It can not handle SSML with a single <sub> as the only DOM child.
				// https://phabricator.wikimedia.org/T286912
				return null;
			}
		}
		$ssml = '<speak xml:lang="' . $this->getSsmlLang() .
			'" version="1.0" xmlns="http://www.w3.org/2001/10/synthesis"' .
			' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
			' xsi:schemalocation="http://www.w3.org/2001/10/synthesis' .
			' http://www.w3.org/TR/speech-synthesis/synthesis.xsd">';
		foreach ( $this->parts as $part ) {
			// phan made me use a variable rather than just using the getter all over.
			$alias = $part->getAlias();
			if ( $alias === null || $alias === $part->getText() ) {
				$ssml .= $part->getText();
			} else {
				$ssml .= '<sub alias="' .
					htmlspecialchars( $alias, ENT_XML1, 'UTF-8' ) .
					'">' .
					htmlspecialchars( $part->getText(), ENT_XML1, 'UTF-8' ) .
					'</sub>';
			}
		}
		$ssml .= '</speak>';
		return $ssml;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	abstract public function getSsmlLang(): string;

	/**
	 * @since 0.1.10
	 * @param int $position
	 * @param FilterPart $part
	 */
	public function insertPart( int $position, FilterPart $part ) {
		$this->parts = array_merge(
			array_slice( $this->parts, 0, $position ),
			[ $part ],
			array_slice( $this->parts, $position )
		);
	}

	/**
	 * @since 0.1.10
	 * @return FilterPart[]
	 */
	public function getParts(): array {
		return $this->parts;
	}

}
