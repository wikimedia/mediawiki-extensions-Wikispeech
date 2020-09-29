<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;

/**
 * Provide Speechoid access.
 *
 * @since 0.1.5
 */
class SpeechoidConnector {

	/** @var string Speechoid URL, without trailing slash. */
	private $url;

	public function __construct() {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		$this->url = rtrim( $config->get( 'WikispeechSpeechoidUrl' ), '/' );
	}

	/**
	 * Make a request to Speechoid to synthesize the provided text.
	 *
	 * @since 0.1.5
	 * @param string $language
	 * @param string $voice
	 * @param string $text
	 * @return array Response from Speechoid, parsed as associative array.
	 * @throws SpeechoidConnectorException On Speechoid I/O errors.
	 */
	public function synthesize(
		$language,
		$voice,
		$text
	): array {
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$requestParameters = [
			'lang' => $language,
			'voice' => $voice,
			'input' => $text
		];
		$responseString = $requestFactory->post(
			$this->url,
			[ 'postData' => $requestParameters ]
		);
		if ( !$responseString ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		$status = FormatJson::parse(
			$responseString,
			FormatJson::FORCE_ASSOC
		);
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException( 'Unexpected response from Speechoid.' );
		}
		$response = $status->getValue();
		return $response;
	}

	/**
	 * Retrieve and parse default voices per language from Speechoid.
	 *
	 * @since 0.1.5
	 * @return array Map language => voice
	 * @throws SpeechoidConnectorException On Speechoid I/O- or JSON parse errors.
	 */
	public function listDefaultVoicePerLanguage() : array {
		$defaultVoicesJson = $this->requestDefaultVoices();
		$status = FormatJson::parse(
			$defaultVoicesJson,
			FormatJson::FORCE_ASSOC
		);
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException( 'Unexpected response from Speechoid.' );
		}
		$defaultVoices = $status->getValue();
		$defaultVoicePerLanguage = [];
		foreach ( $defaultVoices as $voice ) {
			$defaultVoicePerLanguage[ $voice['lang'] ] = $voice['default_voice'];
		}
		return $defaultVoicePerLanguage;
	}

	/**
	 * Retrieve default voices par language from Speechoid
	 *
	 * @since 0.1.6
	 * @return string JSON response
	 * @throws SpeechoidConnectorException On Speechoid I/O error.
	 */
	public function requestDefaultVoices(): string {
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$responseString = $requestFactory->get( $this->url . '/default_voices' );
		if ( !$responseString ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		return $responseString;
	}

}
