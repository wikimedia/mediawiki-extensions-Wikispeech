( function ( mw, $ ) {

	/**
	 * Creates and controls the UI for the extension.
	 *
	 * @class ext.wikispeech.Ui
	 * @constructor
	 */

	function Ui() {
		var self = this;

		/**
		 * Initialize elements and functionality for the UI.
		 */

		this.init = function () {
			mw.wikispeech.ui.addControlPanel();
			mw.wikispeech.ui.addSelectionPlayer();
			mw.wikispeech.ui.addStackToPlayStopButton();
			mw.wikispeech.ui.addKeyboardShortcuts();
		};

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
			self.addButton(
				'ext-wikispeech-skip-back-sentence',
				mw.wikispeech.player.skipBackUtterance
			);
			self.addButton(
				'ext-wikispeech-skip-back-word',
				mw.wikispeech.player.skipBackToken
			);
			self.addButton(
				'ext-wikispeech-play-stop-button',
				mw.wikispeech.player.playOrStop
			);
			self.addButton(
				'ext-wikispeech-skip-ahead-word',
				mw.wikispeech.player.skipAheadToken
			);
			self.addButton(
				'ext-wikispeech-skip-ahead-sentence',
				mw.wikispeech.player.skipAheadUtterance
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
		* Add a control button.
		*
		* @param {string} cssClass The name of the CSS class to add to
		*  the button.
		* @param {string} onClickFunction The name of the function to
		*  call when the button is clicked.
		*/

		this.addButton = function ( cssClass, onClickFunction ) {
			var $button = $( '<button></button>' )
				.addClass( cssClass )
				.appendTo( '#ext-wikispeech-control-panel' );
			$button.click( onClickFunction );
			return $button;
		};

		/**
		 * Add a stack which contains the buffering icon to `playStopButton`s.
		 */

		this.addStackToPlayStopButton = function () {
			this.addSpanToPlayStopButton();
			this.addElementToPlayStopButtonStack(
				'ext-wikispeech-play-stop ext-wikispeech-play fa-stack-2x'
			);
			this.addElementToPlayStopButtonStack(
				'ext-wikispeech-buffering-icon fa-stack-2x fa-spin'
			);
			$( '.ext-wikispeech-play-stop-stack' ).css( 'font-size', '50%' );
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility', 'hidden' );
		};

		/**
		 * Add a Font Awesome stack to the play button.
		 */

		this.addSpanToPlayStopButton = function () {
			$( '<span></span>' )
				.addClass( 'ext-wikispeech-play-stop-stack fa-stack fa-lg' )
				.appendTo( '.ext-wikispeech-play-stop-button' );
		};

		/**
		 * Add an element to the stack on the playStop button.
		 *
		 * @param {string} cssClass The name of the CSS class to add
		 *  the item.
		 */

		this.addElementToPlayStopButtonStack = function ( cssClass ) {
			$( '<i></i>' )
				.addClass( 'fa ' + cssClass )
				.appendTo( '.ext-wikispeech-play-stop-stack' );
		};

		/**
		 * Hide the buffering icon.
		 */

		this.hideBufferingIcon = function () {
			$( '.ext-wikispeech-buffering-icon' )
				.css( 'visibility', 'hidden' );
		};

		/**
		 * Show the buffering icon if the current audio is loading.
		 */

		this.showBufferingIconIfAudioIsLoading = function ( audio ) {
			if ( self.audioIsReady( audio ) ) {
				self.hideBufferingIcon();
			} else {
				self.addCanPlayListener( $( audio ) );
				$( '.ext-wikispeech-buffering-icon' )
					.css( 'visibility', 'visible' );
			}
		};

		/**
		 * Check if the current audio is ready to play.
		 *
		 * The audio is deemed ready to play as soon as any playable
		 * data is available.
		 *
		 * @param {HTMLElement} audio The audio element to test.
		 * @return {boolean} True if the audio is ready to play else false.
		 */

		this.audioIsReady = function ( audio ) {
			return audio.readyState >= 2;
		};

		/**
		 * Add canplay listener for the audio to hide buffering icon.
		 *
		 * Canplaythrough will be caught implicitly as it occurs after
		 * canplay.
		 *
		 * @param {jQuery} $audioElement Audio element to which the
		 *  listener is added.
		 */

		this.addCanPlayListener = function ( $audioElement ) {
			$audioElement.on( 'canplay', function () {
				$( '.ext-wikispeech-buffering-icon' )
					.css( 'visibility', 'hidden' );
			} );
		};

		/**
		 * Remove canplay listener for the audio to hide buffering icon.
		 *
		 * @param {jQuery} $audioElement Audio element from which the
		 *  listener is removed.
		 */

		this.removeCanPlayListener = function ( $audioElement ) {
			$audioElement.off( 'canplay' );
		};

		/**
		 * Change the icon of the play/stop button to stop.
		 */

		this.setPlayStopIconToStop = function () {
			$( '.ext-wikispeech-play-stop' ).addClass( 'ext-wikispeech-stop' );
			$( '.ext-wikispeech-play-stop' )
				.removeClass( 'ext-wikispeech-play' );
		};

		/**
		 * Change the icon of the play/stop button to play.
		 */

		this.setPlayStopIconToPlay = function () {
			$( '.ext-wikispeech-play-stop' ).addClass( 'ext-wikispeech-play' );
			$( '.ext-wikispeech-play-stop' )
				.removeClass( 'ext-wikispeech-stop' );
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
		 * Add a small player that appears when text is selected.
		 */

		this.addSelectionPlayer = function () {
			var $player = $( '<div></div>' )
				.addClass( 'ext-wikispeech-selection-player' )
				.appendTo( '#content' );
			$( '<button></button>' )
				.addClass( 'ext-wikispeech-play-stop-button' )
				.click( mw.wikispeech.player.playOrStop )
				.appendTo( $player );
			$( document ).on( 'mouseup', function () {
				if ( mw.wikispeech.selectionPlayer.isSelectionValid() ) {
					self.showSelectionPlayer();
				} else {
					$( '.ext-wikispeech-selection-player' )
						.css( 'visibility', 'hidden' );
				}
			} );
			$( document ).on( 'click', function () {
				// A click listener is also needed because of the
				// order of events when text is deselected by clicking
				// it.
				if ( !mw.wikispeech.selectionPlayer.isSelectionValid() ) {
					$( '.ext-wikispeech-selection-player' )
						.css( 'visibility', 'hidden' );
				}
			} );
		};

		/**
		 * Show the selection player below the end of the selection.
		 */

		this.showSelectionPlayer = function () {
			var selection, lastRange, lastRect, left, top;

			selection = window.getSelection();
			lastRange = selection.getRangeAt( selection.rangeCount - 1 );
			lastRect =
				mw.wikispeech.util.getLast( lastRange.getClientRects() );
			// Place the player under the end of the selected text.
			if ( self.getTextDirection( lastRange.endContainer ) === 'rtl' ) {
				// For RTL languages, the end of the text is the far left.
				left = lastRect.left + $( document ).scrollLeft();
			} else {
				// For LTR languages, the end of the text is the far
				// right. This is the default value for the direction
				// property.
				left =
					lastRect.right +
					$( document ).scrollLeft() -
					$( '.ext-wikispeech-selection-player' ).width();
			}
			$( '.ext-wikispeech-selection-player' ).css( 'left', left );
			top = lastRect.bottom + $( document ).scrollTop();
			$( '.ext-wikispeech-selection-player' ).css( 'top', top );
			$( '.ext-wikispeech-selection-player' )
				.css( 'visibility', 'visible' );
		};

		/**
		 * Get the text direction for a node.
		 *
		 * @return {string} The CSS value of the `direction` property
		 *  for the node, or for its parent if it is a text node.
		 */

		this.getTextDirection = function ( node ) {
			if ( node.nodeType === 3 ) {
				// For text nodes, get the property of the parent element.
				return $( node ).parent().css( 'direction' );
			} else {
				return $( node ).css( 'direction' );
			}
		};

		/**
		 * Register listeners for keyboard shortcuts.
		 */

		this.addKeyboardShortcuts = function () {
			var shortcuts, name, shortcut;

			shortcuts = mw.config.get( 'wgWikispeechKeyboardShortcuts' );
			$( document ).keydown( function ( event ) {
				if ( self.eventMatchShortcut( event, shortcuts.playStop ) ) {
					mw.wikispeech.player.playOrStop();
					return false;
				} else if (
					self.eventMatchShortcut(
						event,
						shortcuts.skipAheadSentence
					)
				) {
					mw.wikispeech.player.skipAheadUtterance();
					return false;
				} else if (
					self.eventMatchShortcut(
						event,
						shortcuts.skipBackSentence
					)
				) {
					mw.wikispeech.player.skipBackUtterance();
					return false;
				} else if (
					self.eventMatchShortcut( event, shortcuts.skipAheadWord )
				) {
					mw.wikispeech.player.skipAheadToken();
					return false;
				} else if (
					self.eventMatchShortcut( event, shortcuts.skipBackWord )
				) {
					mw.wikispeech.player.skipBackToken();
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
		 * @param {Object} shortcut The shortcut object from the
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
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.Ui = Ui;
	mw.wikispeech.ui = new Ui();
}( mediaWiki, jQuery ) );
