<?php

namespace MediaWiki\Wikispeech\Specials;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use ConfigFactory;
use FormSpecialPage;
use MediaWiki\Languages\LanguageNameUtils;

/**
 * Special page for editing the lexicon.
 *
 * @since 0.1.8
 */

class SpecialEditLexicon extends FormSpecialPage {

	/** @var Config */
	private $config;

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/**
	 * @since 0.1.8
	 * @param ConfigFactory $configFactory
	 * @param LanguageNameUtils $languageNameUtils
	 */
	public function __construct( $configFactory, $languageNameUtils ) {
		parent::__construct( 'EditLexicon', 'wikispeech-edit-lexicon' );
		$this->config = $configFactory->makeConfig( 'wikispeech' );
		$this->languageNameUtils = $languageNameUtils;
	}

	/**
	 * @since 0.1.8
	 * @param string|null $subpage
	 */
	public function execute( $subpage ) {
		parent::execute( $subpage );
		$this->checkPermissions();
		$out = $this->getOutput();
		$out->addModules( [
			'ext.wikispeech.specialEditLexicon'
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		return [
			'language' => [
				'type' => 'select',
				'label' => $this->msg( 'wikispeech-language' )->text(),
				'options' => $this->getLanguageOptions(),
				'id' => 'ext-wikispeech-language'
			],
			'word' => [
				'type' => 'text',
				'label' => $this->msg( 'wikispeech-word' )->text(),
				'required' => true
			],
			'transcription' => [
				'type' => 'textwithbutton',
				'label' => $this->msg( 'wikispeech-transcription' )->text(),
				'required' => true,
				'id' => 'ext-wikispeech-transcription',
				'buttontype' => 'button',
				'buttondefault' => $this->msg( 'wikispeech-preview' )->text(),
				'buttonid' => 'ext-wikispeech-preview-button'
			]
		];
	}

	/**
	 * Will be implemented in T274649.
	 *
	 * @since 0.1.8
	 * @param array $data
	 * @return bool
	 */
	public function onSubmit( array $data ) {
		return false;
	}

	/**
	 * Make options to be used by in a select field
	 *
	 * Each language that is specified in the config variable
	 * "WikispeechVoices" is included in the options. The labels are
	 * of the format "code - autonym".
	 *
	 * @since 0.1.8
	 * @return array Keys are labels and values are language codes.
	 */
	private function getLanguageOptions(): array {
		$voices = $this->config->get( 'WikispeechVoices' );
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
