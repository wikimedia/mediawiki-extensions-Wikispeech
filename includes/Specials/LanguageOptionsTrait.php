<?php

namespace MediaWiki\Wikispeech\Specials;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Config\Config;
use MediaWiki\Languages\LanguageNameUtils;

/**
 * @since 0.1.13
 */

trait LanguageOptionsTrait {
	/**
	 * @see IContextSource::getLanguage
	 * @return Config
	 */
	abstract public function getConfig();

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/**
	 * Make options to be used by in a select field
	 *
	 * Each language that is specified in the config variable
	 * "WikispeechVoices" is included in the options. The labels are
	 * of the format "code - autonym".
	 *
	 * @since 0.1.13
	 * @return array Keys are labels and values are language codes.
	 */
	protected function getLanguageOptions(): array {
		$voices = $this->getConfig()->get( 'WikispeechVoices' );
		$languages = array_keys( $voices );
		sort( $languages );
		$options = [];
		foreach ( $languages as $code ) {
			$name = $this->languageNameUtils->getLanguageName( $code );
			$label = "$code - $name";
			$options[$label] = $code;
		}
		ksort( $options );
		return $options;
	}
}
