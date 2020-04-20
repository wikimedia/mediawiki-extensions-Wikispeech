<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\MediaWikiServices;

class ApiWikispeechListen extends ApiBase {

	/**
	 * Execute an API request.
	 *
	 * @since 0.1.3
	 */
	public function execute() {
		$requestFactory =
			MediaWikiServices::getInstance()->getHttpRequestFactory();
		$config = MediaWikiServices::getInstance()->
			getConfigFactory()->
			makeConfig( 'wikispeech' );
		$serverUrl = $config->get( 'WikispeechServerUrl' );
		$inputParameters = $this->extractRequestParams();
		self::validateParameters( $inputParameters );
		$speechoidParameters = [
			'lang' => $inputParameters['lang'],
			'voice' => $inputParameters['voice'],
			'input' => $inputParameters['input']
		];
		$responseString = $requestFactory->post(
			$serverUrl,
			[ 'postData' => $speechoidParameters ]
		);
		$speechoidResponse = FormatJson::parse(
			$responseString,
			FormatJson::FORCE_ASSOC
		)->getValue();
		$response = [
			'audio' => $speechoidResponse['audio'],
			'tokens' => $speechoidResponse['tokens'],
			'speechoid-response' => $speechoidResponse
		];
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$response
		);
	}

	/**
	 * Validate the parameters for language and voice.
	 *
	 * The parameter values are checked against the extension
	 * configuration. These may differ from what is actually running
	 * on the Speechoid server.
	 *
	 * @since 0.1.3
	 * @param array $parameters Request parameters.
	 * @throws ApiUsageException
	 */
	private function validateParameters( $parameters ) {
		$config = MediaWikiServices::getInstance()->
			getConfigFactory()->
			makeConfig( 'wikispeech' );
		$voices = $config->get( 'WikispeechVoices' );
		$language = $parameters['lang'];

		// Validate language.
		$validLanguages = array_keys( $voices );
		if ( !in_array( $language, $validLanguages ) ) {
			$this->dieWithError( [
				'apierror-wikispeechlisten-invalid-language',
				$language,
				self::makeValuesString( $validLanguages )
			] );
		}

		// Validate voice.
		$voice = $parameters['voice'];
		if ( $voice ) {
			$validVoices = $voices[$language];
			if ( !in_array( $voice, $validVoices ) ) {
				$this->dieWithError( [
					'apierror-wikispeechlisten-invalid-voice',
					$voice,
					self::makeValuesString( $validVoices )
				] );
			}
		}

		// Validate input text.
		$input = $parameters['input'];
		$numberOfCharactersInInput = mb_strlen( $input );
		$maximumNumberOfCharacterInInput = $config->get( 'WikispeechListenMaximumInputCharacters' );
		if ( $numberOfCharactersInInput > $maximumNumberOfCharacterInInput ) {
			$this->dieWithError( [
				'apierror-wikispeechlisten-invalid-input-too-long',
				$maximumNumberOfCharacterInInput,
				$numberOfCharactersInInput
			] );
		}
	}

	/**
	 * Make a formatted string of values to be used in messages.
	 *
	 * @since 0.1.3
	 * @param array $values Values as strings.
	 * @return string The input strings wrapped in <kbd> tags and
	 *  joined by commas.
	 */
	private static function makeValuesString( $values ) {
		$valueStrings = [];
		foreach ( $values as $value ) {
			array_push(
				$valueStrings,
				"<kbd>$value</kbd>"
			);
		}
		$valuesString = implode( ', ', $valueStrings );
		return $valuesString;
	}

	/**
	 * Specify what parameters the API accepts.
	 *
	 * @since 0.1.3
	 * @return array
	 */
	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			[
				'lang' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
				],
				'input' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
				],
				'voice' => [
					ApiBase::PARAM_TYPE => 'string'
				]

			]
		);
	}

	/**
	 * Give examples of usage.
	 *
	 * @since 0.1.3
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=wikispeechlisten&format=json&lang=en&input=Read this'
			=> 'apihelp-wikispeechlisten-example-1',
			'action=wikispeechlisten&format=json&lang=en&input=Read this&voice=cmu-slt-flite'
			=> 'apihelp-wikispeechlisten-example-2'
		];
	}
}
