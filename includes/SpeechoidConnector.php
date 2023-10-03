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
use MediaWiki\Status\Status;

/**
 * Provide Speechoid access.
 *
 * @since 0.1.5
 */
class SpeechoidConnector {

	/** @var Config */
	private $config;

	/** @var string Speechoid URL, without trailing slash. For non queued (non-TTS) operations. */
	private $url;

	/** @var string Speechoid queue URL, without trailing slash. For queued (TTS) operations. */
	private $haproxyQueueUrl;

	/** @var string Speechoid queue status URL, without trailing slash. */
	private $haproxyStatsUrl;

	/** @var string Speechoid symbol set URL, without trailing slash. */
	private $symbolSetUrl;

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
		$this->symbolSetUrl = rtrim( $config->get( 'WikispeechSymbolSetUrl' ), '/' );
		if ( !$this->symbolSetUrl ) {
			$parsedUrl = parse_url( $this->url );
			$parsedUrl['port'] = 8771;
			$this->symbolSetUrl = $this->unparseUrl( $parsedUrl );
		}
		$this->haproxyQueueUrl = rtrim( $config->get( 'WikispeechSpeechoidHaproxyQueueUrl' ), '/' );
		if ( !$this->haproxyQueueUrl ) {
			$parsedUrl = parse_url( $this->url );
			$parsedUrl['port'] = 10001;
			$this->haproxyQueueUrl = $this->unparseUrl( $parsedUrl );
		}
		$this->haproxyStatsUrl = rtrim( $config->get( 'WikispeechSpeechoidHaproxyStatsUrl' ), '/' );
		if ( !$this->haproxyStatsUrl ) {
			$parsedUrl = parse_url( $this->url );
			$parsedUrl['port'] = 10002;
			$this->haproxyStatsUrl = $this->unparseUrl( $parsedUrl );
		}
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
	 * @param array $parameters Should contain either 'text', 'ipa' or 'ssml'.
	 *  Determines input string and type.
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
		} elseif ( isset( $parameters['ssml'] ) ) {
			$postData['input'] = $parameters['ssml'];
			$postData['input_type'] = 'ssml';
		} else {
			throw new InvalidArgumentException(
				'$parameters must contain one of "text", "ipa" or "ssml".'
			);
		}
		$options = [ 'postData' => $postData ];
		$responseString = $this->requestFactory->post( $this->haproxyQueueUrl, $options );
		if ( !$responseString ) {
			throw new SpeechoidConnectorException(
				'Unable to communicate with Speechoid. ' .
				$this->haproxyQueueUrl . var_export( $options, true )
			);
		}
		$status = FormatJson::parse(
			$responseString,
			FormatJson::FORCE_ASSOC
		);
		if ( !$status->isOK() ) {
			throw new SpeechoidConnectorException( $responseString );
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
	public function listDefaultVoicePerLanguage(): array {
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
		$url = wfAppendQuery(
			$this->url . '/lexserver/lexicon/lookup',
			[
				'lexicons' => $lexicon,
				'words' => implode( ',', $words )
			]
		);
		$responseString = $this->requestFactory->get( $url );
		if ( !$responseString ) {
			throw new SpeechoidConnectorException( "Unable to communicate with Speechoid.  '$url'" );
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
	 * Convert a string to IPA from the symbolset used for the given language
	 *
	 * @since 0.1.10
	 * @param string $string
	 * @param string $language Tell Speechoid to use the symbol set
	 *  for this language.
	 * @return Status
	 */
	public function toIpa( string $string, string $language ): Status {
		return $this->map( $string, $language, true );
	}

	/**
	 * Convert a string to or from IPA
	 *
	 * @since 0.1.8
	 * @param string $string
	 * @param string $language Tell Speechoid to use the symbol set
	 *  for this language.
	 * @param bool $toIpa Converts to IPA if true, otherwise from IPA
	 * @return Status
	 */
	private function map( string $string, string $language, bool $toIpa ): Status {
		// Get the symbol set to convert to
		$lexicon = $this->findLexiconByLanguage( $language );
		$symbolsetRequestUrl = "$this->url/lexserver/lexicon/info/$lexicon";
		$symbolSetResponse = $this->requestFactory->get( $symbolsetRequestUrl );
		$symbolSetStatus = FormatJson::parse(
			$symbolSetResponse,
			FormatJson::FORCE_ASSOC
		);
		if ( !$symbolSetStatus->isOK() ) {
			return Status::newFatal(
				"Failed to parse response from $symbolsetRequestUrl as JSON: " .
				$symbolSetResponse
			);
		}
		$symbolSet = $symbolSetStatus->getValue()['symbolSetName'];

		if ( $toIpa ) {
			$from = $symbolSet;
			$to = 'ipa';
		} else {
			$from = 'ipa';
			$to = $symbolSet;
		}
		$mapRequestUrl = "$this->symbolSetUrl/mapper/map/$from/$to/" .
			rawurlencode( $string );
		$mapResponse = $this->requestFactory->get( $mapRequestUrl );
		$mapStatus = FormatJson::parse( $mapResponse, FormatJson::FORCE_ASSOC );
		if ( !$mapStatus->isOK() ) {
			return Status::newFatal(
				"Failed to parse response from $mapRequestUrl as JSON: " .
				$mapResponse
			);
		}
		return Status::newGood( $mapStatus->getValue()['Result'] );
	}

	/**
	 * Convert a string from IPA to the symbolset used for the given language
	 *
	 * @since 0.1.10
	 * @param string $string
	 * @param string $language Tell Speechoid to use the symbol set
	 *  for this language.
	 * @return Status
	 */
	public function fromIpa( string $string, string $language ): Status {
		return $this->map( $string, $language, false );
	}

	/**
	 * Queue is overloaded if there are already the maximum number of current
	 * connections processed by the backend at the same time as the queue
	 * contains more than X connections waiting for their turn,
	 * where X =
	 * WikispeechSpeechoidHaproxyOverloadFactor multiplied with
	 * the maximum number of current connections to the backend.
	 *
	 * @see HaproxyStatusParser::isQueueOverloaded()
	 * @since 0.1.10
	 * @return bool Whether or not connection queue is overloaded
	 */
	public function isQueueOverloaded(): bool {
		$statsResponse = $this->requestFactory->get(
			$this->haproxyStatsUrl . '/stats;csv;norefresh'
		);
		$parser = new HaproxyStatusParser( $statsResponse );
		return $parser->isQueueOverloaded(
			$this->config->get( 'WikispeechSpeechoidHaproxyFrontendPxName' ),
			$this->config->get( 'WikispeechSpeechoidHaproxyFrontendSvName' ),
			$this->config->get( 'WikispeechSpeechoidHaproxyBackendPxName' ),
			$this->config->get( 'WikispeechSpeechoidHaproxyBackendSvName' ),
			floatval( $this->config->get( 'WikispeechSpeechoidHaproxyOverloadFactor' ) )
		);
	}

	/**
	 * Counts number of requests that currently could be sent to the queue
	 * and immediately would be passed down to backend.
	 *
	 * If this value is greater than 0, then the next request sent via the queue
	 * will be immediately processed by the backend.
	 *
	 * If this value is less than 1, then the next connection will be queued,
	 * given that the currently processing requests will not have had time to finish by then.
	 *
	 * If this value is less than 1, then the value is the inverse size of the known queue.
	 * Note that the OS on the HAProxy server might be buffering connections in the TCP-stack
	 * and that HAProxy will not be aware of such connections. A negative number might therefor
	 * not represent a perfect count of current connection lined up in the queue.
	 *
	 * The idea with this function is to see if there are available resources that could
	 * be used for pre-synthesis of utterances during otherwise idle time.
	 *
	 * @see HaproxyStatusParser::getAvailableNonQueuedConnectionSlots()
	 * @since 0.1.10
	 * @return int Positive number if available slots, else inverted size of queue.
	 */
	public function getAvailableNonQueuedConnectionSlots(): int {
		$statsResponse = $this->requestFactory->get(
			$this->haproxyStatsUrl . '/stats;csv;norefresh'
		);
		$parser = new HaproxyStatusParser( $statsResponse );
		return $parser->getAvailableNonQueuedConnectionSlots(
			$this->config->get( 'WikispeechSpeechoidHaproxyFrontendPxName' ),
			$this->config->get( 'WikispeechSpeechoidHaproxyFrontendSvName' ),
			$this->config->get( 'WikispeechSpeechoidHaproxyBackendPxName' ),
			$this->config->get( 'WikispeechSpeechoidHaproxyBackendSvName' )
		);
	}

	/**
	 * Converts the output from {@link parse_url} to an URL.
	 *
	 * @since 0.1.10
	 * @param array $parsedUrl
	 * @return string
	 */
	private function unparseUrl( array $parsedUrl ): string {
		$scheme = isset( $parsedUrl['scheme'] ) ? $parsedUrl['scheme'] . '://' : '';
		$host = $parsedUrl['host'] ?? '';
		$port = isset( $parsedUrl['port'] ) ? ':' . $parsedUrl['port'] : '';
		$user = $parsedUrl['user'] ?? '';
		$pass = isset( $parsedUrl['pass'] ) ? ':' . $parsedUrl['pass'] : '';
		$pass = ( $user || $pass ) ? "$pass@" : '';
		$path = $parsedUrl['path'] ?? '';
		$query = isset( $parsedUrl['query'] ) ? '?' . $parsedUrl['query'] : '';
		$fragment = isset( $parsedUrl['fragment'] ) ? '#' . $parsedUrl['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}

}
