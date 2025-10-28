const Ui = require( 'ext.wikispeech/ext.wikispeech.ui.js' );
const Player = require( 'ext.wikispeech/ext.wikispeech.player.js' );
const SelectionPlayer = require( 'ext.wikispeech/ext.wikispeech.selectionPlayer.js' );
const util = require( './ext.wikispeech.test.util.js' );

QUnit.module( 'ext.wikispeech.ui', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.ui = new Ui();
		this.ui.player = sinon.createStubInstance( Player );
		this.selectionPlayer = sinon.stub( new SelectionPlayer() );
		this.ui.selectionPlayer = this.selectionPlayer;
		$( '#qunit-fixture' ).append(
			$( '<div>' ).attr( 'id', 'content' ),
			$( '<div>' ).attr( 'id', 'footer' )
		);
		this.contentSelector = '#mw-content-text';
		mw.config.set( 'wgWikispeechContentSelector', this.contentSelector );

		/**
		 * Stub window.getSelection
		 *
		 * @param {Node} startContainer Node where selection starts.
		 * @param {Node} endContainer Node where selection ends.
		 * @param {DOMRect} rect The selection rectangle.
		 */
		this.stubGetSelection = ( startContainer, endContainer, rect ) => {
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

QUnit.test( 'addControlPanel(): add help button if page is set', function ( assert ) {
	mw.config.set( 'wgArticlePath', '/wiki/$1' );
	mw.config.set( 'wgWikispeechHelpPage', 'Help' );
	sinon.stub( this.ui, 'addBufferingIcon' );

	this.ui.addControlPanel();

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
	sinon.stub( this.ui, 'addBufferingIcon' );

	this.ui.addControlPanel();

	assert.strictEqual(
		$( '.ext-wikispeech-control-panel' )
			.find( '.oo-ui-buttonElement-button[href="./Feedback"]' )
			.length,
		1
	);
} );

QUnit.test( 'addEditButton(): add edit button with link to local URL', function () {
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	mw.config.set( 'wgScript', '/wiki/index.php' );

	this.ui.linkGroup = sinon.stub( new OO.ui.ButtonGroupWidget() );
	const addButton = sinon.stub( this.ui, 'addButton' );

	this.ui.addEditButton();

	sinon.assert.calledWith(
		addButton,
		this.ui.linkGroup,
		// The colon in "Special:EditLexicon" is URL encoded, see:
		// https://url.spec.whatwg.org/#concept-urlencoded-serializer.
		'/wiki/index.php?title=Special%3AEditLexicon&language=en&page=1',
		{
			title: mw.msg( 'wikispeech-edit-lexicon-btn' ),
			icon: 'edit',
			id: 'wikispeech-edit'
		}
	);
} );

QUnit.test( 'addEditButton(): add edit button with link to given script URL', function () {
	mw.config.set( 'wgWikispeechAllowConsumerEdits', true );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	this.ui.linkGroup = sinon.stub( new OO.ui.ButtonGroupWidget() );
	const addButton = sinon.stub( this.ui, 'addButton' );

	this.ui.addEditButton( 'http://producer.url/w/index.php' );

	sinon.assert.calledWith(
		addButton,
		this.ui.linkGroup,
		// The colon in "Special:EditLexicon" is URL encoded, see:
		// https://url.spec.whatwg.org/#concept-urlencoded-serializer.
		'http://producer.url/w/index.php?title=Special%3AEditLexicon&language=en&page=1',
		{
			title: mw.msg( 'wikispeech-edit-lexicon-btn' ),
			icon: 'edit',
			id: 'wikispeech-edit'
		}
	);
} );

QUnit.test( 'addEditButton(): add edit button with link to given script URL, with consumerUrl parameter', function () {
	mw.config.set( 'wgWikispeechAllowConsumerEdits', true );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	mw.config.set( 'wgScriptPath', '/' );

	this.ui.linkGroup = this.sandbox.stub( new OO.ui.ButtonGroupWidget() );
	const addButton = this.sandbox.stub( this.ui, 'addButton' );

	const scriptUrl = 'http://producer.url/w/index.php';
	const consumerUrl = window.location.origin + '/';

	this.ui.addEditButton( scriptUrl, consumerUrl );
	const expectedUrl = scriptUrl + '?' + new URLSearchParams( {
		title: 'Special:EditLexicon',
		language: 'en',
		page: 1,
		consumerUrl: consumerUrl
	} ).toString();

	sinon.assert.calledWith(
		addButton,
		this.ui.linkGroup,
		expectedUrl,
		{
			title: mw.msg( 'wikispeech-edit-lexicon-btn' ),
			icon: 'edit',
			id: 'wikispeech-edit'
		}
	);
} );

QUnit.test( 'showBufferingIconIfAudioIsLoading()', function () {
	this.ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
	const mockAudio = { readyState: 0 };

	this.ui.showBufferingIconIfAudioIsLoading( mockAudio );

	sinon.assert.called( this.ui.$bufferingIcons.show );
} );

QUnit.test( 'showBufferingIconIfAudioIsLoading(): already loaded', function () {
	this.ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
	const mockAudio = { readyState: 2 };

	this.ui.showBufferingIconIfAudioIsLoading( mockAudio );

	sinon.assert.notCalled( this.ui.$bufferingIcons.show );
} );

QUnit.test( 'addSelectionPlayer(): mouse up shows selection player', function () {
	util.setContentHtml( 'LTR text.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.selectionPlayer.isSelectionValid.returns( true );
	this.stubGetSelection( textNode, textNode, { right: 50, bottom: 10 } );
	sinon.stub( this.ui, 'isShown' ).returns( true );
	this.ui.addSelectionPlayer();
	this.ui.selectionPlayer.button.$element.width( 30 );
	sinon.spy( this.ui.selectionPlayer.button.$element, 'css' );
	sinon.spy( this.ui.selectionPlayer.button, 'toggle' );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, true );
	sinon.assert.calledWith(
		this.ui.selectionPlayer.button.$element.css,
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
	util.setContentHtml(
		'<b style="direction: rtl">RTL text.</b>'
	);
	const textNode = $( this.contentSelector + ' b' ).contents().get( 0 );
	this.selectionPlayer.isSelectionValid.returns( true );
	this.stubGetSelection( textNode, textNode, { left: 15, bottom: 10 } );
	sinon.stub( this.ui, 'isShown' ).returns( true );
	this.ui.addSelectionPlayer();
	sinon.spy( this.ui.selectionPlayer.button.$element, 'css' );
	sinon.spy( this.ui.selectionPlayer.button, 'toggle' );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, true );
	sinon.assert.calledWith(
		this.ui.selectionPlayer.button.$element.css,
		{
			left: '15px',
			top: 10 + $( document ).scrollTop() + 'px'
		}
	);
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when text is not selected', function () {
	sinon.stub( this.ui, 'isShown' ).returns( true );
	this.ui.addSelectionPlayer();
	this.selectionPlayer.isSelectionValid.returns( false );
	sinon.spy( this.ui.selectionPlayer.button, 'toggle' );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when start of selection is not in an utterance node', function () {
	util.setContentHtml(
		'<del>Not an utterance.</del> An utterance.'
	);
	const notUtteranceNode = $( this.contentSelector + ' del' ).contents().get( 0 );
	const utteranceNode = $( this.contentSelector ).contents().get( 1 );
	sinon.stub( this.ui, 'isShown' ).returns( true );
	this.ui.addSelectionPlayer();
	sinon.spy( this.ui.selectionPlayer.button, 'toggle' );
	this.stubGetSelection( notUtteranceNode, utteranceNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when end of selection is not in an utterance node', function () {
	util.setContentHtml(
		'An utterance. <del>Not an utterance.</del>'
	);
	const notUtteranceNode = $( this.contentSelector + ' del' ).contents().get( 0 );
	const utteranceNode = $( this.contentSelector ).contents().get( 0 );
	sinon.stub( this.ui, 'isShown' ).returns( true );
	this.ui.addSelectionPlayer();
	sinon.spy( this.ui.selectionPlayer.button, 'toggle' );
	this.stubGetSelection( utteranceNode, notUtteranceNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): do not show if UI is hidden', function () {
	util.setContentHtml( 'LTR text.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	sinon.stub( this.ui, 'isShown' ).returns( false );
	this.ui.addSelectionPlayer();
	this.selectionPlayer.isSelectionValid.returns( true );
	sinon.spy( this.ui.selectionPlayer.button, 'toggle' );
	this.stubGetSelection( textNode, textNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): hide selection player initially', function ( assert ) {
	this.ui.addSelectionPlayer();

	assert.false( this.ui.selectionPlayer.button.isVisible() );
} );

QUnit.test( 'showLoadAudioError(): plays and stops the error audio', function ( assert ) {
	const done = assert.async();
	const audioMock = {
		play: sinon.stub(),
		pause: sinon.stub(),
		currentTime: 123
	};
	this.ui.errorAudio = audioMock;
	sinon.stub( this.ui, 'openWindow' ).resolves( { action: 'stop' } );
	this.ui.showLoadAudioError().then( () => {
		assert.strictEqual( audioMock.play.calledOnce, true );
		assert.strictEqual( audioMock.pause.calledOnce, true );
		assert.strictEqual( audioMock.currentTime, 0 );
		done();
	} );
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
			playPause: {
				key: 13,
				modifiers: [ 'ctrl' ]
			},
			stop: {
				key: 8,
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
	this.ui.addKeyboardShortcuts();

	$( document ).triggerHandler( createKeydownEvent( keyCode, modifiers ) );

	assert.strictEqual( this.ui.player[ functionName ].called, true );
}

QUnit.test( 'Pressing keyboard shortcut for play/pause', function ( assert ) {
	testKeyboardShortcut.call( this, assert, 'playOrPause', 13, 'c' );
} );

QUnit.test( 'Pressing keyboard shortcut for stop', function ( assert ) {
	testKeyboardShortcut.call( this, assert, 'stop', 8, 'c' );
} );

QUnit.test( 'Pressing keyboard shortcut for skipping ahead sentence', function ( assert ) {
	testKeyboardShortcut.call( this, assert, 'skipAheadUtterance', 39, 'c' );
} );

QUnit.test( 'Pressing keyboard shortcut for skipping back sentence', function ( assert ) {
	testKeyboardShortcut.call( this, assert, 'skipBackUtterance', 37, 'c' );
} );

QUnit.test( 'Pressing keyboard shortcut for skipping ahead word', function ( assert ) {
	testKeyboardShortcut.call( this, assert, 'skipAheadToken', 40, 'c' );
} );

QUnit.test( 'Pressing keyboard shortcut for skipping back word', function ( assert ) {
	testKeyboardShortcut.call( this, assert, 'skipBackToken', 38, 'c' );
} );

QUnit.test( 'toggleVisibility(): hide', function () {
	this.ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
	this.ui.selectionPlayer.button = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.$playerFooter = sinon.stub( $( '<div>' ) );
	sinon.stub( this.ui, 'isShown' ).returns( true );

	this.ui.toggleVisibility();

	sinon.assert.calledWith( this.ui.toolbar.toggle, false );
	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, false );
	sinon.assert.called( this.ui.$playerFooter.hide );
} );

QUnit.test( 'toggleVisibility(): show', function () {
	this.ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
	this.ui.selectionPlayer.button = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.$playerFooter = sinon.stub( $( '<div>' ) );
	sinon.stub( this.ui, 'isShown' ).returns( false );

	this.ui.toggleVisibility();

	sinon.assert.calledWith( this.ui.toolbar.toggle, true );
	sinon.assert.calledWith( this.ui.selectionPlayer.button.toggle, true );
	sinon.assert.called( this.ui.$playerFooter.show );
} );
