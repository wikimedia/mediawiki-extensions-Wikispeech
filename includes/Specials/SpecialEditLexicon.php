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
use HTMLForm;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Wikispeech\Lexicon\LexiconEntryItem;
use MediaWiki\Wikispeech\Lexicon\LexiconStorage;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\Utterance\UtteranceStore;
use MWException;
use Psr\Log\LoggerInterface;
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

	/** @var LexiconStorage */
	private $lexiconStorage;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/** @var LexiconEntryItem */
	private $modifiedItem;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @since 0.1.8
	 * @param ConfigFactory $configFactory
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LexiconStorage $lexiconStorage
	 * @param SpeechoidConnector $speechoidConnector
	 */
	public function __construct(
		$configFactory,
		$languageNameUtils,
		$lexiconStorage,
		$speechoidConnector
	) {
		parent::__construct( 'EditLexicon', 'wikispeech-edit-lexicon' );
		$this->config = $configFactory->makeConfig( 'wikispeech' );
		$this->languageNameUtils = $languageNameUtils;
		$this->lexiconStorage = $lexiconStorage;
		$this->speechoidConnector = $speechoidConnector;
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
	}

	/**
	 * @since 0.1.8
	 * @param string|null $subpage
	 */
	public function execute( $subpage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$request = $this->getRequest();
		$language = $request->getText( 'language' );
		$word = $request->getText( 'word' );
		if ( $language && $word ) {
			$entry = $this->lexiconStorage->getEntry( $language, $word );
		} else {
			$entry = null;
		}
		$copyrightNote = $this->msg( 'wikispeech-lexicon-copyrightnote' )->parse();
		$postText = Html::rawElement( 'p', [], $copyrightNote );

		if ( $entry ) {
			// Entry exists, show form to update existing item or
			// create a new one.
			$itemJsons = [];
			$newLabel = $this->msg( 'wikispeech-lexicon-new' )->text();
			$idOptions = [ $newLabel => '' ];
			foreach ( $entry->getItems() as $item ) {
				$properties = $item->getProperties();
				if ( !isset( $properties['id'] ) ) {
					$this->logger->warning(
						__METHOD__ . ': Skipping item with no id.'
					);
					continue;
				}
				$id = $properties['id'];
				// Add item id as option for selection.
				$idOptions[$id] = $id;
				// Add item to info text.
				$postText .= Html::element( 'pre', [], $item );
			}
			$form = HTMLForm::factory(
				'ooui',
				$this->getConflictFormFields( $idOptions ),
				$this->getContext()
			);
			$form->setPreText(
				$this->msg( 'wikispeech-lexicon-add-entry-conflict' )->text()
			);
			// Display the existing items to help the user to decide
			// if they should update one of them or create a new one.
			if ( $this->getRequest()->getText( 'id', '' ) === '' ) {
				$message = 'wikispeech-lexicon-add-entry-success';
			} else {
				$message = 'wikispeech-lexicon-edit-entry-success';
			}
		} else {
			$form = HTMLForm::factory(
				'ooui',
				$this->getFormFields(),
				$this->getContext()
			);
			$message = 'wikispeech-lexicon-add-entry-success';
		}
		$form->setSubmitCallback( [ $this, 'submit' ] );
		$form->setPostText( $postText );
		if ( $form->show() ) {
			$this->success( $message );
		}
		$this->getOutput()->addModules( [
			'ext.wikispeech.specialEditLexicon'
		] );
	}

	/**
	 * Create a field descriptor for adding an entry
	 *
	 * @since 0.1.9
	 * @return array
	 */
	private function getFormFields() {
		// Get the page parameter to explicitly set it for the hidden
		// field.
		$page = $this->getRequest()->getIntOrNull( 'page' );
		$fields = [
			'language' => [
				'name' => 'language',
				'type' => 'select',
				'label' => $this->msg( 'wikispeech-language' )->text(),
				'options' => $this->getLanguageOptions(),
				'id' => 'ext-wikispeech-language'
			],
			'word' => [
				'name' => 'word',
				'type' => 'text',
				'label' => $this->msg( 'wikispeech-word' )->text(),
				'required' => true
			],
			'transcription' => [
				'name' => 'transcription',
				'type' => 'textwithbutton',
				'label' => $this->msg( 'wikispeech-transcription' )->text(),
				'required' => true,
				'id' => 'ext-wikispeech-transcription',
				'buttontype' => 'button',
				'buttondefault' => $this->msg( 'wikispeech-preview' )->text(),
				'buttonid' => 'ext-wikispeech-preview-button'
			],
			'page' => [
				'name' => 'page',
				'type' => 'hidden',
				'default' => $page
			]
		];
		return $fields;
	}

	/**
	 * Create a field descriptor for choosing to edit or add new entry
	 *
	 * @since 0.1.9
	 * @param array $itemOptions Options for the item id field.
	 * @return array
	 */
	private function getConflictFormFields( $itemOptions ) {
		$page = $this->getRequest()->getIntOrNull( 'page' );
		$fields = [
			'id' => [
				'name' => 'id',
				'type' => 'select',
				'label' => $this->msg( 'wikispeech-item-id' )->text(),
				'options' => $itemOptions,
				'default' => ''
			],
			// These fields are needed to send the data on
			// submit. Also, it is nice for the user to compare their
			// own entry to what is already in the lexicon.
			'language' => [
				'name' => 'language',
				'type' => 'text',
				'label' => $this->msg( 'wikispeech-language' )->text(),
				'id' => 'ext-wikispeech-language',
				'readonly' => true
			],
			'word' => [
				'name' => 'word',
				'type' => 'text',
				'label' => $this->msg( 'wikispeech-word' )->text(),
				'readonly' => true
			],
			'transcription' => [
				'name' => 'transcription',
				'type' => 'textwithbutton',
				'label' => $this->msg( 'wikispeech-transcription' )->text(),
				'required' => true,
				'id' => 'ext-wikispeech-transcription',
				'buttontype' => 'button',
				'buttondefault' => $this->msg( 'wikispeech-preview' )->text(),
				'buttonid' => 'ext-wikispeech-preview-button'
			],
			'page' => [
				'name' => 'page',
				'type' => 'hidden',
				'default' => $page
			]
		];
		return $fields;
	}

	/**
	 * Handle submit request
	 *
	 * If there is no entry for the given spelling, a new one is
	 * created with a new item. If there is an entry, the submit fails
	 * and the user is shown the conflict page. If the request
	 * contains an id, that item is updated or, if id is empty, a new
	 * item is created.
	 *
	 * @since 0.1.9
	 * @return bool
	 */
	public function submit() {
		$request = $this->getRequest();
		$language = $request->getText( 'language' );
		$word = $request->getText( 'word' );
		$entry = $this->lexiconStorage->getEntry( $language, $word );
		if ( $entry && !in_array( 'id', $request->getValueNames() ) ) {
			// An entry already exists for this spelling, but we have
			// not decided if we want to update or create a new entry.
			return false;
		}

		$id = $request->getText( 'id', '' );
		$request = $this->getRequest();
		$transcription = $request->getText( 'transcription' );
		$sampa = $this->speechoidConnector->ipaToSampa(
			$transcription,
			$language
		);
		$properties = [
			'strn' => $word,
			'transcriptions' => [ [ 'strn' => $sampa ] ],
			// Status is required by Speechoid.
			'status' => [
				'name' => 'ok'
			]
		];
		if ( $id === '' ) {
			// Empty id, create new item.
			$item = new LexiconEntryItem();
			$item->setProperties( $properties );
			$this->lexiconStorage->createEntryItem(
				$language,
				$word,
				$item
			);
		} else {
			// Id already exists, update item.
			$item = $entry->findItemBySpeechoidIdentity( intval( $id ) );
			if ( $item === null ) {
				throw new MWException( "No item with id '$id' found." );
			}
			$properties = $item->getProperties();
			$properties['transcriptions'] = [ [ 'strn' => $sampa ] ];
			$item->setProperties( $properties );
			$this->lexiconStorage->updateEntryItem(
				$language,
				$word,
				$item
			);
		}
		// Item is updated by createEntryItem(), so we just need to
		// store it.
		$this->modifiedItem = $item;
		// @todo Introduce $consumerUrl to request parameters and pass it down here.
		// @todo Currently we're passing null, meaning it only support flushing local wiki utterances.
		$this->purgeOriginPageUtterances( null );
		return true;
	}

	/**
	 * Immediately removes any utterance from the origin page, if set.
	 * @since 0.1.8
	 * @param string|null $consumerUrl
	 */
	private function purgeOriginPageUtterances( ?string $consumerUrl ) {
		$page = $this->getRequest()->getIntOrNull( 'page' );
		if ( $page !== null ) {
			$utteranceStore = new UtteranceStore();
			$utteranceStore->flushUtterancesByPage( $consumerUrl, $page );
		}
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
	 * Show success page containing the properties of the added/edited item
	 *
	 * @since 0.1.9
	 * @param string $message Success message.
	 */
	private function success( $message ) {
		$this->getOutput()->addHtml(
			Html::successBox(
				$this->msg( $message )->text()
			)
		);
		$this->getOutput()->addHtml(
			Html::element( 'pre', [], $this->modifiedItem )
		);
	}
}
