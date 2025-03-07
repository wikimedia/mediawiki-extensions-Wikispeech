<?php

namespace MediaWiki\Wikispeech\Specials;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use HTMLForm;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Wikispeech\Lexicon\LexiconEntry;
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

	/** @var string */
	private $postHtml;

	/**
	 * @since 0.1.11 Removed ConfigFactory
	 * @since 0.1.8
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LexiconStorage $lexiconStorage
	 * @param SpeechoidConnector $speechoidConnector
	 */
	public function __construct(
		$languageNameUtils,
		$lexiconStorage,
		$speechoidConnector
	) {
		parent::__construct( 'EditLexicon', 'wikispeech-edit-lexicon' );
		$this->languageNameUtils = $languageNameUtils;
		$this->lexiconStorage = $lexiconStorage;
		$this->speechoidConnector = $speechoidConnector;
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
		$this->postHtml = '';
	}

	/**
	 * @since 0.1.8
	 * @param string|null $subpage
	 */
	public function execute( $subpage ) {
		$this->setHeaders();
		if ( $this->redirectToLoginPage() ) {
			return;
		}

		$this->checkPermissions();
		$this->addHelpLink( 'Help:Extension:Wikispeech/Lexicon editor' );
		$request = $this->getRequest();
		$language = $request->getText( 'language' );
		$word = $request->getText( 'word' );
		if ( $request->getText( 'id' ) === '' ) {
			$id = '';
		} else {
			$id = $request->getIntOrNull( 'id' );
		}
		$entry = $this->lexiconStorage->getEntry( $language, $word );
		$copyrightNote = $this->msg( 'wikispeech-lexicon-copyrightnote' )->parse();
		$this->postHtml = Html::rawElement( 'p', [], $copyrightNote );
		$successMessage = '';

		$formId = '';
		$submitMessage = 'wikispeech-lexicon-next';
		if ( !$language || !$word ) {
			$formId = 'lookup';
			$fields = $this->getLookupFields();
		} elseif ( $entry === null ) {
			$formId = 'newEntry';
			$fields = $this->getAddFields( $language, $word );
			$submitMessage = 'wikispeech-lexicon-save';
			$successMessage = 'wikispeech-lexicon-add-entry-success';
		} elseif ( !in_array( 'id', $request->getValueNames() ) ) {
			$formId = 'selectItem';
			$fields = $this->getSelectFields( $language, $word, $entry );
		} elseif ( $id ) {
			$formId = 'editItem';
			$fields = $this->getEditFields( $language, $word, $id );
			$submitMessage = 'wikispeech-lexicon-save';
			$successMessage = 'wikispeech-lexicon-edit-entry-success';
		} elseif ( $id === '' ) {
			$formId = 'newItem';
			$fields = $this->getAddFields( $language, $word );
			$submitMessage = 'wikispeech-lexicon-save';
			$successMessage = 'wikispeech-lexicon-add-entry-success';
		} else {
			// We have a set of parameters that we can't do anything
			// with. Show the first page.
			$formId = 'lookup';
			$fields = $this->getLookupFields();
		}

		// Set default values from the parameters.
		foreach ( $fields as $field ) {
			$name = $field['name'];
			$value = $request->getVal( $name );
			if ( $value !== null ) {
				// There's no extra conversion logic so default values
				// are set to strings and handled down the
				// line. E.g. boolean values are true for "false" or
				// "no".
				$fields[$name]['default'] = $value;
			}
		}
		$form = HTMLForm::factory(
			'ooui',
			$fields,
			$this->getContext()
		);
		$form->setFormIdentifier( $formId );
		$form->setSubmitCallback( [ $this, 'submit' ] );
		$form->setSubmitTextMsg( $submitMessage );
		$form->setPostHtml( $this->postHtml );
		if ( $form->show() && $successMessage ) {
			$this->success( $successMessage );
		}

		$this->getOutput()->addModules( [
			'ext.wikispeech.specialEditLexicon'
		] );
	}

	/**
	 * Redirect the user to login page when appropriate
	 *
	 * If the the user is not logged in and config variable
	 * `WikispeechEditLexiconAutoLogin` is true this adds a redirect
	 * to the output. Returns to this special page after login and
	 * keeps any URL parameters that were originally given.
	 *
	 * @since 0.1.11
	 * @return bool True if a redirect was added, else false.
	 */
	private function redirectToLoginPage(): bool {
		if ( $this->getUser()->isNamed() ) {
			// User already logged in.
			return false;
		}

		if ( !$this->getConfig()->get( 'WikispeechEditLexiconAutoLogin' ) ) {
			return false;
		}

		$lexiconUrl = $this->getPageTitle()->getPrefixedText();
		$lexiconRequest = $this->getRequest();

		$loginParemeters = [
			'returnto' => $lexiconUrl
		];
		if ( $lexiconRequest->getValues() ) {
			// Add any parameters to this page to include after
			// logging in.
			$lexiconParametersString = http_build_query( $lexiconRequest->getValues() );
			$loginParemeters['returntoquery'] = $lexiconParametersString;
		}
		$title = SpecialPage::getTitleFor( 'Userlogin' );
		$loginUrl = $title->getFullURL( $loginParemeters );
		$this->getOutput()->redirect( $loginUrl );

		return true;
	}

	/**
	 * Create a field descriptor for looking up a word
	 *
	 * Has one field for language and one for word.
	 *
	 * @since 0.1.10
	 * @return array
	 */
	private function getLookupFields(): array {
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
			'page' => [
				'name' => 'page',
				'type' => 'hidden'
			]
		];
		return $fields;
	}

	/**
	 * Create a field descriptor for selecting an item
	 *
	 * Has a field for selecting the id of the item to edit or "new"
	 * for creating a new item. Also shows fields for language and
	 * word from previous page, but readonly.
	 *
	 * @since 0.1.10
	 * @param string $language
	 * @param string $word
	 * @param LexiconEntry|null $entry
	 * @return array
	 */
	private function getSelectFields(
		string $language,
		string $word,
		?LexiconEntry $entry = null
	): array {
		$fields = $this->getLookupFields();
		$fields['language']['readonly'] = true;
		$fields['language']['type'] = 'text';
		$fields['word']['readonly'] = true;
		$fields['word']['required'] = false;

		$newLabel = $this->msg( 'wikispeech-lexicon-new' )->text();
		$itemOptions = [ $newLabel => '' ];
		if ( $entry ) {
			foreach ( $entry->getItems() as $item ) {
				$properties = $item->getProperties();
				if ( !isset( $properties->id ) ) {
					$this->logger->warning(
						__METHOD__ . ': Skipping item with no id.'
					);
					continue;
				}
				$id = $properties->id;
				// Add item id as option for selection.
				$itemOptions[$id] = $id;
				// Add item to info text.
				$this->postHtml .= Html::element( 'pre', [], $item );
			}
		}

		$fields['id'] = [
			'name' => 'id',
			'type' => 'select',
			'label' => $this->msg( 'wikispeech-item-id' )->text(),
			'options' => $itemOptions,
			'default' => ''
		];
		return $fields;
	}

	/**
	 * Create a field descriptor for adding an entry or item
	 *
	 * Has fields for transcription and preferred. Item id is held by
	 * a hidden field. Also shows fields for language and word from
	 * previous page, but readonly.
	 *
	 * @since 0.1.10
	 * @param string $language
	 * @param string $word
	 * @return array
	 */
	private function getAddFields( string $language, string $word ): array {
		$fields = $this->getSelectFields( $language, $word );
		$fields['id']['type'] = 'hidden';
		$fields += [
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
			'preferred' => [
				'name' => 'preferred',
				'type' => 'check',
				'label' => $this->msg( 'wikispeech-preferred' )->text()
			]
		];
		return $fields;
	}

	/**
	 * Create a field descriptor for editing an item
	 *
	 * Has fields for transcription and preferred with default values
	 * from the lexicon. Item id is held by a hidden field. Also shows
	 * fields for language and word from previous page, but readonly.
	 *
	 * @since 0.1.10
	 * @param string $language
	 * @param string $word
	 * @param int $id
	 * @return array
	 */
	private function getEditFields( string $language, string $word, int $id ): array {
		$fields = $this->getAddFields( $language, $word );
		$entry = $this->lexiconStorage->getEntry( $language, $word );
		$item = $entry->findItemBySpeechoidIdentity( $id );
		if ( $item === null ) {
			throw new MWException( "No item with id '$id' found." );
			// TODO: Show error message (T308562).
		}
		$transcriptionStatus = $this->speechoidConnector->toIpa(
			$item->getTranscription(),
			$language
		);
		if ( $transcriptionStatus->isOk() ) {
			$transcription = $transcriptionStatus->getValue();
		} else {
			$transcription = '';
			// TODO: Show error message (T308562).
		}

		$fields['transcription']['default'] = $transcription;
		$fields['preferred']['default'] = $item->getPreferred();
		return $fields;
	}

	/**
	 * Handle submit request
	 *
	 * If there is no entry for the given word a new one is created
	 * with a new item. If the request contains an id that item is
	 * updated or, if id is empty, a new item is created. If there
	 * isn't enough information to do any of the above this returns
	 * false which sends the user to the appropriate page via
	 * `execute()`.
	 *
	 * @since 0.1.9
	 * @param array $data
	 * @return bool
	 */
	public function submit( array $data ): bool {
		if (
			!array_key_exists( 'language', $data ) ||
			!array_key_exists( 'word', $data ) ||
			!array_key_exists( 'id', $data ) ||
			!array_key_exists( 'transcription', $data ) ||
			$data['transcription'] === null ||
			!array_key_exists( 'preferred', $data )
		) {
			// We don't have all the information we need to make an
			// edit yet.
			return false;
		}

		$language = $data['language'];
		$transcription = $data['transcription'];
		$sampaStatus = $this->speechoidConnector->fromIpa(
			$transcription,
			$language
		);
		if ( !$sampaStatus->isOk() ) {
			// TODO: Show error message (T308562).
			return false;
		}

		$sampa = $sampaStatus->getValue();
		$word = $data['word'];
		$id = $data['id'];
		$preferred = $data['preferred'];
		if ( $id === '' ) {
			// Empty id, create new item.
			$item = new LexiconEntryItem();
			$properties = [
				'strn' => $word,
				'transcriptions' => [ (object)[ 'strn' => $sampa ] ],
				// Status is required by Speechoid.
				'status' => (object)[
					'name' => 'ok'
				]
			];
			if ( $preferred ) {
				$properties['preferred'] = true;
			}
			$item->setProperties( (object)$properties );
			$this->lexiconStorage->createEntryItem(
				$language,
				$word,
				$item
			);
		} else {
			// Id already exists, update item.
			$entry = $this->lexiconStorage->getEntry( $language, $word );
			$item = $entry->findItemBySpeechoidIdentity( intval( $id ) );
			if ( $item === null ) {
				throw new MWException( "No item with id '$id' found." );
			}
			$properties = $item->getProperties();
			$properties->transcriptions = [ (object)[ 'strn' => $sampa ] ];
			if ( $preferred ) {
				$properties->preferred = true;
			} else {
				unset( $properties->preferred );
			}

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

		if ( array_key_exists( 'page', $data ) && $data['page'] ) {
			// @todo Introduce $consumerUrl to request parameters and
			// @todo pass it down here. Currently we're passing null,
			// @todo meaning it only support flushing local wiki
			// @todo utterances.
			$this->purgeOriginPageUtterances( $data['page'], null );
		}

		return true;
	}

	/**
	 * Immediately removes any utterance from the origin page.
	 * @since 0.1.8
	 * @param int $pageId
	 * @param string|null $consumerUrl
	 */
	private function purgeOriginPageUtterances( int $pageId, ?string $consumerUrl ) {
		$utteranceStore = new UtteranceStore();
		$utteranceStore->flushUtterancesByPage( $consumerUrl, $pageId );
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

	/**
	 * Show success page containing the properties of the added/edited item
	 *
	 * @since 0.1.9
	 * @param string $message Success message.
	 */
	private function success( $message ) {
		$this->getOutput()->addHtml(
			Html::successBox(
				$this->msg( $message )->parse()
			)
		);
		$this->getOutput()->addHtml(
			Html::element( 'pre', [], $this->modifiedItem )
		);
	}
}
