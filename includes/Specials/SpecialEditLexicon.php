<?php

namespace MediaWiki\Wikispeech\Specials;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use ConfigFactory;
use Html;
use MediaWiki\Languages\LanguageNameUtils;
use OOUI\ButtonWidget;
use OOUI\DropdownInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\TextInputWidget;
use OOUI\Widget;
use SpecialPage;

/**
 * Special page for editing the lexicon.
 *
 * @since 0.1.8
 */

class SpecialEditLexicon extends SpecialPage {

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
		$this->checkPermissions();
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->setPageTitle( $this->msg( 'editlexicon' ) );
		$this->addElements();
		$out->addModules( [
			'ext.wikispeech.specialEditLexicon'
		] );
	}

	/**
	 * Add elements to the UI.
	 *
	 * @since 0.1.8
	 */
	private function addElements() {
		$languageField = new FieldLayout(
			new DropdownInputWidget( [
				'options' => $this->getLanguageOptions(),
				'infusable' => true,
				'classes' => [ 'ext-wikispeech-language' ]
			] ),
			[
				'label' => $this->msg( 'wikispeech-language' )->text(),
				'align' => 'top'
			]
		);

		$wordField = new FieldLayout(
			new TextInputWidget( [
				'required' => true
			] ),
			[
				'label' => $this->msg( 'wikispeech-word' )->text(),
				'align' => 'top'
			]
		);

		$transcriptionField = new FieldLayout(
			new TextInputWidget( [
				'required' => true,
				'infusable' => true,
				'classes' => [ 'ext-wikispeech-transcription' ]
			] ),
			[
				'label' => $this->msg( 'wikispeech-transcription' )->text(),
				'align' => 'top'
			]
		);
		$playerHtml = Html::element(
			'audio',
			[
				'class' => 'ext-wikispeech-preview-player',
				'controls'
			]
		);
		$previewPlayer = new FieldLayout(
			new Widget( [
				'content' => new HtmlSnippet( $playerHtml ),
				[ 'class' => 'ext-wikispeech-preview-player' ]
			] )
		);
		$transcription = new FieldsetLayout( [
			'items' => [
				$transcriptionField,
				$previewPlayer
			]
		] );

		$saveField = new FieldLayout(
			new ButtonWidget( [
				'label' => $this->msg( 'wikispeech-save' )->text(),
				'flags' => [
					'progressive',
					'primary'
				]
			] )
		);

		$this->getOutput()->addHTML(
			new FieldsetLayout( [
				'items' => [
					$languageField,
					$wordField,
					$transcription,
					$saveField
				]
			] )
		);
	}

	/*
	 * Make options to be used by {@link DropdownInputWidget}
	 *
	 * Each language that is specified in the config variable
	 * "WikispeechVoices" is included in the options. The labels are
	 * of the format "code - autonym".
	 *
	 * @since 0.1.8
	 * @return array Items are arrays containing codes and labels.
	 */
	private function getLanguageOptions(): array {
		$voices = $this->config->get( 'WikispeechVoices' );
		$languages = array_keys( $voices );
		sort( $languages );
		$options = [];
		foreach ( $languages as $code ) {
			$name = $this->languageNameUtils->getLanguageName( $code );
			$options[] = [
				'data' => $code,
				'label' => "$code - $name"
			];
		}
		ksort( $options );
		return $options;
	}
}
