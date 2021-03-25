<?php

namespace MediaWiki\Wikispeech\Specials;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use ConfigFactory;
use FormatJson;
use FormSpecialPage;
use Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconHandler;
use MediaWiki\Wikispeech\SpeechoidConnector;

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

	/** @var LexiconHandler */
	private $lexiconHandler;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var LexiconEntryItem */
	private $addedItem;

	/**
	 * @since 0.1.8
	 * @param ConfigFactory $configFactory
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LexiconHandler $lexiconHandler
	 * @param SpeechoidConnector $speechoidConnector
	 */
	public function __construct(
		$configFactory,
		$languageNameUtils,
		$lexiconHandler,
		$speechoidConnector
	) {
		parent::__construct( 'EditLexicon', 'wikispeech-edit-lexicon' );
		$this->config = $configFactory->makeConfig( 'wikispeech' );
		$this->languageNameUtils = $languageNameUtils;
		$this->lexiconHandler = $lexiconHandler;
		$this->speechoidConnector = $speechoidConnector;
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
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$item = new LexiconEntryItem();
		$sampa = $this->speechoidConnector->ipaToSampa(
			$data['transcription'],
			$data['language']
		);
		$item->setProperties( [
			'strn' => $data['word'],
			'transcriptions' => [ [ 'strn' => $sampa ] ],
			// Status is required by Speechoid.
			'status' => [
				'name' => 'ok'
			]
		] );
		$this->lexiconHandler->createEntryItem(
			$data['language'],
			$data['word'],
			$item
		);
		// Item is updated by createEntryItem(), so we just need to
		// store it.
		$this->addedItem = $item;
		return true;
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

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$itemString = FormatJson::encode(
			$this->addedItem->getProperties(),
			true
		);
		$this->getOutput()->addHtml(
			Html::successBox(
				$this->msg( 'wikispeech-lexicon-add-entry-success' )->text()
			)
		);
		$this->getOutput()->addHtml(
			Html::element( 'pre', [], $itemString )
		);
	}
}
