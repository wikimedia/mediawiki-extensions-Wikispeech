( function ( mw, $ ) {
	function Wikispeech() {
		var self, $currentUtterance;

		self = this;
		$currentUtterance = $();

		/**
		 * Add a button for starting and stopping recitation to the page.
		 *
		 * When no utterance is playing, clicking starts the first utterance.
		 * When an utterance is being played, clicking stops the playback.
		 * The button changes appearance to reflect its current function.
		 */

		this.addPlayStopButton = function () {
			var $playStopButton = $( '<button></button>' )
				.attr( 'id', 'ext-wikispeech-play-stop-button' )
				.addClass( 'ext-wikispeech-play' );
			$( '#firstHeading' ).append( $playStopButton );
			// For some reason, testing doesn't work with
			// .click( self.playOrStop ).
			$playStopButton.click( function () { self.playOrStop(); } );
		};

		/**
		 * Play or stop, depending on whether an utterance is playing.
		 */

		this.playOrStop = function () {
			if ( !$currentUtterance.length ) {
				self.play();
			} else {
				self.stop();
			}
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
		 * @param $utterance The utterance to play the audio for.
		 */

		this.playUtterance = function ( $utterance ) {
			self.stopUtterance( $currentUtterance );
			$currentUtterance = $utterance;
			$utterance.children( 'audio' ).trigger( 'play' );
		};

		/**
		 * Stop and rewind the audio for an utterance.
		 *
		 * @param $utterance The utterance to stop the audio for.
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
		 * Add a button for skipping to the next sentence.
		 *
		 * This actually skips to the next utterance; it's assumed that the
		 * utterances are sentences, where titles count as sentences.
		 */

		this.addSkipAheadSentenceButton = function () {
			var $skipAheadSentenceButton = $( '<button></button>' )
				.attr( 'id', 'ext-wikispeech-skip-ahead-sentence-button' )
				.addClass( 'ext-wikispeech-skip-ahead-sentence' );
			$( '#firstHeading' ).append( $skipAheadSentenceButton );
			$skipAheadSentenceButton.click( function () {
				self.skipAheadUtterance();
			} );
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
		 * Register listeners for keyboard shortcuts.
		 */

		this.addKeyboardShortcuts = function () {
			var shortcuts = mw.config.get( 'wgWikispeechKeyboardShortcuts' );
			$( document ).keydown( function ( event ) {
				if ( self.eventMatchShortcut( event, shortcuts.playStop ) ) {
					self.playOrStop();
				} else if ( self.eventMatchShortcut(
					event,
					shortcuts.skipAheadUtterance )
				) {
					self.skipAheadUtterance();
				}
			} );
		};

		/**
		 * Check if a keydown event matches a shortcut from the configuration.
		 *
		 * Compare the key and modifier state (of ctrl, alt and shift) for an
		 * event, to those of a shortcut from the configuration.
		 *
		 * @param event The event to compare.
		 * @param shortcut The shortcut object from the config to compare to.
		 * @return true if key and all the modifiers match with the shortcut,
		 *  else false.
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
		 * Audio for the utterance is requested from the TTS server and event
		 * listeners are added. When an utterance starts playing, the next one
		 * is prepared, and when an utterance is done, the next utterance is
		 * played. This is meant to be a balance between not having to pause
		 * between utterance and not requesting more than needed.

		 * @param $utterance The utterance to prepare.
		 */

		this.prepareUtterance = function ( $utterance ) {
			var $audio, $nextUtterance, $nextUtteranceAudio;

			if ( !$utterance.prop( 'requested' ) ) {
				// Only load audio for an utterance if we haven't already
				// sent a request for it.
				self.loadAudio( $utterance );
				$nextUtterance = self.getNextUtterance( $utterance );
				$audio = $utterance.children( 'audio' );
				if ( !$nextUtterance.length ) {
					// For last utterance, just stop the playback when done.
					$audio.on( 'ended', function () {
						self.stop();
					} );
				} else {
					$nextUtteranceAudio = $nextUtterance.children( 'audio' );
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
		 * Get the utterance after the given utterance.
		 *
		 * @param $utterance The original utterance.
		 * @return The utterance after the original utterance. Empty object if
		 *  $utterance isn't a valid utterance.
		 */

		this.getNextUtterance = function ( $utterance ) {
			var utteranceIdParts, nextUtteranceIndex, nextUtteranceId;

			if ( !$utterance.length ) {
				return $();
			}
			// Utterance id's follow the pattern "utterance-x", where x is
			// the index.
			utteranceIdParts = $utterance.attr( 'id' ).split( '-' );
			nextUtteranceIndex = parseInt( utteranceIdParts[ 1 ], 10 ) + 1;
			utteranceIdParts[ 1 ] = nextUtteranceIndex;
			nextUtteranceId = utteranceIdParts.join( '-' );
			return $( '#' + nextUtteranceId );
		};

		/**
		 * Request audio for an utterance.
		 *
		 * Adds audio and token elements when the response is received.
		 *
		 * @param $utterance The utterance to load audio for.
		 */

		this.loadAudio = function ( $utterance ) {
			var $audio, text, audioUrl;

			$audio = $( '<audio></audio>' ).appendTo( $utterance );
			mw.log( 'Loading audio for: ' + $utterance.attr( 'id' ) );
			// Get the combined string of the text nodes only, i.e. not from
			// the cleaned tag.
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
		 * - input_type: "ssml" if you want SSML markup, otherwise "text" for
		 * plain text.
		 * - input: the text to be synthesized.
		 * For more on the parameters, see:
		 * https://github.com/stts-se/wikispeech_mockup/wiki/api.
		 *
		 * @param {string} text The utterance string to send in the request.
		 * @param {Function} callback Function to be called when a response
		 *  is received.
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
				lang: 'en',
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
		 * @param $utterance The jQuery object to add tokens to.
		 * @param tokens Array of tokens from a server response, where each
		 *  token is an object. For these objects, the property "orth" is the
		 *  string used by the TTS to generate audio for the token.
		 */

		this.addTokenElements = function ( $utterance, tokens ) {
			var position, $tokensElement, $content, firstTokenIndex,
				removedLength;

			// The character position in the original HTML. Starting at the
			// position of the utterance, since that's the earliest a child
			// token can appear.
			position = parseInt( $utterance.attr( 'position' ), 10 );
			$tokensElement = $( '<tokens></tokens>' ).appendTo( $utterance );
			$content = $utterance.children( 'content' );
			firstTokenIndex = 0;
			mw.log( 'Adding tokens to ' + $utterance.attr( 'id' ) + ':' );
			$content.contents().each( function ( i, element ) {
				if ( element.tagName === 'CLEANED-TAG' ) {
					removedLength = element.getAttribute( 'removed' );
					if ( removedLength !== null ) {
						position += parseInt( removedLength, 10 );
					}
					// Advance position two steps extra for the < and >,
					// that were stripped from the tag at an earlier stage.
					position += 2;
				} else {
					// firstTokenIndex is the index, in tokens, of the first
					// token we haven't created an element for.
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
		 * Add a token element for each token that match a substring of the
		 * given text element.
		 *
		 * Goes through textElement, finds substrings matching tokens and
		 * creates token elements for these. The position for the token
		 * elements is the substring position plus the position of textElement.
		 * When a token can no longer be found, the index of that token is
		 * returned to remember what to start looking for in the next text
		 * element.
		 *
		 * @param tokens Array of tokens from a server response, where each
		 *  token is an object. For these objects, the property "orth" is the
		 *  string used by the TTS to generate audio for the token.
		 * @param textElement The text element to match tokens against.
		 * @param {int} startPosition The position of the original text
		 *  element.
		 * @param $tokensElement Element which token elements are added to.
		 * @param {int} firstTokenIndex The index of the first token in tokens
		 *  to search for.
		 * @return {int} The index of the first token that wasn't found.
		 */

		this.addTokensForTextElement = function (
			tokens,
			textElement,
			startPosition,
			$tokensElement,
			firstTokenIndex
		) {
			var positionInElement, matchingPosition, tokenPositionInHtml,
				orthographicToken, i, token;

			positionInElement = 0;
			for ( i = firstTokenIndex; i < tokens.length; i++ ) {
				token = tokens[ i ];
				orthographicToken = token.orth;
				// Look for the token in the remaining string.
				matchingPosition =
					textElement.nodeValue.slice( positionInElement )
					.indexOf( orthographicToken );
				if ( matchingPosition === -1 ) {
					// The token wasn't found in this element. Stop looking for
					// more and return the index of the token.
					return i;
				}
				tokenPositionInHtml = startPosition + positionInElement +
					matchingPosition;
				mw.log( '  "' + orthographicToken + '", position: ' +
					tokenPositionInHtml );
				$( '<token></token>' )
					.text( orthographicToken )
					.attr( 'position', tokenPositionInHtml )
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
		mw.wikispeech.wikispeech.addPlayStopButton();
		mw.wikispeech.wikispeech.addSkipAheadSentenceButton();
		mw.wikispeech.wikispeech.addKeyboardShortcuts();
	}
}( mediaWiki, jQuery ) );
