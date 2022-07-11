( function () {
	var sandbox, contentSelector, selectionPlayer, player, ui;

	QUnit.module( 'ext.wikispeech.ui', {
		beforeEach: function () {
			mw.wikispeech.player = sinon.stub( new mw.wikispeech.Player() );
			player = mw.wikispeech.player;
			mw.wikispeech.selectionPlayer =
				sinon.stub( new mw.wikispeech.SelectionPlayer() );
			selectionPlayer = mw.wikispeech.selectionPlayer;
			ui = new mw.wikispeech.Ui();
			$( '#qunit-fixture' ).append(
				$( '<div>' ).attr( 'id', 'content' ),
				$( '<div>' ).attr( 'id', 'footer' )
			);
			mw.config.set( 'wgWikispeechContentSelector', '#mw-content-text' );
			contentSelector =
				mw.config.get( 'wgWikispeechContentSelector' );
			sandbox = sinon.sandbox.create();
		},
		afterEach: function () {
			sandbox.restore();
			// Remove the event listeners to not trigger them after
			// the tests have run.
			$( document ).off( 'mouseup' );
			$( '#qunit-fixture' ).empty();
			$( '.ext-wikispeech-control-panel, .ext-wikispeech-selection-player' ).remove();
		}
	} );

	QUnit.test( 'addControlPanel(): add help button if page is set', function ( assert ) {
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechHelpPage', 'Help' );
		sinon.stub( ui, 'addBufferingIcon' );

		ui.addControlPanel();

		assert.strictEqual(
			$( '.ext-wikispeech-control-panel' )
				.find( '.oo-ui-buttonElement-button[href="./Help"]' )
				.length,
			1
		);
	} );

	QUnit.test( 'addControlPanel(): add feedback button', function ( assert ) {
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechFeedbackPage', 'Feedback' );
		sinon.stub( ui, 'addBufferingIcon' );

		ui.addControlPanel();

		assert.strictEqual(
			$( '.ext-wikispeech-control-panel' )
				.find( '.oo-ui-buttonElement-button[href="./Feedback"]' )
				.length,
			1
		);
	} );

	QUnit.test( 'showBufferingIconIfAudioIsLoading()', function () {
		var mockAudio;

		ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
		mockAudio = { readyState: 0 };

		ui.showBufferingIconIfAudioIsLoading( mockAudio );

		sinon.assert.called( ui.$bufferingIcons.show );
	} );

	QUnit.test( 'showBufferingIconIfAudioIsLoading(): already loaded', function () {
		var mockAudio;

		ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
		mockAudio = { readyState: 2 };

		ui.showBufferingIconIfAudioIsLoading( mockAudio );

		sinon.assert.notCalled( ui.$bufferingIcons.show );
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up shows selection player', function () {
		var textNode, expectedLeft, event;

		mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
		textNode = $( contentSelector ).contents().get( 0 );
		selectionPlayer.isSelectionValid.returns( true );
		self.stubGetSelection( textNode, textNode, { right: 15, bottom: 10 } );
		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		sinon.spy( ui.selectionPlayer.$element, 'css' );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		expectedLeft =
			15 -
			$( '.ext-wikispeech-selection-player' ).width() +
			$( document ).scrollLeft();
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, true );
		sinon.assert.calledWith(
			ui.selectionPlayer.$element.css,
			{
				left: expectedLeft + 'px',
				top: 10 + $( document ).scrollTop() + 'px'
			}
		);
	} );

	/**
	 * Add a mocked control panel for tests that need to check if it's visible
	 */
	this.addControlPanel = function () {
		$( '<div>' ).addClass( 'ext-wikispeech-control-panel' )
			.appendTo( $( '#qunit-fixture' ) );
	};

	/**
	 * Stub window.getSelection
	 *
	 * @param {Node} startContainer Node where selection starts.
	 * @param {Node} endContainer Node where selection ends.
	 * @param {DOMRect} rect The selection rectangle.
	 */
	this.stubGetSelection = function ( startContainer, endContainer, rect ) {
		sandbox.stub( window, 'getSelection' ).returns( {
			rangeCount: 1,
			getRangeAt: function () {
				return {
					getClientRects: function () {
						return [ rect ];
					},
					startContainer: startContainer,
					endContainer: endContainer
				};
			}
		} );
	};

	QUnit.test( 'addSelectionPlayer(): mouse up shows selection player, RTL', function () {
		var textNode, event;

		mw.wikispeech.test.util.setContentHtml(
			'<b style="direction: rtl">RTL text.</b>'
		);
		textNode = $( contentSelector + ' b' ).contents().get( 0 );
		selectionPlayer.isSelectionValid.returns( true );
		self.stubGetSelection( textNode, textNode, { left: 15, bottom: 10 } );
		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		sinon.spy( ui.selectionPlayer.$element, 'css' );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, true );
		sinon.assert.calledWith(
			ui.selectionPlayer.$element.css,
			{
				left: '15px',
				top: 10 + $( document ).scrollTop() + 'px'
			}
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when text is not selected', function () {
		var event;

		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( false );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when start of selection is not in an utterance node', function () {
		var notUtteranceNode, utteranceNode, event;

		mw.wikispeech.test.util.setContentHtml(
			'<del>Not an utterance.</del> An utterance.'
		);
		notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
		utteranceNode = $( contentSelector ).contents().get( 1 );
		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		sinon.spy( ui.selectionPlayer, 'toggle' );
		self.stubGetSelection( notUtteranceNode, utteranceNode );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when end of selection is not in an utterance node', function () {
		var notUtteranceNode, utteranceNode, event;

		mw.wikispeech.test.util.setContentHtml(
			'An utterance. <del>Not an utterance.</del>'
		);
		notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
		utteranceNode = $( contentSelector ).contents().get( 0 );
		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		sinon.spy( ui.selectionPlayer, 'toggle' );
		self.stubGetSelection( utteranceNode, notUtteranceNode );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): do not show if UI is hidden', function () {
		var textNode, event;

		mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
		textNode = $( contentSelector ).contents().get( 0 );
		sinon.stub( ui, 'isShown' ).returns( false );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( true );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		self.stubGetSelection( textNode, textNode );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	/**
	 * Create a keydown event.
	 *
	 * @param {number} keyCode The key code for the event.
	 * @param {string} modifiers A string that defines the
	 *  modifiers. The characters c, a and s triggers the modifiers
	 *  for ctrl, alt and shift, respectively.
	 * @return {jQuery} The created keydown event.
	 */
	function createKeydownEvent( keyCode, modifiers ) {
		var event = $.Event( 'keydown' );
		event.which = keyCode;
		event.ctrlKey = modifiers.indexOf( 'c' ) >= 0;
		event.altKey = modifiers.indexOf( 'a' ) >= 0;
		event.shiftKey = modifiers.indexOf( 's' ) >= 0;
		return event;
	}

	/**
	 * Test that a keyboard event triggers the correct function.
	 *
	 * @param {QUnit.assert} assert
	 * @param {string} functionName Name of the function that should
	 *  be called.
	 * @param {number} keyCode The key code for the event.
	 * @param {string} modifiers A string that defines the
	 *  modifiers. The characters c, a and s triggers the modifiers
	 *  for ctrl, alt and shift, respectively.
	 */
	function testKeyboardShortcut( assert, functionName, keyCode, modifiers ) {
		mw.config.set(
			'wgWikispeechKeyboardShortcuts', {
				playStop: {
					key: 32,
					modifiers: [ 'ctrl' ]
				},
				skipAheadSentence: {
					key: 39,
					modifiers: [ 'ctrl' ]
				},
				skipBackSentence: {
					key: 37,
					modifiers: [ 'ctrl' ]
				},
				skipAheadWord: {
					key: 40,
					modifiers: [ 'ctrl' ]
				},
				skipBackWord: {
					key: 38,
					modifiers: [ 'ctrl' ]
				}
			}
		);
		ui.addKeyboardShortcuts();

		$( document ).trigger( createKeydownEvent( keyCode, modifiers ) );

		assert.strictEqual( player[ functionName ].called, true );
	}

	// Skipping these tests for now since they are causing
	// trouble. They will likely not be needed, or at least be
	// reworked, once we switch to OOUI (T181780).
	QUnit.skip( 'Pressing keyboard shortcut for play/stop', function ( assert ) {
		testKeyboardShortcut( assert, 'playOrStop', 32, 'c' );
	} );

	QUnit.skip( 'Pressing keyboard shortcut for skipping ahead sentence', function ( assert ) {
		testKeyboardShortcut( assert, 'skipAheadUtterance', 39, 'c' );
	} );

	QUnit.skip( 'Pressing keyboard shortcut for skipping back sentence', function ( assert ) {
		testKeyboardShortcut( assert, 'skipBackUtterance', 37, 'c' );
	} );

	QUnit.skip( 'Pressing keyboard shortcut for skipping ahead word', function ( assert ) {
		testKeyboardShortcut( assert, 'skipAheadToken', 40, 'c' );
	} );

	QUnit.skip( 'Pressing keyboard shortcut for skipping back word', function ( assert ) {
		testKeyboardShortcut( assert, 'skipBackToken', 38, 'c' );
	} );

	QUnit.test( 'toggleVisibility(): hide', function () {
		ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
		ui.selectionPlayer = sinon.stub( new OO.ui.ButtonWidget() );
		ui.$playerFooter = sinon.stub( $( '<div>' ) );
		sinon.stub( ui, 'isShown' ).returns( true );

		ui.toggleVisibility();

		sinon.assert.calledWith( ui.toolbar.toggle, false );
		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
		sinon.assert.called( ui.$playerFooter.hide );
	} );

	QUnit.test( 'toggleVisibility(): show', function () {
		ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
		ui.selectionPlayer = sinon.stub( new OO.ui.ButtonWidget() );
		ui.$playerFooter = sinon.stub( $( '<div>' ) );
		sinon.stub( ui, 'isShown' ).returns( false );

		ui.toggleVisibility();

		sinon.assert.calledWith( ui.toolbar.toggle, true );
		sinon.assert.calledWith( ui.selectionPlayer.toggle, true );
		sinon.assert.called( ui.$playerFooter.show );
	} );
}() );
