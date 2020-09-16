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
 * @todo List supported languages, voices and default voice per language.
 *
 * @since 0.1.5
 */
class SpeechoidConnector {

	private $url;

	public function __construct() {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'wikispeech' );
		$this->url = $config->get( 'WikispeechSpeechoidUrl' );
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
		$requestFactory =
			MediaWikiServices::getInstance()->getHttpRequestFactory();
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
	 * Retrieve default voice setup from Speechoid.
	 *
	 * @since 0.1.5
	 * @return array Map language => voice
	 */
	public function listDefaultVoicePerLanguage() {
		// @todo Awaits implementation in Speechoid Wikispeech-server.
		return [];
	}
}
