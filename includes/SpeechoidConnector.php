<?php

namespace MediaWiki\Wikispeech;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Config;
use FormatJson;
use InvalidArgumentException;
use MediaWiki\Http\HttpRequestFactory;

/**
 * Provide Speechoid access.
 *
 * @since 0.1.5
 */
class SpeechoidConnector {

	/** @var HttpRequestFactory */
	private $requestFactory;

	/** @var string Speechoid URL, without trailing slash. */
	private $url;

	/** @var int Default timeout awaiting HTTP response in seconds. */
	private $defaultHttpResponseTimeoutSeconds;

	/**
	 * @param Config $config
	 * @param HttpRequestFactory $requestFactory
	 * @since 0.1.5
	 */
	public function __construct( $config, $requestFactory ) {
		$this->url = rtrim( $config->get( 'WikispeechSpeechoidUrl' ), '/' );

		if ( $config->get( 'WikispeechSpeechoidResponseTimeoutSeconds' ) ) {
			$this->defaultHttpResponseTimeoutSeconds = intval(
				$config->get( 'WikispeechSpeechoidResponseTimeoutSeconds' )
			);
		}
		$this->requestFactory = $requestFactory;
	}

	/**
	 * Make a request to Speechoid to synthesize the provided text or ipa string.
	 *
	 * @since 0.1.5
	 * @param string $language
	 * @param string $voice
	 * @param array $parameters Should contain either 'text' or
	 *  'ipa'. Determines input string and type.
	 * @param int|null $responseTimeoutSeconds Seconds before timing out awaiting response.
	 *  Falsy value defaults to config value WikispeechSpeechoidResponseTimeoutSeconds,
	 *  which if falsy (e.g. 0) defaults to MediaWiki default.
	 * @return array Response from Speechoid, parsed as associative array.
	 * @throws SpeechoidConnectorException On Speechoid I/O errors.
	 */
	public function synthesize(
		$language,
		$voice,
		$parameters,
		$responseTimeoutSeconds = null
	): array {
		$postData = [
			'lang' => $language,
			'voice' => $voice
		];
		$options = [];
		if ( $responseTimeoutSeconds ) {
			$options['timeout'] = $responseTimeoutSeconds;
		} elseif ( $this->defaultHttpResponseTimeoutSeconds ) {
			$options['timeout'] = $this->defaultHttpResponseTimeoutSeconds;
		}
		if ( isset( $parameters['ipa'] ) ) {
			$postData['input'] = $parameters['ipa'];
			$postData['input_type'] = 'ipa';
		} elseif ( isset( $parameters['text'] ) ) {
			$postData['input'] = $parameters['text'];
		} else {
			throw new InvalidArgumentException(
				'$parameters must contain one of "text" and "ipa".'
			);
		}
		$options = [ 'postData' => $postData ];
		$responseString = $this->requestFactory->post( $this->url, $options );
		if ( !$responseString ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		$status = FormatJson::parse(
			$responseString,
			FormatJson::FORCE_ASSOC
		);
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException(
				'Unexpected response from Speechoid: ' . $responseString
			);
		}
		$response = $status->getValue();
		return $response;
	}

	/**
	 * Make a request to Speechoid to synthesize the provided text.
	 *
	 * @since 0.1.8
	 * @param string $language
	 * @param string $voice
	 * @param string $text
	 * @param int|null $responseTimeoutSeconds
	 * @return array
	 */
	public function synthesizeText(
		$language,
		$voice,
		$text,
		$responseTimeoutSeconds = null
	): array {
		return $this->synthesize(
			$language,
			$voice,
			[ 'text' => $text ],
			$responseTimeoutSeconds
		);
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
	 * @throws SpeechoidConnectorException On Speechoid I/O error or
	 *  if URL is invalid.
	 */
	public function requestDefaultVoices(): string {
		if ( !filter_var( $this->url, FILTER_VALIDATE_URL ) ) {
			throw new SpeechoidConnectorException( 'No Speechoid URL provided.' );
		}
		$responseString = $this->requestFactory->get( $this->url . '/default_voices' );
		if ( !$responseString ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		return $responseString;
	}

}
