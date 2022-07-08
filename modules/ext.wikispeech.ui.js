( function () {

	/**
	 * Creates and controls the UI for the extension.
	 *
	 * @class ext.wikispeech.Ui
	 * @constructor
	 */

	function Ui() {
		var self = this;
		// Resolves the UI is ready to be extended by consumer.
		self.ready = $.Deferred();

		/**
		 * Initialize elements and functionality for the UI.
		 */

		this.init = function () {
			self.addSelectionPlayer();
			if ( mw.wikispeech.consumerMode ) {
				// No edit lexicon button since we can not check the rights
				// on the producer.
				self.addControlPanel( false );
			} else {
				mw.user.getRights()
					.done( function ( rights ) {
						var canEditLexicon = rights.indexOf( 'wikispeech-edit-lexicon' ) >= 0;
						self.addControlPanel( canEditLexicon );
					} )
					.fail( function () {
						// If we can not get the rights we still want to
						// show the player. Assume the user does not have
						// the right to edit lexicon.
						self.addControlPanel( false );
					} );
			}
			self.addKeyboardShortcuts();
			self.windowManager = new OO.ui.WindowManager();
			self.addDialogs();
			self.ready.resolve();
		};

		/**
		 * Add a panel with controls for for Wikispeech.
		 *
		 * The panel contains buttons for controlling playback and
		 * links to related pages.
		 *
		 * @param {boolean} addEditButton Whether the button for edit
		 *  lexicon should be added or not.
		 */

		this.addControlPanel = function ( addEditButton ) {
			var $footer, toolFactory, toolGroupFactory, playerGroup,
				linkGroup, height, padding;

			// eslint-disable-next-line no-jquery/no-global-selector
			$footer = $( '#footer' );
			toolFactory = new OO.ui.ToolFactory();
			toolGroupFactory = new OO.ui.ToolGroupFactory();
			self.toolbar = new OO.ui.Toolbar(
				toolFactory,
				toolGroupFactory,
				{
					actions: true,
					classes: [ 'ext-wikispeech-control-panel' ],
					position: 'bottom'
				}
			);

			playerGroup = self.addToolbarGroup();
			self.addButton(
				playerGroup,
				'first',
				mw.wikispeech.player.skipBackUtterance
			);
			self.addButton(
				playerGroup,
				'previous',
				mw.wikispeech.player.skipBackToken
			);
			self.playStopButton = self.addButton(
				playerGroup,
				'play',
				mw.wikispeech.player.playOrStop,
				[ 'ext-wikispeech-play-stop' ]
			);
			self.addButton(
				playerGroup,
				'next',
				mw.wikispeech.player.skipAheadToken
			);
			self.addButton(
				playerGroup,
				'last',
				mw.wikispeech.player.skipAheadUtterance
			);

			linkGroup = self.addToolbarGroup();
			self.addLinkConfigButton(
				linkGroup,
				'help',
				'wgWikispeechHelpPage'
			);
			self.addLinkConfigButton(
				linkGroup,
				'feedback',
				'wgWikispeechFeedbackPage'
			);
			if ( addEditButton ) {
				self.addButton(
					linkGroup,
					'edit',
					mw.util.getUrl(
						'Special:EditLexicon',
						// Send URL parameters to the edit lexicon special
						// page. These will populate the fields.
						{
							language: mw.config.get( 'wgPageContentLanguage' ),
							page: mw.config.get( 'wgArticleId' )
						}
					)
				);
			}
			$( document.body ).append( self.toolbar.$element );
			self.toolbar.initialize();

			// Add extra padding at the bottom of the page to not have
			// the player cover anything.
			height = self.toolbar.$element.height();
			padding =
				Number( $footer.css( 'padding-bottom' ).slice( 0, -2 ) );
			$footer.css( 'padding-bottom', padding + height );
			self.addBufferingIcon();
		};

		/**
		 * Add a group to the player toolbar.
		 *
		 * @return {OO.ui.ButtonGroupWidget}
		 */

		this.addToolbarGroup = function () {
			var group = new OO.ui.ButtonGroupWidget();
			self.toolbar.$actions.append( group.$element );
			return group;
		};

		/**
		 * Add a control button.
		 *
		 * @param {OO.ui.ButtonGroupWidget} group Group to add button to.
		 * @param {string} icon Name of button icon.
		 * @param {Function|string} onClick Function to call or link.
		 * @param {string[]} classes CSS classes.
		 * @return {OO.ui.ButtonWidget}
		 */

		this.addButton = function ( group, icon, onClick, classes ) {
			// eslint-disable-next-line mediawiki/class-doc
			var button = new OO.ui.ButtonWidget( {
				icon: icon,
				classes: classes
			} );
			if ( typeof onClick === 'function' ) {
				button.on( 'click', onClick );
			} else if ( typeof onClick === 'string' ) {
				button.setHref( onClick );
				// Open link in new tab or window.
				button.setTarget( '_blank' );
			}
			group.addItems( [ button ] );
			return button;
		};

		/**
		 * Add buffering icon to the play/stop button.
		 *
		 * The icon shows when the waiting for audio to play.
		 */

		this.addBufferingIcon = function () {
			var $playStopButtons, $containers;

			$playStopButtons = $(
				self.toolbar.$element
					.find( '.ext-wikispeech-play-stop' )
			)
				.add( self.selectionPlayer.$element );
			$containers = $( '<span>' )
				.addClass( 'ext-wikispeech-buffering-icon-container' )
				.appendTo( ( $playStopButtons ).find( '.oo-ui-iconElement-icon' ) );
			self.$bufferingIcons = $( '<span>' )
				.addClass( 'ext-wikispeech-buffering-icon' )
				.appendTo( $containers )
				.hide();
		};

		/**
		 * Hide the buffering icon.
		 */

		this.hideBufferingIcon = function () {
			self.$bufferingIcons.hide();
		};

		/**
		 * Show the buffering icon if the current audio is loading.
		 */

		this.showBufferingIconIfAudioIsLoading = function ( audio ) {
			if ( self.audioIsReady( audio ) ) {
				self.hideBufferingIcon();
			} else {
				$( audio ).on( 'canplay', function () {
					self.hideBufferingIcon();
				} );
				self.$bufferingIcons.show();
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
			self.playStopButton.setIcon( 'stop' );
			self.selectionPlayer.setIcon( 'stop' );
		};

		/**
		 * Change the icon of the play/stop button to play.
		 */

		this.setPlayStopIconToPlay = function () {
			self.playStopButton.setIcon( 'play' );
			self.selectionPlayer.setIcon( 'play' );
		};

		/**
		 * Add a button that takes the user to another page.
		 *
		 * The button gets the link destination from a supplied
		 * config variable. If the variable isn't specified, the button
		 * isn't added.
		 *
		 * @param {OO.ui.ButtonGroupWidget} group Group to add button to.
		 * @param {string} icon Name of button icon.
		 * @param {string} configVariable The config variable to get
		 *  link destination from.
		 */

		this.addLinkConfigButton = function ( group, icon, configVariable ) {
			var url;

			url = mw.config.get( configVariable );
			if ( url ) {
				self.addButton( group, icon, url );
			}
		};

		/**
		 * Add a small player that appears when text is selected.
		 */

		this.addSelectionPlayer = function () {
			self.selectionPlayer = new OO.ui.ButtonWidget( {
				icon: 'play',
				classes: [
					'ext-wikispeech-selection-player',
					'ext-wikispeech-play-stop'
				]
			} )
				.on( 'click', mw.wikispeech.player.playOrStop );
			self.selectionPlayer.toggle( false );
			$( document.body ).append( self.selectionPlayer.$element );
			$( document ).on( 'mouseup', function () {
				if (
					self.isShown() &&
					mw.wikispeech.selectionPlayer.isSelectionValid()
				) {
					self.showSelectionPlayer();
				} else {
					self.selectionPlayer.toggle( false );
				}
			} );
			$( document ).on( 'click', function () {
				// A click listener is also needed because of the
				// order of events when text is deselected by clicking
				// it.
				if ( !mw.wikispeech.selectionPlayer.isSelectionValid() ) {
					self.selectionPlayer.toggle( false );
				}
			} );
		};

		/**
		 * Check if control panel is shown
		 *
		 * @return {boolean} Visibility of control panel.
		 */

		this.isShown = function () {
			return self.toolbar.isVisible();
		};

		/**
		 * Show the selection player below the end of the selection.
		 */

		this.showSelectionPlayer = function () {
			var selection, lastRange, lastRect, left, top;

			self.selectionPlayer.toggle( true );
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
					self.selectionPlayer.$element.width();
			}
			top = lastRect.bottom + $( document ).scrollTop();
			self.selectionPlayer.$element.css( {
				left: left + 'px',
				top: top + 'px'
			} );
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
			$( document ).on( 'keydown', function ( event ) {
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
			$( document ).on( 'keyup', function ( event ) {
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

		/**
		 * Create dialogs and add them to a window manager
		 */

		this.addDialogs = function () {
			$( document.body ).append( self.windowManager.$element );
			self.messageDialog = new OO.ui.MessageDialog();
			self.errorLoadAudioDialogData = {
				title: mw.msg( 'wikispeech-error-loading-audio-title' ),
				message: mw.msg( 'wikispeech-error-loading-audio-message' ),
				actions: [
					{
						action: 'retry',
						label: mw.msg( 'wikispeech-retry' ),
						flags: 'primary'
					},
					{
						action: 'stop',
						label: mw.msg( 'wikispeech-stop' ),
						flags: 'destructive'
					}
				]
			};
			self.addWindow( self.messageDialog );
		};

		/**
		 * Add a window to the window manager.
		 *
		 * @param {OO.ui.Window} window
		 */

		this.addWindow = function ( window ) {
			self.windowManager.addWindows( [ window ] );
		};

		/**
		 * Toggle GUI visibility
		 *
		 * Hides or shows control panel which also dictates whether
		 * the selection player should be shown.
		 */

		this.toggleVisibility = function () {
			if ( self.isShown() ) {
				self.toolbar.toggle( false );
				self.selectionPlayer.toggle( false );
			} else {
				self.toolbar.toggle( true );
				self.selectionPlayer.toggle( true );
			}
		};

		/**
		 * Show an error dialog for when audio could not be loaded
		 *
		 * Has buttons for retrying and stopping playback.
		 *
		 * @return {jQuery.Promise} Resolves when dialog is closed.
		 */

		this.showLoadAudioError = function () {
			return self.openWindow(
				self.messageDialog,
				self.errorLoadAudioDialogData
			);
		};

		/**
		 * Open a window.
		 *
		 * @param {OO.ui.Window} window
		 * @param {Object} data
		 * @return {jQuery.Promise} Resolves when window is closed.
		 */

		this.openWindow = function ( window, data ) {
			return self.windowManager.openWindow( window, data ).closed;
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.Ui = Ui;
	mw.wikispeech.ui = new Ui();
}() );
