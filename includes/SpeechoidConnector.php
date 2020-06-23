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
	 * @return string JSON response
	 * @throws SpeechoidConnectorException On Speechoid I/O errors.
	 * @since 0.1.5
	 */
	public function synthesize(
		$language,
		$voice,
		$text
	): string {
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
		return $responseString;
	}

}
