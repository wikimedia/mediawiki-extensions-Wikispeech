<?php

namespace MediaWiki\Wikispeech\Specials;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Wikispeech\SpeechoidConnector;
use MediaWiki\Wikispeech\VoiceHandler;
use SpecialPage;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Codex\Utility\Sanitizer;

/**
 * Special page for listening to a synthesised utterance.
 *
 * @since 0.1.13
 */

class SpecialTestListen extends SpecialPage {
	use LanguageOptionsTrait;

	/** @var SpeechoidConnector */
	private $speechoidConnector;

	/**
	 * @since 0.1.13
	 * @param LanguageNameUtils $languageNameUtils
	 * @param mixed $speechoidConnector
	 */
	public function __construct(
		$languageNameUtils,
		$speechoidConnector
	) {
		parent::__construct( 'TestListen', 'wikispeech-listen' );

		$this->languageNameUtils = $languageNameUtils;
		$this->speechoidConnector = $speechoidConnector;
	}

	/**
	 * @since 0.1.13
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$form = HTMLForm::factory(
			'codex',
			[
				'text' => [
					'name' => 'text',
					'type' => 'text',
					'label' => $this->msg( 'wikispeech-testlisten-text' )->text(),
					'required' => true
				],
				'language' => [
					'name' => 'language',
					'type' => 'select',
					'label' => $this->msg( 'wikispeech-language' )->text(),
					'options' => $this->getLanguageOptions()
				],
				'ssml' => [
					'name' => 'ssml',
					'type' => 'check',
					'label' => $this->msg( 'wikispeech-testlisten-ssml' )->text()
				]
			],
			$this->getContext()
		);

		$codex = new Codex();
		// phpcs:ignore Generic.Files.LineLength
		$ssmlSpeakTag = '<speak xml:lang="en-US" version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemalocation="http://www.w3.org/2001/10/synthesis http://www.w3.org/TR/speech-synthesis/synthesis.xsd">...</speak>';
		$sanitizer = new Sanitizer();
		$tag = $sanitizer->sanitizeText( $ssmlSpeakTag );
		// This note intentionally doesn't use messages. It's likely that it
		// will change or be removed before it's relevant to end users.
		$noteContent = "<p>This page is only intened to help developers.</p>"
			. '<p>When SSML is enabled the input has to be a speak tag like the one below.</p>'
			. "<pre>$tag</pre>";
		$note = $codex
			->message()
			->setType( 'notice' )
			->setHeading( 'For development' )
			->setContentHtml(
				$codex
					->htmlSnippet()
					->setContent( $noteContent )
					->build()
			)
			->build()
			->getHtml();
		$form->addHeaderHtml( $note );

		$form->setSubmitCallback( function ( array $data, HTMLForm $form ) {
			$logger = LoggerFactory::getInstance( 'Wikispeech' );
			$config = MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'wikispeech' );
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$voiceHandler = new VoiceHandler(
				$logger,
				$config,
				$this->speechoidConnector,
				$cache
			);
			$language = $data['language'];
			$voice = $voiceHandler->getDefaultVoice( $language );
			$speechoidData = [];
			if ( $data['ssml'] ) {
				$speechoidData['ssml'] = $data['text'];
			} else {
				$speechoidData['text'] = $data['text'];
			}
			$speechoidResponse = $this->speechoidConnector->synthesize(
				$language,
				$voice,
				$speechoidData
			);
			$tokens = json_encode( $speechoidResponse['tokens'] );
			$audioDataString = 'data:audio/ogg;base64,' . $speechoidResponse['audio_data'];
			$html = Html::element( 'audio', [ 'controls' => '', 'src' => $audioDataString ], $tokens );
			$html .= Html::openElement( 'table', [ 'class' => 'wikitable' ] );
			$html .= Html::openElement( 'tr' );
			$html .= Html::element( 'th', [], 'orth' );
			$html .= Html::element( 'th', [], 'expanded' );
			$html .= Html::element( 'th', [], 'endtime' );
			$html .= Html::openElement( 'tr' );
			foreach ( $speechoidResponse['tokens'] as $token ) {
				$html .= Html::openElement( 'tr' );
				$html .= Html::openElement( 'td' )
					. Html::element( 'code', [], $token['orth'] )
					. Html::closeElement( 'td' );
				$html .= Html::openElement( 'td' );
				if ( array_key_exists( 'expanded', $token ) ) {
					$html .= Html::element( 'code', [], $token['expanded'] );
				}
				$html .= Html::closeElement( 'td' );
				$html .= Html::element( 'td', [], $token['endtime'] );
				$html .= Html::closeElement( 'tr' );
			}
			$html .= Html::openElement( 'table' );
			$form->addFooterHtml( $html );
		} );
		$form->show();
	}
}
