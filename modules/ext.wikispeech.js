( function ( mw, $ ) {
	function Wikispeech() {
		var self, $currentUtterance;

		self = this;
		$currentUtterance = $();

		/**
		 * Add buttons for controlling playback to the top of the page.
		 */

		this.addButtons = function () {
			self.addButton(
				'ext-wikispeech-play-stop-button',
				'ext-wikispeech-play',
				self.playOrStop
			);
			self.addButton(
				'ext-wikispeech-skip-ahead-sentence-button',
				'ext-wikispeech-skip-ahead-sentence',
				self.skipAheadUtterance
			);
			self.addButton(
				'ext-wikispeech-skip-back-sentence-button',
				'ext-wikispeech-skip-back-sentence',
				self.skipBackUtterance
			);
			self.addButton(
				'ext-wikispeech-skip-ahead-word-button',
				'ext-wikispeech-skip-ahead-word',
				self.skipAheadToken
			);
			self.addButton(
				'ext-wikispeech-skip-back-word-button',
				'ext-wikispeech-skip-back-word',
				self.skipBackToken
			);
		};

		/**
		* Add a control button.
		*
		* @param {string} id The id of the button.
		* @param {string} cssClass The name of the CSS class to add to
		*  the button.
		* @param {string} onClickFunction The name of the function to
		*  call when the button is clicked.
		*/

		this.addButton = function ( id, cssClass, onClickFunction ) {
			var $button = $( '<button></button>' )
				.attr( 'id', id )
				.addClass( cssClass );
			$( '#firstHeading' ).append( $button );
			$button.click( onClickFunction );
		};

		/**
		 * Play or stop, depending on whether an utterance is playing.
		 */

		this.playOrStop = function () {
			if ( self.isPlaying() ) {
				self.stop();
			} else {
				self.play();
			}
		};

		/**
		 * Test if there currently is an utterance playing
		 *
		 * @return {boolean} true if there is an utterance playing,
		 *  else false.
		 */

		this.isPlaying = function () {
			return $currentUtterance.length > 0;
		};

		/**
		 * Start playing the first utterance.
		 */

		this.play = function () {
			var $playStopButton = $( '#ext-wikispeech-play-stop-button' );
			self.playUtterance( $( '#utterance-0' ) );
			$playStopButton.removeClass( 'ext-wikispeech-play' );
			$playStopButton.addClass( 'ext-wikispeech-stop' );
		};

		/**
		 * Play the audio for an utterance.
		 *
		 * This also stops any currently playing utterance.
		 *
		 * @param {jQuery} $utterance The utterance to play the audio
		 *  for.
		 */

		this.playUtterance = function ( $utterance ) {
			self.stopUtterance( $currentUtterance );
			$currentUtterance = $utterance;
			$utterance.children( 'audio' ).trigger( 'play' );
		};

		/**
		 * Stop and rewind the audio for an utterance.
		 *
		 * @param {jQuery} $utterance The utterance to stop the audio
		 *  for.
		 */

		this.stopUtterance = function ( $utterance ) {
			$utterance.children( 'audio' ).trigger( 'pause' );
			// Rewind audio for next time it plays.
			$utterance.children( 'audio' ).prop( 'currentTime', 0 );
		};

		/**
		 * Stop playing the utterance currently playing.
		 */

		this.stop = function () {
			var $playStopButton = $( '#ext-wikispeech-play-stop-button' );
			self.stopUtterance( $currentUtterance );
			$currentUtterance = $();
			$playStopButton.removeClass( 'ext-wikispeech-stop' );
			$playStopButton.addClass( 'ext-wikispeech-play' );
		};

		/**
		 * Skip to the next utterance.
		 *
		 * Stop the current utterance and start playing the next one.
		 */

		this.skipAheadUtterance = function () {
			var $nextUtterance = self.getNextUtterance( $currentUtterance );
			if ( $nextUtterance.length ) {
				self.playUtterance( $nextUtterance );
			} else {
				self.stop();
			}
		};

		/**
		 * Get the utterance after the given utterance.
		 *
		 * @param {jQuery} $utterance The original utterance.
		 * @return {jQuery} The utterance after the original
		 *  utterance. Empty object if $utterance is the last one.
		 */

		this.getNextUtterance = function ( $utterance ) {
			return self.getUtteranceByOffset( $utterance, 1 );
		};

		/**
		 * Get the utterance by offset from another utterance.
		 *
		 * @param {jQuery} $utterance The original utterance.
		 * @param {number} offset The difference, in index, to the
		 *  wanted utterance. Can be negative for preceding
		 *  utterances.
		 * @return {jQuery} The utterance after the original
		 *  utterance. Empty object if $utterance isn't a valid
		 *  utterance or if an utterance couldn't be found.
		 */

		this.getUtteranceByOffset = function ( $utterance, offset ) {
			var utteranceIdParts, nextUtteranceIndex, nextUtteranceId;

			if ( !$utterance.length ) {
				return $();
			}
			// Utterance id's follow the pattern "utterance-x", where
			// x is the index.
			utteranceIdParts = $utterance.attr( 'id' ).split( '-' );
			nextUtteranceIndex =
				parseInt( utteranceIdParts[ 1 ], 10 ) + offset;
			utteranceIdParts[ 1 ] = nextUtteranceIndex;
			nextUtteranceId = utteranceIdParts.join( '-' );
			return $( '#' + nextUtteranceId );
		};

		/**
		 * Skip to the previous utterance.
		 *
		 * Stop the current utterance and start playing the previous one. If
		 * the first utterance is playing, restart it.
		 */

		this.skipBackUtterance = function () {
			var previousUtterance, rewindThreshold, $audio, time;

			previousUtterance =
				self.getPreviousUtterance( $currentUtterance );
			if ( previousUtterance.length ) {
				// Only consider skipping back to previous if the
				// current utterance isn't the first one.
				rewindThreshold = mw.config.get(
					'wgWikispeechSkipBackRewindsThreshold' );
				$audio = $currentUtterance.children( 'audio' );
				time = $audio.prop( 'currentTime' );
				if ( time > rewindThreshold ) {
					$audio.prop( 'currentTime', 0.0 );
				} else {
					self.playUtterance( previousUtterance );
				}
			} else if ( self.isPlaying() ) {
				// Alwas skip to start of utterance if the current
				// uterrance is the first.
				self.play();
			}
		};

		/**
		 * Get the utterance before the given utterance.
		 *
		 * @param {jQuery} $utterance The original utterance.
		 * @return {jQuery} The utterance before the original
		 *  utterance. Empty object if $utterance is the first one.
		 */

		this.getPreviousUtterance = function ( $utterance ) {
			return self.getUtteranceByOffset( $utterance, -1 );
		};

		/**
		 * Skip to the next token in the current utterance.
		 */

		this.skipAheadToken = function () {
			var nextToken, $audio, $succeedingTokens;

			if ( self.isPlaying() ) {
				$succeedingTokens =
					self.getCurrentToken().nextAll().filter( function () {
						return !self.isSilent( this );
					} );
				if ( $succeedingTokens.length === 0 ) {
					self.skipAheadUtterance();
				} else {
					nextToken = $succeedingTokens.get( 0 );
					$audio = $currentUtterance.children( 'audio' );
					$audio.prop(
						'currentTime',
						nextToken.getAttribute( 'start-time' )
					);
				}
			}
		};

		/**
		 * Get the token being played.
		 *
		 * @return {jQuery} The token being played.
		 */

		this.getCurrentToken = function () {
			var $tokens, currentTime, $currentToken, $tokensWithDuration,
				duration, startTime, endTime;

			$currentToken = $();
			$tokens = $currentUtterance.find( 'token' );
			currentTime = $currentUtterance.children( 'audio' )
				.prop( 'currentTime' );
			$tokensWithDuration = $tokens.filter( function () {
				duration = this.getAttribute( 'end-time' ) -
					this.getAttribute( 'start-time' );
				return duration > 0.0;
			} );
			if (
				currentTime ===
					parseFloat( $tokensWithDuration.last().attr( 'end-time' ) )
			) {
				// If the current time is equal to the end time of the
				// last token, the last token is the current.
				$currentToken = $tokensWithDuration.last();
			} else {
				$tokensWithDuration.each( function ( i, element ) {
					startTime =
						parseFloat( element.getAttribute( 'start-time' ) );
					endTime =
						parseFloat( element.getAttribute( 'end-time' ) );
					if ( startTime <= currentTime && endTime > currentTime
					) {
						$currentToken = $( element );
						return false;
					}
				} );
			}
			return $currentToken;
		};

		/**
		 * Test if a token is silent.
		 *
		 * Silent is here defined as either having no transcription
		 * (i.e. the empty string) or having no duration (i.e. start
		 * and end time is the same.)
		 *
		 * @param {HTMLElement} tokenElement The token element to test.
		 * @return {boolean} true if the token is silent, else false.
		 */

		this.isSilent = function ( tokenElement ) {
			var startTime, endTime;

			startTime = tokenElement.getAttribute( 'start-time' );
			endTime = tokenElement.getAttribute( 'end-time' );
			return startTime === endTime || tokenElement.textContent === '';
		};

		/**
		 * Skip to the previous token.
		 */

		this.skipBackToken = function () {
			var $previousToken, $utterance, $audio;

			if ( self.isPlaying() ) {
				$previousToken =
					self.getPreviousToken( self.getCurrentToken() );
				$utterance = $previousToken.parent().parent();
				if ( $utterance.get( 0 ) !== $currentUtterance.get( 0 ) ) {
					self.playUtterance( $utterance );
				}
				$audio = $currentUtterance.children( 'audio' );
				$audio.prop(
					'currentTime',
					$previousToken.attr( 'start-time' )
				);
			}
		};

		/**
		 * Get the token before a given token.
		 *
		 * Tokens that are "silent" i.e. have a duration of zero or have no
		 * transcription, are ignored.
		 *
		 * @param {jQuery} $token Original token.
		 * @return {jQuery} The token before $token, empty object if
		 *  $token is the first token.
		 */

		this.getPreviousToken = function ( $token ) {
			var $utterance, $followingToken, $tokens;

			$utterance = $token.parent().parent();
			do {
				$followingToken = $token;
				$token = $token.prev();
				if ( !$token.length ) {
					$utterance = $utterance.prev();
					if ( !$utterance.length ) {
						return $();
					}
					$tokens = $utterance.find( 'token' );
					$token = $( $utterance.find( 'token' )
						.get( $tokens.length - 1 ) );
				}
				// Ignore tokens that either have a duration of zero
				// or no text.
			} while ( self.isSilent( $token.get( 0 ) ) );
			return $token;
		};

		/**
		 * Register listeners for keyboard shortcuts.
		 */

		this.addKeyboardShortcuts = function () {
			var shortcuts = mw.config.get( 'wgWikispeechKeyboardShortcuts' );
			$( document ).keydown( function ( event ) {
				if ( self.eventMatchShortcut( event, shortcuts.playStop ) ) {
					self.playOrStop();
					return false;
				} else if ( self.eventMatchShortcut(
					event,
					shortcuts.skipAheadSentence )
				) {
					self.skipAheadUtterance();
					return false;
				} else if ( self.eventMatchShortcut(
					event,
					shortcuts.skipBackSentence )
				) {
					self.skipBackUtterance();
					return false;
				} else if (
					self.eventMatchShortcut( event, shortcuts.skipAheadWord )
				) {
					self.skipAheadToken();
					return false;
				} else if (
					self.eventMatchShortcut( event, shortcuts.skipBackWord )
				) {
					self.skipBackToken();
					return false;
				}
			} );
		};

		/**
		 * Check if a keydown event matches a shortcut from the
		 * configuration.
		 *
		 * Compare the key and modifier state (of ctrl, alt and shift)
		 * for an event, to those of a shortcut from the
		 * configuration.
		 *
		 * @param {Event} event The event to compare.
		 * @param {Object }shortcut The shortcut object from the
		 *  config to compare to.
		 * @return {boolean} true if key and all the modifiers match
		 *  with the shortcut, else false.
		 */

		this.eventMatchShortcut = function ( event, shortcut ) {
			return event.which === shortcut.key &&
				event.ctrlKey === shortcut.modifiers.indexOf( 'ctrl' ) >= 0 &&
				event.altKey === shortcut.modifiers.indexOf( 'alt' ) >= 0 &&
				event.shiftKey === shortcut.modifiers.indexOf( 'shift' ) >= 0;
		};

		/**
		 * Prepare an utterance for playback.
		 *
		 * Audio for the utterance is requested from the TTS server
		 * and event listeners are added. When an utterance starts
		 * playing, the next one is prepared, and when an utterance is
		 * done, the next utterance is played. This is meant to be a
		 * balance between not having to pause between utterance and
		 * not requesting more than needed.

		 * @param {jQuery} $utterance The utterance to prepare.
		 */

		this.prepareUtterance = function ( $utterance ) {
			var $audio, $nextUtterance;

			if ( !$utterance.prop( 'requested' ) ) {
				// Only load audio for an utterance if we haven't
				// already sent a request for it.
				self.loadAudio( $utterance );
				$nextUtterance = self.getNextUtterance( $utterance );
				$audio = $utterance.children( 'audio' );
				if ( !$nextUtterance.length ) {
					// For last utterance, just stop the playback when
					// done.
					$audio.on( 'ended', function () {
						self.stop();
					} );
				} else {
					$audio.on( {
						play: function () {
							$currentUtterance = $utterance;
							self.prepareUtterance( $nextUtterance );
						},
						ended: function () {
							self.skipAheadUtterance();
						}
					} );
				}
			}
		};

		/**
		 * Request audio for an utterance.
		 *
		 * Adds audio and token elements when the response is
		 * received.
		 *
		 * @param {jQuery} $utterance The utterance to load audio for.
		 */

		this.loadAudio = function ( $utterance ) {
			var $audio, text, audioUrl;

			$audio = $( '<audio></audio>' ).appendTo( $utterance );
			mw.log( 'Loading audio for: ' + $utterance.attr( 'id' ) );
			// Get the combined string of the text nodes only,
			// i.e. not from the cleaned tag.
			text = $utterance.children( 'content' ).contents().filter(
				function () {
					// Filter text nodes. Not using Node.TEXT_NODE to
					// support IE7.
					return this.nodeType === 3;
				}
			).text();
			self.requestTts( text, function ( response ) {
				audioUrl = response.audio;
				mw.log( 'Setting url for ' + $utterance.attr( 'id' ) + ': ' +
					audioUrl );
				$audio.attr( 'src', audioUrl );
				self.addTokenElements( $utterance, response.tokens );
			} );
			$utterance.prop( 'requested', true );
		};

		/**
		 * Send a request to the TTS server.
		 *
		 * The request should specify the following parameters:
		 * - lang: the language used by the synthesizer.
		 * - input_type: "ssml" if you want SSML markup, otherwise
		 *  "text" for plain text.
		 * - input: the text to be synthesized.
		 * For more on the parameters, see:
		 * https://github.com/stts-se/wikispeech_mockup/wiki/api.
		 *
		 * @param {string} text The utterance string to send in the
		 *  request.
		 * @param {Function} callback Function to be called when a
		 *  response is received.
		 */

		this.requestTts = function ( text, callback ) {
			var request, parameters, serverUrl, response;

			request = new XMLHttpRequest();
			request.overrideMimeType( 'text/json' );
			serverUrl = mw.config.get( 'wgWikispeechServerUrl' );
			request.open( 'POST', serverUrl, true );
			request.setRequestHeader(
				'Content-type',
				'application/x-www-form-urlencoded'
			);
			parameters = $.param( {
				// jscs:disable requireCamelCaseOrUpperCaseIdentifiers
				lang: mw.config.get( 'wgPageContentLanguage' ),
				input_type: 'text',
				input: text
				// jscs:enable requireCamelCaseOrUpperCaseIdentifiers
			} );
			request.onload = function () {
				mw.log( 'Response received: ' + request.responseText );
				response = JSON.parse( request.responseText );
				callback( response );
			};
			mw.log( 'Sending request: ' + serverUrl + '?' + parameters );
			request.send( parameters );
		};

		/**
		 * Add token elements to an utterance element.
		 *
		 * Adds a tokens element and populate it with token elements.
		 *
		 * @param {jQuery} $utterance The jQuery object to add tokens to.
		 * @param {Object[]} tokens Tokens from a server response,
		 *  where each token is an object. For these objects, the
		 *  property "orth" is the string used by the TTS to generate
		 *  audio for the token.
		 */

		this.addTokenElements = function ( $utterance, tokens ) {
			var position, $tokensElement, $content, firstTokenIndex,
				removedLength;

			// The character position in the original HTML. Starting
			// at the position of the utterance, since that's the
			// earliest a child token can appear.
			position = parseInt( $utterance.attr( 'position' ), 10 );
			$tokensElement = $( '<tokens></tokens>' ).appendTo( $utterance );
			$content = $utterance.children( 'content' );
			firstTokenIndex = 0;
			mw.log( 'Adding tokens to ' + $utterance.attr( 'id' ) + '.' );
			$content.contents().each( function ( i, element ) {
				if ( element.tagName === 'CLEANED-TAG' ) {
					removedLength = element.getAttribute( 'removed' );
					if ( removedLength !== null ) {
						position += parseInt( removedLength, 10 );
					}
					// Advance position two steps extra for the < and
					// >, that were stripped from the tag at an
					// earlier stage.
					position += 2;
				} else {
					// firstTokenIndex is the index, in tokens, of the
					// first token we haven't created an element for.
					firstTokenIndex = self.addTokensForTextElement(
						tokens,
						element,
						position,
						$tokensElement,
						firstTokenIndex
					);
				}
				position += element.textContent.length;
			} );
		};

		/**
		 * Add a token element for each token that match a substring
		 * of the given text element.
		 *
		 * Goes through textElement, finds substrings matching tokens
		 * and creates token elements for these. The position for the
		 * token elements is the substring position plus the position
		 * of textElement. When a token can no longer be found, the
		 * index of that token is returned to remember what to start
		 * looking for in the next text element.
		 *
		 * @param {Object[]} tokens Tokens from a server response,
		 *  where each token is an object. For these objects, the
		 *  property "orth" is the string used by the TTS to generate
		 *  audio for the token.
		 * @param {HTMLElement} textElement The text element to match
		 *  tokens against.
		 * @param {number} startPosition The position of the original
		 *  text element.
		 * @param {HTMLElement} $tokensElement Element which token
		 *  elements are added to.
		 * @param {number} firstTokenIndex The index of the first
		 *  token in tokens to search for.
		 * @return {number} The index of the first token that wasn't
		 *  found.
		 */

		this.addTokensForTextElement = function (
			tokens,
			textElement,
			startPosition,
			$tokensElement,
			firstTokenIndex
		) {
			var positionInElement, matchingPosition, tokenPositionInHtml,
				orthographicToken, i, token, startTime;

			positionInElement = 0;
			for ( i = firstTokenIndex; i < tokens.length; i++ ) {
				token = tokens[ i ];
				orthographicToken = token.orth;
				// Look for the token in the remaining string.
				matchingPosition =
					textElement.nodeValue.slice( positionInElement )
					.indexOf( orthographicToken );
				if ( matchingPosition === -1 ) {
					// The token wasn't found in this element. Stop
					// looking for more and return the index of the
					// token.
					return i;
				}
				tokenPositionInHtml = startPosition + positionInElement +
					matchingPosition;
				if ( i === 0 ) {
					startTime = 0.0;
				} else {
					startTime = tokens[ i - 1 ].endtime;
				}
				$( '<token></token>' )
					.text( orthographicToken )
					.attr( 'position', tokenPositionInHtml )
					.attr( 'start-time', startTime )
					.attr( 'end-time', tokens[ i ].endtime )
					.appendTo( $tokensElement );
				positionInElement += orthographicToken.length;
			}
		};
	}

	mw.wikispeech = {};
	mw.wikispeech.Wikispeech = Wikispeech;

	if ( $( 'utterances' ).length ) {
		mw.wikispeech.wikispeech = new mw.wikispeech.Wikispeech();
		// Prepare the first utterance for playback.
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.addButtons();
		mw.wikispeech.wikispeech.addKeyboardShortcuts();
	}
}( mediaWiki, jQuery ) );
