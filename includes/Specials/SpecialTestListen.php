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

	/** @var VoiceHandler */
	private $voiceHandler;

	/**
	 * @since 0.1.13
	 * @param LanguageNameUtils $languageNameUtils
	 * @param mixed $speechoidConnector
	 * @param VoiceHandler $voiceHandler
	 */
	public function __construct(
		$languageNameUtils,
		$speechoidConnector,
		VoiceHandler $voiceHandler
	) {
		parent::__construct( 'TestListen', 'wikispeech-listen' );

		$this->languageNameUtils = $languageNameUtils;
		$this->speechoidConnector = $speechoidConnector;
		$this->voiceHandler = $voiceHandler;
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
					'label' => $this->msg( 'wikispeech-testlisten-text' )->text()
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
				],
				'audioData' => [
					'name' => 'audioData',
					'type' => 'textarea',
					'label' => $this->msg( 'wikispeech-testlisten-audio-data' )->text(),
					'rows' => 5
				],
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

		$form->setSubmitCallback( function ( $data, $form ) {
			if ( $data['text'] ) {
				return $this->submitCallbackText( $data, $form );
			} elseif ( $data['audioData'] ) {
				return $this->submitCallbackAudioData( $data, $form );
			} else {
				return 'Either text or audio data must be provided.';
			}
		} );
		$form->show();
	}

	/**
	 * Make synthesized speech and add an audio element and a table for tokens.
	 *
	 * @param array $data Must contain 'text' or 'ssml'.
	 * @param HTMLForm $form
	 */
	private function submitCallbackText( array $data, HTMLForm $form ) {
		$language = $data['language'];
		$voice = $this->voiceHandler->getDefaultVoice( $language );
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
		$html = $this->makeAudioElement( $speechoidResponse['audio_data'] );
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
	}

	/**
	 * Create an audio element with audio data.
	 *
	 * @param string $audioData Base64 encoded Opus data.
	 * @return string
	 */
	private function makeAudioElement( string $audioData ) {
		$audioDataString = "data:audio/ogg;base64,$audioData";
		$html = Html::element( 'audio', [ 'controls' => '', 'src' => $audioDataString ] );
		return $html;
	}

	/**
	 * Add an audio element with the input audio data.
	 *
	 * @param array $data Must contain 'audioData'.
	 * @param HTMLForm $form
	 */
	private function submitCallbackAudioData( array $data, HTMLForm $form ) {
		$html = $this->makeAudioElement( $data['audioData'] );
		$form->addFooterHtml( $html );
	}
}
