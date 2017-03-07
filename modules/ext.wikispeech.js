( function ( mw, $ ) {
	function Wikispeech() {
		var self = this;
		self.$currentUtterance = $();

		/**
		 * Add a panel with controls for for Wikispeech.
		 *
		 * The panel contains buttons for controlling playback and
		 * links to related pages.
		 */

		this.addControlPanel = function () {
			$( '<div></div>' )
				.attr( 'id', 'ext-wikispeech-control-panel' )
				.addClass( 'ext-wikispeech-control-panel' )
				.appendTo( '#content' );
			self.moveControlPanelWhenDebugging();
			self.addButton(
				'ext-wikispeech-skip-back-sentence',
				self.skipBackUtterance
			);
			self.addButton(
				'ext-wikispeech-skip-back-word',
				self.skipBackToken
			);
			self.addButton(
				'ext-wikispeech-play',
				self.playOrStop,
				'ext-wikispeech-play-stop-button'
			);
			self.addButton(
				'ext-wikispeech-skip-ahead-word',
				self.skipAheadToken
			);
			self.addButton(
				'ext-wikispeech-skip-ahead-sentence',
				self.skipAheadUtterance
			);
			self.addLinkButton(
				'ext-wikispeech-help',
				'wgWikispeechHelpPage'
			);
			self.addLinkButton(
				'ext-wikispeech-feedback',
				'wgWikispeechFeedbackPage'
			);
			if (
				$( '.ext-wikispeech-help, ext-wikispeech-feedback' ).length
			) {
				// Add divider if there are any non-control buttons.
				$( '<span></span>' )
					.addClass( 'ext-wikispeech-divider' )
					.insertBefore(
						$( '.ext-wikispeech-help, ext-wikispeech-feedback' )
							.first()
					);
			}
		};

		/**
		* Make sure the control panel isn't covered by the debug panel.
		*
		* The debug panel is also added to the bottom of the page and
		* covers the control panel. This function moves the control
		* panel above the debug panel, when it is added or has
		* children added (which changes the size).
		*/

		this.moveControlPanelWhenDebugging = function () {
			var debugPanelChangedObserver, debugPanelAddedObserver;

			debugPanelChangedObserver =
				new MutationObserver( function ( ) {
					// Move the control panel above the debug panel.
					// jquery-foot-hovzer is the id of the debug
					// panel.
					$( '#ext-wikispeech-control-panel' ).css(
						'bottom',
						$( '#jquery-foot-hovzer' ).height()
					);
				} );
			debugPanelAddedObserver =
				new MutationObserver( function ( mutations ) {
					mutations.forEach( function ( mutation ) {
						mutation.addedNodes.forEach( function ( addedNode ) {
							if ( addedNode.getAttribute( 'id' ) ===
									'jquery-foot-hovzer' ) {
								// Start observing changes to nodes in
								// the debug panel. This needs to wait
								// until the actual panel is added.
								debugPanelChangedObserver.observe(
									$( '#jquery-foot-hovzer' ).get( 0 ),
									{ childList: true }
								);
								// We don't need to listen to this
								// anymore, since we are just
								// interested in when things are added
								// to the debug panel.
								debugPanelAddedObserver.disconnect();
							}
						} );
					} );
				} );
			debugPanelAddedObserver.observe(
				$( 'body' ).get( 0 ),
				{ childList: true }
			);
		};

		/**
		* Add a control button.
		*
		* @param {string} cssClass The name of the CSS class to add to
		*  the button.
		* @param {string} onClickFunction The name of the function to
		*  call when the button is clicked.
		* @param {string} id The id of the button.
		*/

		this.addButton = function ( cssClass, onClickFunction, id ) {
			var $button = $( '<button></button>' )
				.addClass( cssClass )
				.attr( 'id', id )
				.appendTo( '#ext-wikispeech-control-panel' );
			$button.click( onClickFunction );
			return $button;
		};

		/**
		* Add a button that takes the user to another page.
		*
		* The button gets the link destination from a supplied
		* config variable. If the variable isn't specified, the button
		* isn't added.
		*
		* @param {string} cssClass The name of the CSS class to add to
		*  the button.
		* @param {string} configVariable The config variable to get
		*  link destination from.
		*/

		this.addLinkButton = function ( cssClass, configVariable ) {
			var page, pagePath;

			page = mw.config.get( configVariable );
			if ( page ) {
				pagePath = mw.config.get( 'wgArticlePath' )
					.replace( '$1', page );
				$( '<a></a>' )
					.attr( 'href', pagePath )
					.append(
						$( '<button></button>' )
							.addClass( cssClass )
					)
					.appendTo( '#ext-wikispeech-control-panel' );
			}
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
			return self.$currentUtterance.length > 0;
		};

		/**
		 * Stop playing the utterance currently playing.
		 */

		this.stop = function () {
			var $playStopButton = $( '#ext-wikispeech-play-stop-button' );
			self.stopUtterance( self.$currentUtterance );
			self.$currentUtterance = $();
			$playStopButton.removeClass( 'ext-wikispeech-stop' );
			$playStopButton.addClass( 'ext-wikispeech-play' );
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
			self.stopUtterance( self.$currentUtterance );
			self.$currentUtterance = $utterance;
			$utterance.children( 'audio' ).get( 0 ).play();
			mw.wikispeech.highlighter.highlightUtterance( $utterance );
		};

		/**
		 * Stop and rewind the audio for an utterance.
		 *
		 * @param {jQuery} $utterance The utterance to stop the audio
		 *  for.
		 */

		this.stopUtterance = function ( $utterance ) {
			var $audio = $utterance.children( 'audio' );
			if ( $audio.length ) {
				$audio.get( 0 ).pause();
			}
			// Rewind audio for next time it plays.
			$audio.prop( 'currentTime', 0 );
			// Remove sentence highlighting.
			mw.wikispeech.highlighter.removeWrappers(
				'.ext-wikispeech-highlight-sentence'
			);
			// Remove word highlighting.
			mw.wikispeech.highlighter.removeWrappers(
				'.ext-wikispeech-highlight-word'
			);
			clearTimeout( mw.wikispeech.highlighter.highlightTokenTimer );
		};

		/**
		 * Skip to the next utterance.
		 *
		 * Stop the current utterance and start playing the next one.
		 */

		this.skipAheadUtterance = function () {
			var $nextUtterance =
				self.getNextUtterance( self.$currentUtterance );
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
				self.getPreviousUtterance( self.$currentUtterance );
			if ( previousUtterance.length ) {
				// Only consider skipping back to previous if the
				// current utterance isn't the first one.
				rewindThreshold = mw.config.get(
					'wgWikispeechSkipBackRewindsThreshold' );
				$audio = self.$currentUtterance.children( 'audio' );
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
			var nextToken, $audio;

			if ( self.isPlaying() ) {
				nextToken = self.getNextToken( self.getCurrentToken() );
				if ( nextToken === null ) {
					self.skipAheadUtterance();
				} else {
					$audio = self.$currentUtterance.children( 'audio' );
					$audio.prop( 'currentTime', nextToken.startTime );
					mw.wikispeech.highlighter.removeWrappers(
						'.ext-wikispeech-highlight-word'
					);
					mw.wikispeech.highlighter.highlightToken( nextToken );
				}
			}
		};

		/**
		 * Get the token following a given token.
		 *
		 * @param {jQuery} $originalToken Find the next token element
		 *  after this one.
		 * @return {HTMLElement} The first token following
		 *  $originalToken that has time greater than zero and a
		 *  transcription. null if no such token is found. Will not
		 *  look beyond the $originalToken's utterance.
		 */

		this.getNextToken = function ( $originalToken ) {
			var $succeedingTokens, nextToken;

			$succeedingTokens =
				$originalToken.nextAll().filter( function () {
					return !self.isSilent( this );
				} );
			if ( $succeedingTokens.length ) {
				nextToken = $succeedingTokens.get( 0 );
				return nextToken;
			} else {
				return null;
			}
		};

		/**
		 * Get the token being played.
		 *
		 * @return {jQuery} The token being played.
		 */

		this.getCurrentToken = function () {
			var $tokens, currentTime, $currentToken, $tokensWithDuration,
				duration;

			$currentToken = $();
			$tokens = self.$currentUtterance.find( 'token' );
			currentTime = self.$currentUtterance.children( 'audio' )
				.prop( 'currentTime' );
			$tokensWithDuration = $tokens.filter( function () {
				duration = this.endTime - this.startTime;
				return duration > 0.0;
			} );
			if (
				currentTime ===
					parseFloat( $tokensWithDuration.last().prop( 'endTime' ) )
			) {
				// If the current time is equal to the end time of the
				// last token, the last token is the current.
				$currentToken = $tokensWithDuration.last();
			} else {
				$tokensWithDuration.each( function () {
					if (
						this.startTime <= currentTime &&
							this.endTime > currentTime
					) {
						$currentToken = $( this );
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

			startTime = tokenElement.startTime;
			endTime = tokenElement.endTime;
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
				$utterance =
					$previousToken.parentsUntil( 'utterance' ).parent();
				if ( $utterance.get( 0 ) !== self.$currentUtterance.get( 0 ) ) {
					self.playUtterance( $utterance );
				}
				$audio = self.$currentUtterance.children( 'audio' );
				$audio.prop(
					'currentTime',
					$previousToken.prop( 'startTime' )
				);
				mw.wikispeech.highlighter.removeWrappers(
					'.ext-wikispeech-highlight-word'
				);
				mw.wikispeech.highlighter.highlightToken( $previousToken.get( 0 ) );
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

			$utterance = $token.parentsUntil( 'utterance' ).parent();
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
			var shortcuts, name, shortcut;

			shortcuts = mw.config.get( 'wgWikispeechKeyboardShortcuts' );
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
			// Prevent keyup events from triggering if there is
			// keydown event for the same key combination. This caused
			// buttons in focus to trigger if a shortcut had space as
			// key.
			$( document ).keyup( function ( event ) {
				for ( name in shortcuts ) {
					shortcut = shortcuts[ name ];
					if ( self.eventMatchShortcut( event, shortcut ) ) {
						event.preventDefault();
					}
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

			if ( !$utterance.children( 'audio' ).length ) {
				// Make sure there is an audio element for this
				// utterance.
				$utterance.append( '<audio></audio>' );
			}
			$audio = $utterance.children( 'audio' );
			if (
				!$audio.attr( 'src' ) &&
					!$utterance.prop( 'waitingForResponse' )
			) {
				// Only load audio for an utterance if it isn't
				// already loaded or waiting for response from server.
				self.loadAudio( $utterance );
				$nextUtterance = self.getNextUtterance( $utterance );
				$audio.on( {
					playing: function () {
						var firstToken;

						// Highlight token only when the audio starts
						// playing, since we need the token info from
						// the response to know what to highlight.
						if ( $audio.prop( 'currentTime' ) === 0 ) {
							firstToken =
								$utterance.find( 'token' ).get( 0 );
							mw.wikispeech.highlighter.highlightToken(
								firstToken
							);
							mw.wikispeech.highlighter.setHighlightTokenTimer(
								firstToken
							);
						}
					}
				} );
				if ( !$nextUtterance.length ) {
					// For last utterance, just stop the playback when
					// done.
					$audio.on( 'ended', self.stop );
				} else {
					$audio.on( {
						play: function () {
							self.prepareUtterance( $nextUtterance );
						},
						ended: self.skipAheadUtterance
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

			mw.log( 'Loading audio for: ' + $utterance.attr( 'id' ) );
			// Get the combined string of the text nodes only,
			// i.e. not from the cleaned tag.
			text = $utterance.children( 'content' ).contents().filter(
				function () {
					// Filter text nodes. Not using Node.ELEMENT_NODE
					// to support IE7.
					if ( this.nodeType === 1 && this.tagName === 'TEXT' ) {
						return true;
					} else {
						return false;
					}
				}
			).text();
			self.requestTts( text, $utterance, function ( response ) {
				audioUrl = response.audio;
				mw.log(
					'Setting url for ' + $utterance.attr( 'id' ) + ': ' +
						audioUrl
				);
				$audio = $utterance.children( 'audio' );
				$audio.attr( 'src', audioUrl );
				self.addTokenElements( $utterance, response.tokens );
			} );
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
		 * @param {jQuery} $utterance The utterance for this request.
		 * @param {Function} callback Function to be called when a
		 *  response is received.
		 */

		this.requestTts = function ( text, $utterance, callback ) {
			var serverUrl = mw.config.get( 'wgWikispeechServerUrl' );
			$.ajax( {
				url: serverUrl,
				method: 'POST',
				data: {
					// jscs:disable requireCamelCaseOrUpperCaseIdentifiers
					lang: mw.config.get( 'wgPageContentLanguage' ),
					input_type: 'text',
					input: text
					// jscs:enable requireCamelCaseOrUpperCaseIdentifiers
				},
				dataType: 'json',
				beforeSend: function ( jqXHR, settings ) {
					mw.log( 'Sending request: ' + settings.url + '?' + settings.data );
					$utterance.prop( 'waitingForResponse', true );
				}
			} )
				.done( function ( data ) {
					mw.log( 'Response received:', data );
					callback( data );
				} )
				.fail( function ( jqXHR, textStatus ) {
					mw.log.warn(
						'Request failed, error type "' + textStatus + '":',
						this.url
					);
				} )
				.always( function () {
					$utterance.prop( 'waitingForResponse', false );
				} );
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
			var $tokensElement, i, token, startTime, utteranceOffset,
				searchOffset, lastEndOffset, $tokenElement;

			$tokensElement = $( '<tokens></tokens>' ).appendTo( $utterance );
			utteranceOffset =
				parseInt( $utterance.attr( 'start-offset' ), 10 );
			searchOffset = 0;
			lastEndOffset = 0;
			for ( i = 0; i < tokens.length; i++ ) {
				token = tokens[ i ];
				if ( i === 0 ) {
					// The first token in an utterance always start on
					// time zero.
					startTime = 0.0;
				} else {
					// Since the response only contains end times for
					// token, the start time for a token is set to the
					// end time of the previous one.
					startTime = tokens[ i - 1 ].endtime;
				}
				$tokenElement = $( '<token></token>' )
					.text( token.orth )
					.prop( {
						startTime: startTime,
						endTime: token.endtime
					} )
					.appendTo( $tokensElement );
				if ( i > 0 ) {
					// Start looking for the next token after the
					// previous one, except for the first token, where
					// we want to start on zero.
					searchOffset += 1;
				}
				searchOffset = self.addOffsetsAndTextElements(
					$tokenElement,
					searchOffset
				);
			}
		};

		/**
		 * Add properties for offsets and text elements to an token element.
		 *
		 * The offsets are for the start and end of the token in the
		 * text node which they appear. These text nodes are not
		 * necessary the same.
		 *
		 * The text elements are the element in which the token start,
		 * the element in which it ends and any element in between.
		 *
		 * @param {jQuery} $tokenElement The token element to add
		 *  properties to.
		 * @param {number} searchOffset The offset to start searching
		 *  from, in the concatenated string.
		 * @return {number} The end offset in the concatenated string.
		 */

		this.addOffsetsAndTextElements = function (
			$tokenElement,
			searchOffset
		) {
			var $utterance, utteranceOffset,
				$textElementsForUtterance, textElements,
				startOffsetInUtteranceString,
				endOffsetInUtteranceString, endOffsetForTextElement,
				firstElementIndex, elementsBeforeStart,
				lastElementIndex, elementsBeforeEnd;

			$utterance = $tokenElement.parentsUntil( 'utterance' ).parent();
			utteranceOffset =
				parseInt( $utterance.attr( 'start-offset' ), 10 );
			$textElementsForUtterance = $utterance.find( 'content text' );
			textElements = [];
			startOffsetInUtteranceString =
				self.getStartOffsetInUtteranceString(
					$tokenElement.text(),
					$textElementsForUtterance,
					textElements,
					searchOffset
				);
			endOffsetInUtteranceString =
				startOffsetInUtteranceString +
				$tokenElement.text().length - 1;
			// textElements now contains all the text elements in the
			// utterance, from the first one to the last, that
			// contains at least part of the token. To get only the
			// ones that contain part of the token, the elements that
			// appear before the token are removed.
			endOffsetForTextElement = 0;
			textElements =
				$( textElements ).filter( function () {
					endOffsetForTextElement += this.textContent.length;
					return endOffsetForTextElement >
						startOffsetInUtteranceString;
				} )
				.get();
			$tokenElement.prop( 'textElements', textElements, $tokenElement );

			// Calculate start and end offset for the token, in the
			// text nodes it appears in, and add them to the
			// token.
			firstElementIndex =
				$textElementsForUtterance.index( textElements[ 0 ] );
			elementsBeforeStart =
				$textElementsForUtterance.slice( 0, firstElementIndex );
			$tokenElement.prop(
				'startOffset',
				utteranceOffset - $( elementsBeforeStart ).text().length +
					startOffsetInUtteranceString
			);
			lastElementIndex =
				$textElementsForUtterance.index(
					textElements[ textElements.length - 1 ]
				);
			elementsBeforeEnd =
				$textElementsForUtterance.slice( 0, lastElementIndex );
			$tokenElement.prop(
				'endOffset',
				utteranceOffset - $( elementsBeforeEnd ).text().length +
					endOffsetInUtteranceString
			);
			return endOffsetInUtteranceString;
		};

		/**
		 * Calculate the start offset of a token in the utterance string.
		 *
		 * The token is the first match found, starting at
		 * searchOffset.
		 *
		 * @param {string} token The token to search for.
		 * @param {jQuery} $textElementsForUtterance The text elements
		 *  of the utterance where the token appear.
		 * @param {HTMLElement[]} textElements An array of text
		 *  elements to which each element, up to and including the
		 *  last one that contains part of the token, is added.
		 * @param {number} searchOffset Where we want to start looking
		 *  for the token in the utterance string.
		 * @return {number} The offset where the first character of
		 *  the token appears in the utterance string.
		 */

		this.getStartOffsetInUtteranceString = function (
			token,
			$textElementsForUtterance,
			textElements,
			searchOffset
		) {
			var concatenatedText, startOffsetInUtteranceString;

			// The concatenation of the strings from text
			// elements. Used to find tokens that span multiple text
			// nodes.
			concatenatedText = '';
			$textElementsForUtterance.each( function () {
				// Look through the text elements until we find a
				// substring matching the token.
				concatenatedText += this.textContent;
				textElements.push( this );
				if ( searchOffset > concatenatedText.length ) {
					// Don't look in text elements that end before
					// where we start looking.
					return;
				}
				startOffsetInUtteranceString = concatenatedText.indexOf(
					token, searchOffset
				);
				if ( startOffsetInUtteranceString !== -1 ) {
					return false;
				}
			} );
			return startOffsetInUtteranceString;
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.wikispeech = new Wikispeech();
	mw.wikispeech.Wikispeech = Wikispeech;

	if ( $( 'utterances' ).length ) {
		// Prepare the first utterance for playback.
		mw.wikispeech.wikispeech.prepareUtterance( $( '#utterance-0' ) );
		mw.wikispeech.wikispeech.addControlPanel();
		mw.wikispeech.wikispeech.addKeyboardShortcuts();
	}
}( mediaWiki, jQuery ) );
