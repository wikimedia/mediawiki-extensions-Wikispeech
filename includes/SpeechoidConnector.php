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

	/** @var int Default timeout awaiting HTTP response in seconds. */
	private $defaultHttpResponseTimeoutSeconds;

	/**
	 * @param Config $config
	 * @since 0.1.5
	 */
	public function __construct( $config ) {
		$this->url = rtrim( $config->get( 'WikispeechSpeechoidUrl' ), '/' );

		if ( $config->get( 'WikispeechSpeechoidResponseTimeoutSeconds' ) ) {
			$this->defaultHttpResponseTimeoutSeconds = intval(
				$config->get( 'WikispeechSpeechoidResponseTimeoutSeconds' )
			);
		}
	}

	/**
	 * Make a request to Speechoid to synthesize the provided text.
	 *
	 * @since 0.1.5
	 * @param string $language
	 * @param string $voice
	 * @param string $text
	 * @param int|null $responseTimeoutSeconds Seconds before timing out awaiting response.
	 *  Falsy value defaults to config value WikispeechSpeechoidResponseTimeoutSeconds,
	 *  which if falsy (e.g. 0) defaults to MediaWiki default.
	 * @return array Response from Speechoid, parsed as associative array.
	 * @throws SpeechoidConnectorException On Speechoid I/O errors.
	 */
	public function synthesize(
		$language,
		$voice,
		$text,
		$responseTimeoutSeconds = null
	): array {
		$requestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$postData = [
			'lang' => $language,
			'voice' => $voice,
			'input' => $text
		];
		$options = [ 'postData' => $postData ];
		if ( $responseTimeoutSeconds ) {
			$options['timeout'] = $responseTimeoutSeconds;
		} elseif ( $this->defaultHttpResponseTimeoutSeconds ) {
			$options['timeout'] = $this->defaultHttpResponseTimeoutSeconds;
		}
		$responseString = $requestFactory->post( $this->url, $options );
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
