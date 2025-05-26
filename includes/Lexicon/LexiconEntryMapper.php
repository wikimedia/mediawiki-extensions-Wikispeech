<?php

namespace MediaWiki\Wikispeech\Lexicon;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

 /**
  * @since 0.1.13
  */

class LexiconEntryMapper {

	/**
	 * This refers only to nouns and sometimes pronouns.
	 * @param string $value
	 * @return string
	 */
	public static function morphologyMap( string $value ): string {
		if ( trim( $value ) === '' ) {
			return '-';
		}
		$values = [
			'SIN' => wfMessage( 'wikispeech-morphology-sin' )->text(),
			'PLU' => wfMessage( 'wikispeech-morphology-plu' )->text(),
			'NOM' => wfMessage( 'wikispeech-morphology-nom' )->text(),
			'GEN' => wfMessage( 'wikispeech-morphology-gen' )->text(),
			'IND' => wfMessage( 'wikispeech-morphology-ind' )->text(),
			'DEF' => wfMessage( 'wikispeech-morphology-def' )->text(),
			'UTR' => wfMessage( 'wikispeech-morphology-utr' )->text(),
			'NEU' => wfMessage( 'wikispeech-morphology-neu' )->text(),
		];

		$parts = preg_split( '/[|\-]/', $value );
		$filteredParts = array_filter( $parts, static function ( $p ) {
			return trim( $p ) !== '';
		} );

		$labels = [];
		foreach ( $filteredParts as $code ) {
			if ( isset( $values[$code] ) ) {
				$labels[] = $values[$code];
			} else {
				$labels[] = $code;
			}
		}

		return implode( ', ', $labels );
	}

	public static function partOfSpeechMap( string $value ): string {
		if ( trim( $value ) === '' ) {
			return '-';
		}
		$values = [
			'NN' => wfMessage( 'wikispeech-morphology-nn' )->text(),
			'PM' => wfMessage( 'wikispeech-morphology-pm' )->text(),
			'VB' => wfMessage( 'wikispeech-morphology-vb' )->text(),
		];

		return $values[$value] ?? $value;
	}

}
