<?php

use MediaWiki\MediaWikiServices;

/**
 * Class SpeechoidConnector
 *
 * Speechoid access
 * @since 0.1.5
 *
 * @todo list supported languages, voices and default voice per language.
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
	 * @param string $language
	 * @param string $voice
	 * @param string $text
	 * @return array Response from Speechoid, parsed as associative array.
	 * @throws SpeechoidConnectorException On Speechoid I/O errors.
	 * @since 0.1.5
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
		if ( $responseString == null ) {
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
	 * @return array Map language => voice
	 * @since 0.1.5
	 */
	public function listDefaultVoicePerLanguage() {
		// @todo awaits implementation in Speechoid Wikispeech-server
		return [];
	}
}
