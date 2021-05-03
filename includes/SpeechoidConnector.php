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
use Status;

/**
 * Provide Speechoid access.
 *
 * @since 0.1.5
 */
class SpeechoidConnector {

	/** @var Config */
	private $config;

	/** @var string Speechoid URL, without trailing slash. */
	private $url;

	/** @var int Default timeout awaiting HTTP response in seconds. */
	private $defaultHttpResponseTimeoutSeconds;

	/** @var HttpRequestFactory */
	private $requestFactory;

	/**
	 * @since 0.1.5
	 * @param Config $config
	 * @param HttpRequestFactory $requestFactory
	 */
	public function __construct( $config, $requestFactory ) {
		$this->config = $config;
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
		return $status->getValue();
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

	/**
	 * An array of items such as:
	 * {
	 *   "name": "sv_se_nst_lex:sv-se.nst",
	 *   "symbolSetName": "sv-se_ws-sampa",
	 *   "locale": "sv_SE",
	 *   "entryCount": 919476
	 * }
	 *
	 * This list includes all registered lexicons,
	 * including those that are not in use by any voice.
	 *
	 * @since 0.1.8
	 * @return array Parsed JSON response as an associative array
	 * @throws SpeechoidConnectorException
	 */
	public function requestLexicons(): array {
		$json = $this->requestFactory->get(
			$this->url . '/lexserver/lexicon/list'
		);
		if ( !$json ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		$status = FormatJson::parse(
			$json,
			FormatJson::FORCE_ASSOC
		);
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException( 'Unexpected response from Speechoid.' );
		}
		return $status->getValue();
	}

	/**
	 * This includes all registered lexicons,
	 * including those that are not in use by any voice.
	 *
	 * Case insensitive prefix matching query.
	 * I.e. $locale 'en' will match both 'en_US' and 'en_NZ'.
	 *
	 * @see requestLexicons
	 * @since 0.1.8
	 * @param string $locale
	 * @return string|null Name of lexicon, or null if not found.
	 * @throws SpeechoidConnectorException
	 */
	public function findLexiconByLocale(
		string $locale
	): ?string {
		$locale = strtolower( $locale );
		$lexicons = $this->requestLexicons();
		$matches = [];
		foreach ( $lexicons as $lexicon ) {
			$lexiconLocale = $lexicon['locale'];
			$lexiconLocale = strtolower( $lexiconLocale );
			$isMatching = str_starts_with( $lexiconLocale, $locale );
			if ( $isMatching ) {
				$matches[] = $lexicon;
			}
		}
		$numberOfMatches = count( $matches );
		if ( $numberOfMatches === 0 ) {
			return null;
		} elseif ( $numberOfMatches > 1 ) {
			throw new SpeechoidConnectorException(
				'Multiple lexicons matches locale:' .
				FormatJson::encode( $matches, true )
			);
		}
		return $matches[0]['name'];
	}

	/**
	 * An array of items such as:
	 * {
	 *   "components": [
	 * 	 {
	 *     "call": "marytts_preproc",
	 * 	   "mapper": {
	 * 	     "from": "sv-se_ws-sampa",
	 * 	     "to": "sv-se_sampa_mary"
	 * 	   },
	 * 	   "module": "adapters.marytts_adapter"
	 * 	 },
	 * 	 {
	 * 	   "call": "lexLookup",
	 * 	   "lexicon": "sv_se_nst_lex:sv-se.nst",
	 * 	   "module": "adapters.lexicon_client"
	 * 	 }
	 * 	 ],
	 * 	 "config_file": "wikispeech_server/conf/voice_config_marytts.json",
	 * 	 "default": true,
	 * 	 "lang": "sv",
	 * 	 "name": "marytts_textproc_sv"
	 * }
	 *
	 * This list includes the lexicons for all registered voices,
	 * even if the voice is currently unavailable.
	 *
	 * @since 0.1.8
	 * @return array Parsed JSON response as associative array
	 * @throws SpeechoidConnectorException
	 */
	public function requestTextProcessors(): array {
		$json = $this->requestFactory->get(
			$this->url . '/textprocessing/textprocessors'
		);
		if ( !$json ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		$status = FormatJson::parse(
			$json,
			FormatJson::FORCE_ASSOC
		);
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException( 'Unexpected response from Speechoid.' );
		}
		return $status->getValue();
	}

	/**
	 * This includes the lexicons for all registered voices,
	 * even if the voice is currently unavailable.
	 * Response is in form such as 'sv_se_nst_lex:sv-se.nst',
	 * where prefix and suffix split by : is used differently throughout Speechoid
	 * e.g combined, prefix only or suffix only, for identifying items.
	 *
	 * @see requestTextProcessors
	 * @since 0.1.8
	 * @param string $language Case insensitive language code, e.g. 'en'.
	 * @return string|null Name of lexicon, or null if not found.
	 * @throws SpeechoidConnectorException
	 */
	public function findLexiconByLanguage(
		string $language
	): ?string {
		$language = strtolower( $language );
		$lexicons = $this->requestTextProcessors();
		$matches = [];
		foreach ( $lexicons as $lexicon ) {
			$lexiconLang = strtolower( $lexicon['lang'] );
			if ( $lexiconLang == $language ) {
				$matches[] = $lexicon;
			}
		}
		$numberOfMatches = count( $matches );
		if ( $numberOfMatches === 0 ) {
			return null;
		} elseif ( $numberOfMatches > 1 ) {
			throw new SpeechoidConnectorException(
				'Multiple lexicon matches language' .
				FormatJson::encode( $matches, true )
			);
		}
		foreach ( $matches[0]['components'] as $component ) {
			if (
				array_key_exists( 'call', $component ) &&
				$component['call'] === 'lexLookup'
			) {
				return $component['lexicon'];
			}
		}
		return null;
	}

	/**
	 * An array of items such as:
	 * {
	 *   "id": 808498,
	 *   "lexRef": {
	 *     "dbRef": "sv_se_nst_lex",
	 *	   "lexName": "sv-se.nst"
	 *	 },
	 *   "strn": "tomten",
	 *   "language": "sv-se",
	 *   "partOfSpeech": "NN",
	 *   "morphology": "SIN|DEF|NOM|UTR",
	 *   "wordParts": "tomten",
	 *   "lemma": {
	 *     "id": 92909,
	 *     "strn": "tomte",
	 *     "paradigm": "s2b-båge"
	 *   },
	 *   "transcriptions": [
	 *     {
	 *       "id": 814660,
	 *       "entryId": 808498,
	 *       "strn": "\"\" t O m . t e n",
	 *       "language": "sv-se",
	 *       "sources": [
	 *         "nst"
	 *       ]
	 *    }
	 *  ],
	 *  "status": {
	 *     "id": 808498,
	 *     "name": "imported",
	 *     "source": "nst",
	 *     "timestamp": "2018-06-18T08:51:25Z",
	 *     "current": true
	 *   }
	 * }
	 *
	 * @since 0.1.8
	 * @param string $lexicon
	 * @param string[] $words
	 * @return Status If successful, value contains deserialized json response.
	 * @throws SpeechoidConnectorException
	 * @throws InvalidArgumentException If words array is empty.
	 */
	public function lookupLexiconEntries(
		string $lexicon,
		array $words
	): Status {
		if ( $words === [] ) {
			throw new InvalidArgumentException( 'Must contain at least one word' );
		}
		$responseString = $this->requestFactory->get(
			wfAppendQuery(
				$this->url . '/lexserver/lexicon/lookup',
				[
					'lexicons' => $lexicon,
					'words' => implode( ",", $words )
				]
			)
		);
		if ( !$responseString ) {
			throw new SpeechoidConnectorException( 'Unable to communicate with Speechoid.' );
		}
		return FormatJson::parse( $responseString, FormatJson::FORCE_ASSOC );
	}

	/**
	 * @since 0.1.8
	 * @param string $json A single entry object item.
	 *  I.e. not an array as returned by {@link lookupLexiconEntries}.
	 * @return Status If successful, value contains deserialized json response (updated entry item)
	 */
	public function updateLexiconEntry(
		string $json
	): Status {
		$responseString = $this->requestFactory->get(
			wfAppendQuery(
				$this->url . '/lexserver/lexicon/updateentry',
				[ 'entry' => $json ]
			)
		);
		return FormatJson::parse( $responseString, FormatJson::FORCE_ASSOC );
	}

	/**
	 * Deletes a lexicon entry item
	 *
	 * @since 0.1.8
	 * @param string $lexiconName
	 * @param int $identity
	 * @return Status
	 */
	public function deleteLexiconEntry(
		string $lexiconName,
		int $identity
	): Status {
		$responseString = $this->requestFactory->get(
			$this->url . '/lexserver/lexicon/delete_entry/' .
		   urlencode( $lexiconName ) . '/' . $identity
		);
		// If successful, returns something like:
		// deleted entry id '11' from lexicon 'sv'
		// where the lexicon is the second part of the lexicon name:lang.
		if ( mb_ereg_match(
			"deleted entry id '(.+)' from lexicon '(.+)'",
			$responseString
		) ) {
			return Status::newGood( $responseString );
		}
		return Status::newFatal( $responseString );
	}

	/**
	 * {
	 *   "strn": "flesk",
	 *   "language": "sv-se",
	 *   "partOfSpeech": "NN",
	 *   "morphology": "SIN-PLU|IND|NOM|NEU",
	 *   "wordParts": "flesk",
	 *   "lemma": {
	 *     "strn": "flesk",
	 *     "reading": "",
	 *     "paradigm": "s7n-övriga ex träd"
	 *   },
	 *   "transcriptions": [
	 *     {
	 *       "strn": "\" f l E s k",
	 *       "language": "sv-se"
	 *     }
	 *   ]
	 * }
	 *
	 * @since 0.1.8
	 * @param string $lexiconName E.g. 'wikispeech_lexserver_testdb:sv'
	 * @param string $json A single entry object item.
	 *  I.e. not an array as returned by {@link lookupLexiconEntries}.
	 * @return Status value set to int identity of newly created entry.
	 * @throws SpeechoidConnectorException
	 */
	public function addLexiconEntry(
		string $lexiconName,
		string $json
	): Status {
		$responseString = $this->requestFactory->get(
			wfAppendQuery(
				$this->url . '/lexserver/lexicon/addentry',
				[
					'lexicon_name' => $lexiconName,
					'entry' => $json
				]
			)
		);
		// @todo how do we know if this was successful? Always return 200

		$deserializedStatus = FormatJson::parse( $responseString, FormatJson::FORCE_ASSOC );
		if ( !$deserializedStatus->isOK() ) {
			throw new SpeechoidConnectorException( "Failed to parse response as JSON: $responseString" );
		}
		/** @var array $deserializedResponse */
		$deserializedResponse = $deserializedStatus->getValue();
		if ( !array_key_exists( 'ids', $deserializedResponse ) ) {
			return Status::newFatal( 'Unexpected Speechoid response. No `ids` field.' );
		}
		/** @var array $ids */
		$ids = $deserializedResponse['ids'];
		$numberOfIdentities = count( $ids );
		if ( $numberOfIdentities === 0 ) {
			return Status::newFatal( 'Unexpected Speechoid response. No `ids` values.' );
		} elseif ( $numberOfIdentities > 1 ) {
			return Status::newFatal( 'Unexpected Speechoid response. Multiple `ids` values.' );
		}
		if ( !is_int( $ids[0] ) ) {
			return Status::newFatal( 'Unexpected Speechoid response. Ids[0] is a non integer value.' );
		}
		return Status::newGood( $ids[0] );
	}

	/**
	 * Convert a string of IPA to a string of SAMPA
	 *
	 * @since 0.1.8
	 * @param string $ipa
	 * @param string $language Tell Speechoid to use the symbol set
	 *  for this language.
	 * @return string
	 * @throws SpeechoidConnectorException
	 */
	public function ipaToSampa( string $ipa, string $language ): string {
		// Get the symbol set to convert to
		$lexicon = $this->findLexiconByLanguage( $language );
		$symbolsetRequestUrl = "$this->url/lexserver/lexicon/info/$lexicon";
		$symbolSetResponse = $this->requestFactory->get( $symbolsetRequestUrl );
		$symbolSetStatus = FormatJson::parse(
			$symbolSetResponse,
			FormatJson::FORCE_ASSOC
		);
		if ( !$symbolSetStatus->isOK() ) {
			throw new SpeechoidConnectorException(
				"Failed to parse response from $symbolsetRequestUrl as JSON: " .
				"$symbolSetResponse"
			);
		}
		$symbolSet = $symbolSetStatus->getValue()['symbolSetName'];

		$symbolSetUrl = $this->config->get( 'WikispeechSymbolSetUrl' );
		$mapRequestUrl = "$symbolSetUrl/mapper/map/ipa/$symbolSet/$ipa";
		$mapResponse = $this->requestFactory->get( $mapRequestUrl );
		$mapStatus = FormatJson::parse( $mapResponse, FormatJson::FORCE_ASSOC );
		if ( !$mapStatus->isOK() ) {
			throw new SpeechoidConnectorException(
				"Failed to parse response from $mapRequestUrl as JSON: " .
				"$mapResponse"
			);
		}
		return $mapStatus->getValue()['Result'];
	}
}
