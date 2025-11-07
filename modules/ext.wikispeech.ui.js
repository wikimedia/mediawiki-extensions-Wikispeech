/**
 * Creates and controls the UI for the extension.
 *
 * @class ext.wikispeech.Ui
 * @constructor
 */
const util = require( './ext.wikispeech.util.js' );

class Ui {
	constructor() {
		// Resolves the UI is ready to be extended by consumer.
		this.ready = $.Deferred();
		this.player = null;
		this.storage = null;
		this.highlighter = null;
		this.selectionPlayer = null;
	}

	/**
	 * Initialize elements and functionality for the UI.
	 */

	init() {
		this.addSelectionPlayer();
		this.addControlPanel();
		this.addKeyboardShortcuts();
		this.windowManager = new OO.ui.WindowManager();
		this.addDialogs();
		this.loadErrorAudio();
		this.ready.resolve();
	}

	/**
	 * Adds an item to the link dropdown menu.
	 *
	 * @param {Object} item The menu item to add.
	 * @param {string} item.url The URL for the menu item.
	 * @param {string} item.label The label for the menu item.
	 * @param {string} item.icon The icon for the menu item.
	 */
	addMenuItem( item ) {
		const option = new OO.ui.MenuOptionWidget( {
			data: item.url,
			label: item.label,
			icon: item.icon
		} );
		option.$element
			.attr( 'title', item.label )
			.attr( 'aria-label', item.label );

		this.linkMenuButton.getMenu().addItems( [ option ] );
	}

	/**
	 * Adds a dropdown menu button to a toolbar group.
	 *
	 * @param {OO.ui.ToolGroup} group The group to add the menu button to.
	 * @param {Function} onChoose Callback when a menu option is chosen.
	 * @return {OO.ui.ButtonMenuSelectWidget} The created menu button.
	 */
	addMenuButton( group, onChoose ) {
		const label = mw.msg( 'wikispeech-menu-label' );

		const button = new OO.ui.ButtonMenuSelectWidget( {
			icon: 'ellipsis'
		} );

		button.$element.find( 'a' )
			.attr( 'title', label )
			.attr( 'aria-label', label );

		button.getMenu().on( 'choose', ( item ) => {
			onChoose( item.getData(), item );
		} );

		group.addItems( [ button ] );
		return button;
	}

	/**
	 * Add a panel with controls for for Wikispeech.
	 *
	 * The panel contains buttons for controlling playback and
	 * links to related pages.
	 */

	addControlPanel() {
		const toolFactory = new OO.ui.ToolFactory();
		const toolGroupFactory = new OO.ui.ToolGroupFactory();
		this.toolbar = new OO.ui.Toolbar(
			toolFactory,
			toolGroupFactory,
			{
				actions: true,
				classes: [ 'ext-wikispeech-control-panel' ],
				position: 'bottom'
			}
		);
		const playerGroupPlayStop = this.addToolbarGroup();
		this.playPauseButton = this.addButton(
			playerGroupPlayStop,
			() => this.player.playOrPause(),
			{
				title: mw.msg( 'wikispeech-play' ),
				icon: 'play',
				flags: [
					'primary',
					'progressive'
				]
			}
		);
		this.addButton(
			playerGroupPlayStop,
			() => this.player.stop(),
			// TODO: add destructive flag when
			// https://gerrit.wikimedia.org/r/c/1194133 is done.
			{
				title: mw.msg( 'wikispeech-stop' ),
				icon: 'stop'
			}
		);
		const playerGroup = this.addToolbarGroup();
		this.addButton(
			playerGroup,
			() => this.player.skipBackUtterance(),
			{
				title: mw.msg( 'wikispeech-skip-back' ),
				icon: 'doubleChevronStart'
			}
		);
		this.addButton(
			playerGroup,
			() => this.player.skipBackToken(),
			{
				title: mw.msg( 'wikispeech-previous' ),
				icon: 'previous'
			}
		);
		this.addButton(
			playerGroup,
			() => this.player.skipAheadToken(),
			{
				title: mw.msg( 'wikispeech-next' ),
				icon: 'next'
			}
		);
		this.addButton(
			playerGroup,
			() => this.player.skipAheadUtterance(),
			{
				title: mw.msg( 'wikispeech-skip-ahead' ),
				icon: 'doubleChevronEnd'
			}
		);

		this.linkGroup = this.addToolbarGroup();
		this.linkMenuButton = this.addMenuButton(
			this.linkGroup,
			( url ) => window.open( url, '_blank' )
		);

		const helpUrl = mw.config.get( 'wgWikispeechHelpPage' );
		if ( helpUrl ) {
			this.addMenuItem( {
				url: mw.util.getUrl( helpUrl ),
				label: mw.msg( 'wikispeech-help' ),
				icon: 'help'
			} );
		}
		const feedbackUrl = mw.config.get( 'wgWikispeechFeedbackPage' );
		this.addMenuItem( {
			url: mw.util.getUrl( feedbackUrl ),
			label: mw.msg( 'wikispeech-feedback' ),
			icon: 'feedback'
		} );

		const api = new mw.Api();
		api.getUserInfo()
			.done( ( info ) => {
				const canEditLexicon = info.rights.includes( 'wikispeech-edit-lexicon' );
				if ( canEditLexicon ) {
					this.addMenuItem( this.createEditButton( null, null ) );
				}
			} );

		$( document.body ).append( this.toolbar.$element );
		this.toolbar.initialize();

		// Add extra padding at the bottom of the page to not have
		// the player cover anything.
		const height = this.toolbar.$element.height();
		this.$playerFooter = $( '<div>' )
			.height( height )
			// A bit of CSS is needed to make it interact properly
			// with the other floating elements in the footer.
			.css( {
				float: 'left',
				width: '100%'
			} )
			.appendTo( '#footer' );
		this.addBufferingIcon();
	}

	/**
	 * Add button that takes the user to the lexicon editor.
	 *
	 *  If`scriptUrl` is given, this is used to build the URL for
	 *  the editor page. It should be the URL to the script
	 *  endpoint of a wiki, i.e. "...index.php". If not given the
	 *  link will go to the page on the local wiki.
	 *
	 *  @param {string} [scriptUrl] Optional. The script endpoint for the editor page (i.e "...index.php").
	 *  @param {string} [consumerUrl] Optional. The consumer URL to include as a parameter.
	 *  @return {Object} Menu item configuration for the lexicon editor.
	 */

	createEditButton( scriptUrl, consumerUrl ) {
		let editUrl;
		if ( scriptUrl ) {
			editUrl = scriptUrl;
		} else {
			editUrl = mw.config.get( 'wgScript' );
		}
		const params = {
			title: 'Special:EditLexicon',
			language: mw.config.get( 'wgPageContentLanguage' ),
			page: mw.config.get( 'wgArticleId' )
		};

		if ( consumerUrl ) {
			params.consumerUrl = consumerUrl;
		}

		editUrl += '?' + new URLSearchParams( params );

		const label = mw.msg( 'wikispeech-edit-lexicon-btn' );

		return {
			url: editUrl,
			label: label,
			icon: 'edit',
			id: 'wikispeech-edit'
		};
	}

	/**
	 * Add a group to the player toolbar.
	 *
	 * @return {OO.ui.ButtonGroupWidget}
	 */

	addToolbarGroup() {
		const group = new OO.ui.ButtonGroupWidget();
		this.toolbar.$actions.append( group.$element );
		return group;
	}

	/**
	 * Add a control button.
	 *
	 * @param {OO.ui.ButtonGroupWidget} group Group to add button to.
	 * @param {Function|string} onClick Function to call or link.
	 * @param {string} label Labels, such as aria labels and titles
	 * @param {Object} config Configuration for the button widget.
	 *  `title` is also used as `aria-label` attribute.
	 *  See {@link OO.ui.ButtonWidget}.
	 * @return {OO.ui.ButtonWidget}
	 */

	addButton( group, onClick, config ) {
		config = config || {};
		const button = new OO.ui.ButtonWidget( config );
		if ( typeof onClick === 'function' ) {
			button.on( 'click', onClick );
		} else if ( typeof onClick === 'string' ) {
			button.setHref( onClick );
			// Open link in new tab or window.
			button.setTarget( '_blank' );
		}
		if ( config.title ) {
			button.$element.find( 'a' ).attr( 'aria-label', config.title );
		}
		group.addItems( [ button ] );
		return button;
	}

	/**
	 * Add buffering icon to the play/pause button.
	 *
	 * The icon shows when the waiting for audio to play.
	 */

	addBufferingIcon() {
		const $playPauseButtons = $().add( this.playPauseButton.$element ).add( this.playSelectionButton.$element );
		const $containers = $( '<span>' )
			.addClass( 'ext-wikispeech-buffering-icon-container' )
			.appendTo( ( $playPauseButtons ).find( '.oo-ui-iconElement-icon' ) );
		this.$bufferingIcons = $( '<span>' )
			.addClass( 'ext-wikispeech-buffering-icon' )
			.appendTo( $containers )
			.hide();
	}

	/**
	 * Hide the buffering icon.
	 */

	hideBufferingIcon() {
		this.$bufferingIcons.hide();
	}

	/**
	 * Show the buffering icon if the current audio is loading.
	 */

	showBufferingIconIfAudioIsLoading( audio ) {
		if ( this.audioIsReady( audio ) ) {
			this.hideBufferingIcon();
		} else {
			$( audio ).on( 'canplay', () => {
				this.hideBufferingIcon();
			} );
			this.$bufferingIcons.show();
		}
	}

	/**
	 * Check if the current audio is ready to play.
	 *
	 * The audio is deemed ready to play as soon as any playable
	 * data is available.
	 *
	 * @param {HTMLElement} audio The audio element to test.
	 * @return {boolean} True if the audio is ready to play else false.
	 */

	audioIsReady( audio ) {
		return audio.readyState >= 2;
	}

	/**
	 * Remove canplay listener for the audio to hide buffering icon.
	 *
	 * @param {jQuery} $audioElement Audio element from which the
	 *  listener is removed.
	 */

	removeCanPlayListener( $audioElement ) {
		$audioElement.off( 'canplay' );
	}

	/**
	 * Change the icon of the play/pause button to pause.
	 */

	setPlayPauseIconToPause() {
		this.playPauseButton.setIcon( 'pause' );
		this.playPauseButton.setTitle( mw.msg( 'wikispeech-pause' ) );
		this.playPauseButton.$element.find( 'a' ).attr( 'aria-label', mw.msg( 'wikispeech-pause' ) );
	}

	/**
	 * Change the icon of the play/pause button to play.
	 */

	setAllPlayerIconsToPlay() {
		this.playPauseButton.setIcon( 'play' );
		this.playPauseButton.setTitle( mw.msg( 'wikispeech-play' ) );
		this.playPauseButton.$element.find( 'a' ).attr( 'aria-label', mw.msg( 'wikispeech-play' ) );
		this.playSelectionButton.setIcon( 'play' );
	}

	/**
	 * Change the icon of the selectionPlayer to stop.
	 */

	setSelectionPlayerIconToStop() {
		this.playSelectionButton.setIcon( 'stop' );
		this.playSelectionButton.setTitle( mw.msg( 'wikispeech-stop' ) );
		this.playSelectionButton.$element.find( 'a' ).attr( 'aria-label', mw.msg( 'wikispeech-stop' ) );
	}

	/**
	 * Add a small player that appears when text is selected.
	 */

	addSelectionPlayer() {
		const label = mw.msg( 'wikispeech-play-selection' );
		this.playSelectionButton = new OO.ui.ButtonWidget( {
			icon: 'play',
			classes: [ 'ext-wikispeech-selection-player' ],
			title: label
		} );
		this.playSelectionButton.$element.find( 'a' ).attr( 'aria-label', label );
		this.playSelectionButton.on( 'click', () => this.player.playOrStop() );
		this.playSelectionButton.toggle( false );
		$( document.body ).append( this.playSelectionButton.$element );
		$( document ).on( 'mouseup', () => {
			if (
				this.isShown() &&
				this.selectionPlayer.isSelectionValid()
			) {
				this.showSelectionPlayer();
			} else {
				this.playSelectionButton.toggle( false );
			}
		} );
		$( document ).on( 'click', () => {
			// A click listener is also needed because of the
			// order of events when text is deselected by clicking
			// it.
			if ( !this.selectionPlayer.isSelectionValid() ) {
				this.playSelectionButton.toggle( false );
			}
		} );
	}

	/**
	 * Check if control panel is shown
	 *
	 * @return {boolean} Visibility of control panel.
	 */

	isShown() {
		return this.toolbar.isVisible();
	}

	/**
	 * Show the selection player below the end of the selection.
	 */

	showSelectionPlayer() {
		this.playSelectionButton.toggle( true );
		const selection = window.getSelection();
		const lastRange = selection.getRangeAt( selection.rangeCount - 1 );
		const lastRect =
			util.getLast( lastRange.getClientRects() );

		// Place the player under the end of the selected text.
		let left;
		if ( this.getTextDirection( lastRange.endContainer ) === 'rtl' ) {
			// For RTL languages, the end of the text is the far left.
			left = lastRect.left + $( document ).scrollLeft();
		} else {
			// For LTR languages, the end of the text is the far
			// right. This is the default value for the direction
			// property.
			left =
				lastRect.right +
				$( document ).scrollLeft() -
				this.playSelectionButton.$element.width();
		}
		const top = lastRect.bottom + $( document ).scrollTop();
		this.playSelectionButton.$element.css( {
			left: left + 'px',
			top: top + 'px'
		} );
	}

	/**
	 * Get the text direction for a node.
	 *
	 * @return {string} The CSS value of the `direction` property
	 *  for the node, or for its parent if it is a text node.
	 */

	getTextDirection( node ) {
		if ( node.nodeType === 3 ) {
			// For text nodes, get the property of the parent element.
			return $( node ).parent().css( 'direction' );
		} else {
			return $( node ).css( 'direction' );
		}
	}

	/**
	 * Register listeners for keyboard shortcuts.
	 */

	addKeyboardShortcuts() {
		const shortcuts = mw.config.get( 'wgWikispeechKeyboardShortcuts' );
		$( document ).on( 'keydown', ( event ) => {
			if ( this.eventMatchShortcut( event, shortcuts.playPause ) ) {
				this.player.playOrPause();
				return false;
			} else if (
				this.eventMatchShortcut(
					event,
					shortcuts.stop
				)
			) {
				this.player.stop();
				return false;
			} else if (
				this.eventMatchShortcut(
					event,
					shortcuts.skipAheadSentence
				)
			) {
				this.player.skipAheadUtterance();
				return false;
			} else if (
				this.eventMatchShortcut(
					event,
					shortcuts.skipBackSentence
				)
			) {
				this.player.skipBackUtterance();
				return false;
			} else if (
				this.eventMatchShortcut( event, shortcuts.skipAheadWord )
			) {
				this.player.skipAheadToken();
				return false;
			} else if (
				this.eventMatchShortcut( event, shortcuts.skipBackWord )
			) {
				this.player.skipBackToken();
				return false;
			}
		} );
		// Prevent keyup events from triggering if there is
		// keydown event for the same key combination. This caused
		// buttons in focus to trigger if a shortcut had space as
		// key.
		$( document ).on( 'keyup', ( event ) => {
			for ( const name in shortcuts ) {
				const shortcut = shortcuts[ name ];
				if ( this.eventMatchShortcut( event, shortcut ) ) {
					event.preventDefault();
				}
			}
		} );
	}

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

	eventMatchShortcut( event, shortcut ) {
		return event.which === shortcut.key &&
			event.ctrlKey === shortcut.modifiers.includes( 'ctrl' ) &&
			event.altKey === shortcut.modifiers.includes( 'alt' ) &&
			event.shiftKey === shortcut.modifiers.includes( 'shift' );
	}

	/**
	 * Create dialogs and add them to a window manager
	 */

	addDialogs() {
		$( document.body ).append( this.windowManager.$element );
		this.messageDialog = new OO.ui.MessageDialog();
		this.errorLoadAudioDialogData = {
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
		this.addWindow( this.messageDialog );
	}

	/**
	 * Add a window to the window manager.
	 *
	 * @param {OO.ui.Window} window
	 */

	addWindow( window ) {
		this.windowManager.addWindows( [ window ] );
	}

	/**
	 * Toggle GUI visibility
	 *
	 * Hides or shows control panel which also dictates whether
	 * the selection player should be shown.
	 */

	toggleVisibility() {
		if ( this.isShown() ) {
			this.toolbar.toggle( false );
			this.playSelectionButton.toggle( false );
			this.$playerFooter.hide();
		} else {
			this.toolbar.toggle( true );
			this.playSelectionButton.toggle( true );
			this.$playerFooter.show();
		}
	}

	/**
	 * Loads the error audio once and calls it in init()
	 */

	loadErrorAudio() {
		const lang = mw.config.get( 'wgUserLanguage' ) || 'en';
		let errorAudioData;

		try {
			errorAudioData = require( `./audio/error.${ lang }.json` );
		} catch ( e ) {
			errorAudioData = require( './audio/error.en.json' );
		}
		const src = 'data:audio/ogg;base64,' + errorAudioData[ 'wikispeech-listen' ].audio;

		this.errorAudio = new Audio( src );
	}

	/**
	 * Show an error dialog for when audio could not be loaded
	 *
	 * Has buttons for retrying and stopping playback.
	 *
	 * @return {jQuery.Promise} Resolves when dialog is closed.
	 */

	showLoadAudioError() {
		if ( this.errorAudio ) {
			this.errorAudio.play();
		}

		return this.openWindow(
			this.messageDialog,
			this.errorLoadAudioDialogData
		).then( ( data ) => {
			if ( this.errorAudio ) {
				this.errorAudio.pause();
				this.errorAudio.currentTime = 0;
			}
			return data;
		} );
	}

	/**
	 * Open a window.
	 *
	 * @param {OO.ui.Window} window
	 * @param {Object} data
	 * @return {jQuery.Promise} Resolves when window is closed.
	 */

	openWindow( window, data ) {
		return this.windowManager.openWindow( window, data ).closed;
	}
}

module.exports = Ui;
