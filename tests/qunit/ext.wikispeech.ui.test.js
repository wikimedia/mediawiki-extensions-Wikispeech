let contentSelector, selectionPlayer, player, ui;

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
		mw.config.set( 'wgWikispeechContentSelector', contentSelector );

		/**
		 * Stub window.getSelection
		 *
		 * @param {Node} startContainer Node where selection starts.
		 * @param {Node} endContainer Node where selection ends.
		 * @param {DOMRect} rect The selection rectangle.
		 */
		this.stubGetSelection = function ( startContainer, endContainer, rect ) {
			this.sandbox.stub( window, 'getSelection' ).returns( {
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
	},
	afterEach: function () {
		// Remove the event listeners to not trigger them after
		// the tests have run.
		$( document ).off( 'mouseup' );
		$( '#qunit-fixture' ).empty();
		$( '.ext-wikispeech-control-panel, .ext-wikispeech-selection-player' ).remove();
	}
} ) );

QUnit.skip( 'addControlPanel(): add help button if page is set', ( assert ) => {
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

QUnit.skip( 'addControlPanel(): add feedback button', ( assert ) => {
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

QUnit.skip( 'addEditButton(): add edit button with link to local URL', function () {
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	mw.config.set( 'wgScript', '/wiki/index.php' );
	ui.linkGroup = this.sandbox.stub( new OO.ui.ButtonGroupWidget() );
	const addButton = this.sandbox.stub( ui, 'addButton' );

	ui.addEditButton();

	sinon.assert.calledWith(
		addButton,
		ui.linkGroup,
		'edit',
		// The colon in "Special:EditLexicon" is URL encoded, see:
		// https://url.spec.whatwg.org/#concept-urlencoded-serializer.
		'/wiki/index.php?title=Special%3AEditLexicon&language=en&page=1',
		mw.msg( 'wikispeech-edit-lexicon-btn' ),
		null,
		'wikispeech-edit'
	);
} );

QUnit.skip( 'addEditButton(): add edit button with link to given script URL', function () {
	mw.config.set( 'wgWikispeechAllowConsumerEdits', true );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	ui.linkGroup = this.sandbox.stub( new OO.ui.ButtonGroupWidget() );
	const addButton = this.sandbox.stub( ui, 'addButton' );

	ui.addEditButton( 'http://producer.url/w/index.php' );

	sinon.assert.calledWith(
		addButton,
		ui.linkGroup,
		'edit',
		// The colon in "Special:EditLexicon" is URL encoded, see:
		// https://url.spec.whatwg.org/#concept-urlencoded-serializer.
		'http://producer.url/w/index.php?title=Special%3AEditLexicon&language=en&page=1',
		mw.msg( 'wikispeech-edit-lexicon-btn' ),
		null,
		'wikispeech-edit'
	);
} );

QUnit.skip( 'showBufferingIconIfAudioIsLoading()', () => {
	ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
	const mockAudio = { readyState: 0 };

	ui.showBufferingIconIfAudioIsLoading( mockAudio );

	sinon.assert.called( ui.$bufferingIcons.show );
} );

QUnit.skip( 'showBufferingIconIfAudioIsLoading(): already loaded', () => {
	ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
	const mockAudio = { readyState: 2 };

	ui.showBufferingIconIfAudioIsLoading( mockAudio );

	sinon.assert.notCalled( ui.$bufferingIcons.show );
} );

QUnit.test( 'addSelectionPlayer(): mouse up shows selection player', function () {
	mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
	const textNode = $( contentSelector ).contents().get( 0 );
	selectionPlayer.isSelectionValid.returns( true );
	this.stubGetSelection( textNode, textNode, { right: 50, bottom: 10 } );
	sinon.stub( ui, 'isShown' ).returns( true );
	ui.addSelectionPlayer();
	ui.selectionPlayer.$element.width( 30 );
	sinon.spy( ui.selectionPlayer.$element, 'css' );
	sinon.spy( ui.selectionPlayer, 'toggle' );
	const event = $.Event( 'mouseup' );

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

QUnit.test( 'addSelectionPlayer(): mouse up shows selection player, RTL', function () {
	mw.wikispeech.test.util.setContentHtml(
		'<b style="direction: rtl">RTL text.</b>'
	);
	const textNode = $( contentSelector + ' b' ).contents().get( 0 );
	selectionPlayer.isSelectionValid.returns( true );
	this.stubGetSelection( textNode, textNode, { left: 15, bottom: 10 } );
	sinon.stub( ui, 'isShown' ).returns( true );
	ui.addSelectionPlayer();
	sinon.spy( ui.selectionPlayer.$element, 'css' );
	sinon.spy( ui.selectionPlayer, 'toggle' );
	const event = $.Event( 'mouseup' );

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

QUnit.skip( 'addSelectionPlayer(): mouse up hides selection player when text is not selected', () => {
	sinon.stub( ui, 'isShown' ).returns( true );
	ui.addSelectionPlayer();
	selectionPlayer.isSelectionValid.returns( false );
	sinon.spy( ui.selectionPlayer, 'toggle' );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when start of selection is not in an utterance node', function () {
	mw.wikispeech.test.util.setContentHtml(
		'<del>Not an utterance.</del> An utterance.'
	);
	const notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
	const utteranceNode = $( contentSelector ).contents().get( 1 );
	sinon.stub( ui, 'isShown' ).returns( true );
	ui.addSelectionPlayer();
	sinon.spy( ui.selectionPlayer, 'toggle' );
	this.stubGetSelection( notUtteranceNode, utteranceNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when end of selection is not in an utterance node', function () {
	mw.wikispeech.test.util.setContentHtml(
		'An utterance. <del>Not an utterance.</del>'
	);
	const notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
	const utteranceNode = $( contentSelector ).contents().get( 0 );
	sinon.stub( ui, 'isShown' ).returns( true );
	ui.addSelectionPlayer();
	sinon.spy( ui.selectionPlayer, 'toggle' );
	this.stubGetSelection( utteranceNode, notUtteranceNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): do not show if UI is hidden', function () {
	mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
	const textNode = $( contentSelector ).contents().get( 0 );
	sinon.stub( ui, 'isShown' ).returns( false );
	ui.addSelectionPlayer();
	selectionPlayer.isSelectionValid.returns( true );
	sinon.spy( ui.selectionPlayer, 'toggle' );
	this.stubGetSelection( textNode, textNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
} );

QUnit.skip( 'addSelectionPlayer(): hide selection player initially', ( assert ) => {
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
	event.ctrlKey = modifiers.includes( 'c' );
	event.altKey = modifiers.includes( 'a' );
	event.shiftKey = modifiers.includes( 's' );
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

QUnit.skip( 'Pressing keyboard shortcut for play/stop', ( assert ) => {
	testKeyboardShortcut( assert, 'playOrStop', 32, 'c' );
} );

QUnit.skip( 'Pressing keyboard shortcut for skipping ahead sentence', ( assert ) => {
	testKeyboardShortcut( assert, 'skipAheadUtterance', 39, 'c' );
} );

QUnit.skip( 'Pressing keyboard shortcut for skipping back sentence', ( assert ) => {
	testKeyboardShortcut( assert, 'skipBackUtterance', 37, 'c' );
} );

QUnit.skip( 'Pressing keyboard shortcut for skipping ahead word', ( assert ) => {
	testKeyboardShortcut( assert, 'skipAheadToken', 40, 'c' );
} );

QUnit.skip( 'Pressing keyboard shortcut for skipping back word', ( assert ) => {
	testKeyboardShortcut( assert, 'skipBackToken', 38, 'c' );
} );

QUnit.skip( 'toggleVisibility(): hide', () => {
	ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
	ui.selectionPlayer = sinon.stub( new OO.ui.ButtonWidget() );
	ui.$playerFooter = sinon.stub( $( '<div>' ) );
	sinon.stub( ui, 'isShown' ).returns( true );

	ui.toggleVisibility();

	sinon.assert.calledWith( ui.toolbar.toggle, false );
	sinon.assert.calledWith( ui.selectionPlayer.toggle, false );
	sinon.assert.called( ui.$playerFooter.hide );
} );

QUnit.skip( 'toggleVisibility(): show', () => {
	ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
	ui.selectionPlayer = sinon.stub( new OO.ui.ButtonWidget() );
	ui.$playerFooter = sinon.stub( $( '<div>' ) );
	sinon.stub( ui, 'isShown' ).returns( false );

	ui.toggleVisibility();

	sinon.assert.calledWith( ui.toolbar.toggle, true );
	sinon.assert.calledWith( ui.selectionPlayer.toggle, true );
	sinon.assert.called( ui.$playerFooter.show );
} );
