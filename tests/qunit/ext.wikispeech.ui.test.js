( function () {
	let sandbox, contentSelector, selectionPlayer, player, ui;

	QUnit.module( 'ext.wikispeech.ui', QUnit.newMwEnvironment( {
		beforeEach: function () {
			mw.wikispeech.player = sinon.stub( new mw.wikispeech.Player() );
			player = mw.wikispeech.player;
			mw.wikispeech.selectionPlayer =
				sinon.stub( new mw.wikispeech.SelectionPlayer() );
			selectionPlayer = mw.wikispeech.selectionPlayer;
			ui = new mw.wikispeech.Ui();
			sinon.stub( ui, 'addBufferingIcon' );
			$( '#qunit-fixture' ).append(
				$( '<div>' ).attr( 'id', 'content' ),
				$( '<div>' ).attr( 'id', 'footer' )
			);
			contentSelector = '#mw-content-text';
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
	} ) );

	QUnit.test( 'addControlPanel(): add help button if page is set', ( assert ) => {
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechHelpPage', 'Help' );

		ui.addControlPanel();

		assert.strictEqual(
			$( '.ext-wikispeech-control-panel' )
				.find( '.oo-ui-buttonElement-button[href="./Help"]' )
				.length,
			1
		);
	} );

	QUnit.test( 'addControlPanel(): add feedback button', ( assert ) => {
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechFeedbackPage', 'Feedback' );

		ui.addControlPanel();

		assert.strictEqual(
			$( '.ext-wikispeech-control-panel' )
				.find( '.oo-ui-buttonElement-button[href="./Feedback"]' )
				.length,
			1
		);
	} );

	QUnit.test( 'addEditButton(): add edit button with link to local URL', function () {
		let addButton;

		mw.config.set( 'wgPageContentLanguage', 'en' );
		mw.config.set( 'wgArticleId', 1 );
		mw.config.set( 'wgScript', '/wiki/index.php' );
		ui.linkGroup = this.sandbox.stub( new OO.ui.ButtonGroupWidget() );
		addButton = this.sandbox.stub( ui, 'addButton' );

		ui.addEditButton();

		sinon.assert.calledWith(
			addButton,
			ui.linkGroup,
			'edit',
			// The colon in "Special:EditLexicon" is URL encoded, see:
			// https://url.spec.whatwg.org/#concept-urlencoded-serializer.
			'/wiki/index.php?title=Special%3AEditLexicon&language=en&page=1',
			null,
			'wikispeech-edit'
		);
	} );

	QUnit.test( 'addEditButton(): add edit button with link to given script URL', function () {
		let addButton;

		mw.config.set( 'wgWikispeechAllowConsumerEdits', true );
		mw.config.set( 'wgPageContentLanguage', 'en' );
		mw.config.set( 'wgArticleId', 1 );
		ui.linkGroup = this.sandbox.stub( new OO.ui.ButtonGroupWidget() );
		addButton = this.sandbox.stub( ui, 'addButton' );

		ui.addEditButton( 'http://producer.url/w/index.php' );

		sinon.assert.calledWith(
			addButton,
			ui.linkGroup,
			'edit',
			// The colon in "Special:EditLexicon" is URL encoded, see:
			// https://url.spec.whatwg.org/#concept-urlencoded-serializer.
			'http://producer.url/w/index.php?title=Special%3AEditLexicon&language=en&page=1',
			null,
			'wikispeech-edit'
		);
	} );

	QUnit.test( 'showBufferingIconIfAudioIsLoading()', () => {
		let mockAudio;

		ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
		mockAudio = { readyState: 0 };

		ui.showBufferingIconIfAudioIsLoading( mockAudio );

		sinon.assert.called( ui.$bufferingIcons.show );
	} );

	QUnit.test( 'showBufferingIconIfAudioIsLoading(): already loaded', () => {
		let mockAudio;

		ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
		mockAudio = { readyState: 2 };

		ui.showBufferingIconIfAudioIsLoading( mockAudio );

		sinon.assert.notCalled( ui.$bufferingIcons.show );
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up shows selection player', () => {
		let textNode, event;

		mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
		textNode = $( contentSelector ).contents().get( 0 );
		selectionPlayer.isSelectionValid.returns( true );
		self.stubGetSelection( textNode, textNode, { right: 50, bottom: 10 } );
		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		ui.selectionPlayer.$element.width( 30 );
		sinon.spy( ui.selectionPlayer.$element, 'css' );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		event = $.Event( 'mouseup' );

		$( document ).triggerHandler( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, true );
		sinon.assert.calledWith(
			ui.selectionPlayer.$element.css,
			{
				left: '20px',
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

	QUnit.test( 'addSelectionPlayer(): mouse up shows selection player, RTL', () => {
		let textNode, event;

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

		$( document ).triggerHandler( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, true );
		sinon.assert.calledWith(
			ui.selectionPlayer.$element.css,
			{
				left: '15px',
				top: 10 + $( document ).scrollTop() + 'px'
			}
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when text is not selected', () => {
		let event;

		sinon.stub( ui, 'isShown' ).returns( true );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( false );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		event = $.Event( 'mouseup' );

		$( document ).triggerHandler( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when start of selection is not in an utterance node', () => {
		let notUtteranceNode, utteranceNode, event;

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

		$( document ).triggerHandler( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when end of selection is not in an utterance node', () => {
		let notUtteranceNode, utteranceNode, event;

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

		$( document ).triggerHandler( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): do not show if UI is hidden', () => {
		let textNode, event;

		mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
		textNode = $( contentSelector ).contents().get( 0 );
		sinon.stub( ui, 'isShown' ).returns( false );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( true );
		sinon.spy( ui.selectionPlayer, 'toggle' );
		self.stubGetSelection( textNode, textNode );
		event = $.Event( 'mouseup' );

		$( document ).triggerHandler( event );

		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	} );

	QUnit.test( 'addSelectionPlayer(): hide selection player initially', ( assert ) => {
		ui.addSelectionPlayer();

		assert.false( ui.selectionPlayer.isVisible() );
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
		const event = $.Event( 'keydown' );
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

		$( document ).triggerHandler( createKeydownEvent( keyCode, modifiers ) );

		assert.strictEqual( player[ functionName ].called, true );
	}

	QUnit.test( 'Pressing keyboard shortcut for play/stop', ( assert ) => {
		testKeyboardShortcut( assert, 'playOrStop', 32, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping ahead sentence', ( assert ) => {
		testKeyboardShortcut( assert, 'skipAheadUtterance', 39, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping back sentence', ( assert ) => {
		testKeyboardShortcut( assert, 'skipBackUtterance', 37, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping ahead word', ( assert ) => {
		testKeyboardShortcut( assert, 'skipAheadToken', 40, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping back word', ( assert ) => {
		testKeyboardShortcut( assert, 'skipBackToken', 38, 'c' );
	} );

	QUnit.test( 'toggleVisibility(): hide', () => {
		ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
		ui.selectionPlayer = sinon.stub( new OO.ui.ButtonWidget() );
		ui.$playerFooter = sinon.stub( $( '<div>' ) );
		sinon.stub( ui, 'isShown' ).returns( true );

		ui.toggleVisibility();

		sinon.assert.calledWith( ui.toolbar.toggle, false );
		sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
		sinon.assert.called( ui.$playerFooter.hide );
	} );

	QUnit.test( 'toggleVisibility(): show', () => {
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
