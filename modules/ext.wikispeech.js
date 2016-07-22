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
			var $playStopButton;

			$playStopButton = $( '<button></button>' )
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
			var $playStopButton;

			self.playUtterance( $( '#utterance-0' ) );
			$playStopButton = $( '#ext-wikispeech-play-stop-button' );
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
			var $playStopButton;

			self.stopUtterance( $currentUtterance );
			$currentUtterance = $();
			$playStopButton = $( '#ext-wikispeech-play-stop-button' );
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
			var $skipAheadSentenceButton;

			$skipAheadSentenceButton = $( '<button></button>' )
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
			var $nextUtterance;

			$nextUtterance = self.getNextUtterance( $currentUtterance );
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
			var shortcuts;

			$( document ).keydown( function ( event ) {
				shortcuts = mw.config.get( 'wgWikispeechKeyboardShortcuts' );
				if ( self.eventMatchShortcut( event, shortcuts.playStop ) ) {
					self.playOrStop();
				}
				if ( self.eventMatchShortcut(
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
		 *	$utterance isn't a valid utterance.
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
		 * When the response is received, set the audio URL as the source for
		 * the utterance's audio element.
		 *
		 * @param $utterance The utterance to load audio for.
		 */

		this.loadAudio = function ( $utterance ) {
			var $audio, text, audioUrl;

			$audio = $utterance.children( 'audio' );
			mw.log( 'Loading audio for: ' + $utterance.attr( 'id' ) );
			text = $utterance.children( 'text' ).text();
			self.requestTts( text, function ( response ) {
				audioUrl = response.audio;
				mw.log( 'Setting url for ' + $utterance.attr( 'id' ) + ': ' +
						audioUrl );
				$audio.attr( 'src', audioUrl );
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
		 *	is received.
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
				response = JSON.parse( request.responseText );
				callback( response );
			};
			mw.log( 'Sending request: ' + serverUrl + '?' + parameters );
			request.send( parameters );
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
