const Ui = require( 'ext.wikispeech/ext.wikispeech.ui.js' );
const Player = require( 'ext.wikispeech/ext.wikispeech.player.js' );
const SelectionPlayer = require( 'ext.wikispeech/ext.wikispeech.selectionPlayer.js' );
const util = require( './ext.wikispeech.test.util.js' );

QUnit.module( 'ext.wikispeech.ui', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.ui = new Ui();
		this.ui.player = sinon.createStubInstance( Player );
		this.ui.selectionPlayer = sinon.stub( new SelectionPlayer() );
		$( '#qunit-fixture' ).append(
			$( '<div>' ).attr( 'id', 'content' ),
			$( '<div>' ).attr( 'id', 'footer' )
		);
		sinon.stub( this.ui, 'isShown' ).returns( true );
		this.contentSelector = '#mw-content-text';
		mw.config.set( 'wgWikispeechContentSelector', this.contentSelector );
		this.$content = util.setContentHtml( 'Some text.' );

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
		this.$content.off( 'click' );
		$( '#qunit-fixture' ).empty();
		$( '.ext-wikispeech-control-panel, .ext-wikispeech-selection-player' ).remove();
	}
} ) );

QUnit.test( 'addControlPanel(): adds help menu item if page is set', function ( assert ) {
	const done = assert.async();

	mw.config.set( 'wgArticlePath', '/wiki/$1' );
	mw.config.set( 'wgWikispeechHelpPage', 'Help' );

	sinon.stub( this.ui, 'addBufferingIcon' );
	const addMenuItemStub = sinon.stub( this.ui, 'addMenuItem' );

	const deferred = $.Deferred();
	sinon.stub( mw.Api.prototype, 'getUserInfo' ).returns( deferred.promise() );

	this.ui.addControlPanel();
	deferred.resolve( { rights: [] } );

	deferred.done( () => {
		assert.strictEqual( addMenuItemStub.called, true, 'addMenuItem called' );

		const helpItem = addMenuItemStub
			.getCalls()
			.map( ( i ) => i.args[ 0 ] )
			.find( ( item ) => item.icon === 'help' );

		assert.notStrictEqual( helpItem, undefined, 'Help menu item exists' );
		assert.strictEqual( helpItem.url.includes( 'Help' ), true, 'URL includes Help' );

		mw.Api.prototype.getUserInfo.restore();
		this.ui.addMenuItem.restore();
		done();
	} );
} );

QUnit.test( 'addControlPanel(): adds feedback menu item if page is set', function ( assert ) {
	const done = assert.async();
	mw.config.set( 'wgArticlePath', '/wiki/$1' );
	mw.config.set( 'wgWikispeechFeedbackPage', 'Feedback' );
	sinon.stub( this.ui, 'addBufferingIcon' );

	const addMenuItemStub = sinon.stub( this.ui, 'addMenuItem' );
	const deferred = $.Deferred();
	sinon.stub( mw.Api.prototype, 'getUserInfo' ).returns( deferred.promise() );

	this.ui.addControlPanel();
	deferred.resolve( { rights: [] } );

	deferred.then( () => {
		assert.strictEqual( addMenuItemStub.called, true, 'addMenuItem called' );

		const feedbackItem = addMenuItemStub
			.getCalls()
			.map( ( i ) => i.args[ 0 ] )
			.find( ( item ) => item.icon === 'feedback' );

		assert.notStrictEqual( feedbackItem, undefined, 'Feedback menu item exists' );

		mw.Api.prototype.getUserInfo.restore();
		this.ui.addMenuItem.restore();
		done();
	} );
} );

QUnit.test( 'createEditButton(): returns menu item with local URL', function ( assert ) {
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	mw.config.set( 'wgScript', '/wiki/index.php' );

	const item = this.ui.createEditButton();

	assert.strictEqual(
		item.url,
		'/wiki/index.php?title=Special%3AEditLexicon&language=en&page=1',
		{
			title: mw.msg( 'wikispeech-edit-lexicon-btn' ),
			icon: 'edit',
			id: 'wikispeech-edit'
		}
	);

} );

QUnit.test( 'createEditButton(): add edit button with link to given script URL', function ( assert ) {
	mw.config.set( 'wgWikispeechAllowConsumerEdits', true );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );

	const item = this.ui.createEditButton( 'http://producer.url/w/index.php' );

	assert.strictEqual(
		item.url,
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

QUnit.test( 'createEditButton(): add edit button with link to given script URL, with consumerUrl parameter', function ( assert ) {
	mw.config.set( 'wgWikispeechAllowConsumerEdits', true );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgArticleId', 1 );
	mw.config.set( 'wgScriptPath', '/' );

	const scriptUrl = 'http://producer.url/w/index.php';
	const consumerUrl = window.location.origin + '/';

	const item = this.ui.createEditButton( scriptUrl, consumerUrl );
	const expectedUrl = scriptUrl + '?' + new URLSearchParams( {
		title: 'Special:EditLexicon',
		language: 'en',
		page: 1,
		consumerUrl: consumerUrl
	} ).toString();

	assert.strictEqual(
		item.url,
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
	const mockUtterance = { audio: { readyState: 0 } };

	this.ui.showBufferingIconIfAudioIsLoading( mockUtterance );

	sinon.assert.called( this.ui.$bufferingIcons.show );
} );

QUnit.test( 'showBufferingIconIfAudioIsLoading(): already loaded', function () {
	this.ui.$bufferingIcons = sinon.stub( $( '<div>' ) );
	const mockUtterance = { audio: { readyState: 2 } };

	this.ui.showBufferingIconIfAudioIsLoading( mockUtterance );

	sinon.assert.notCalled( this.ui.$bufferingIcons.show );
} );

QUnit.test( 'addSelectionPlayer(): mouse up shows selection player', function () {
	util.setContentHtml( 'LTR text.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.ui.selectionPlayer.isSelectionValid.returns( true );
	this.ui.playPauseButton = sinon.stub( new OO.ui.ButtonWidget() );
	this.stubGetSelection( textNode, textNode, { right: 50, bottom: 10 } );
	this.ui.addSelectionPlayer();
	this.ui.selectionPlayerUi.$element.width( 30 );
	sinon.spy( this.ui.selectionPlayerUi.$element, 'css' );
	sinon.spy( this.ui.selectionPlayerUi, 'toggle' );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, true );
	sinon.assert.calledWith(
		this.ui.selectionPlayerUi.$element.css,
		{
			left: '20px',
			top: 10 + $( document ).scrollTop() + 'px'
		}
	);
} );

QUnit.test( 'addSelectionPlayer(): mouse up shows focus player', function () {
	const $content = util.setContentHtml( 'LTR text.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.stubGetSelection( textNode, textNode, { left: 20, top: 30 } );
	this.ui.selectionPlayer.isSelectionValid.returns( false );
	this.ui.selectionPlayer.getFocus = sinon.stub().returns( true );
	this.ui.playPauseButton = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.addSelectionPlayer();
	this.ui.selectionPlayerUi.$element.height( 20 );
	sinon.spy( this.ui.selectionPlayerUi.$element, 'css' );
	sinon.spy( this.ui.selectionPlayerUi, 'toggle' );
	const event = $.Event( 'mouseup' );
	event.target = $content.get( 0 );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, true );
	sinon.assert.calledWith(
		this.ui.selectionPlayerUi.$element.css,
		{
			left: '20px',
			top: 10 + $( document ).scrollTop() + 'px'
		}
	);
} );

QUnit.test( 'addSelectionPlayer(): mouse up shows selection player, RTL', function () {
	util.setContentHtml(
		'<b style="direction: rtl">RTL text.</b>'
	);
	const textNode = $( this.contentSelector + ' b' ).contents().get( 0 );
	this.ui.selectionPlayer.isSelectionValid.returns( true );
	this.ui.playPauseButton = sinon.stub( new OO.ui.ButtonWidget() );
	this.stubGetSelection( textNode, textNode, { left: 15, bottom: 10 } );
	this.ui.addSelectionPlayer();
	sinon.spy( this.ui.selectionPlayerUi.$element, 'css' );
	sinon.spy( this.ui.selectionPlayerUi, 'toggle' );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, true );
	sinon.assert.calledWith(
		this.ui.selectionPlayerUi.$element.css,
		{
			left: '15px',
			top: 10 + $( document ).scrollTop() + 'px'
		}
	);
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when start of selection is not in an utterance node', function () {
	util.setContentHtml(
		'<del>Not an utterance.</del> An utterance.'
	);
	const notUtteranceNode = $( this.contentSelector + ' del' ).contents().get( 0 );
	const utteranceNode = $( this.contentSelector ).contents().get( 1 );
	this.ui.playPauseButton = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.addSelectionPlayer();
	sinon.spy( this.ui.selectionPlayerUi, 'toggle' );
	this.stubGetSelection( notUtteranceNode, utteranceNode );
	const event = new MouseEvent( 'mouseup' );

	document.dispatchEvent( event );

	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when end of selection is not in an utterance node', function () {
	util.setContentHtml(
		'An utterance. <del>Not an utterance.</del>'
	);
	const notUtteranceNode = $( this.contentSelector + ' del' ).contents().get( 0 );
	const utteranceNode = $( this.contentSelector ).contents().get( 0 );
	this.ui.playPauseButton = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.addSelectionPlayer();
	sinon.spy( this.ui.selectionPlayerUi, 'toggle' );
	this.stubGetSelection( utteranceNode, notUtteranceNode );
	const event = new MouseEvent( 'mouseup' );

	document.dispatchEvent( event );

	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, false );
} );

QUnit.test( 'addSelectionPlayer(): do not show if UI is hidden', function () {
	util.setContentHtml( 'LTR text.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.ui.isShown.returns( false );
	this.ui.playPauseButton = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.addSelectionPlayer();
	this.ui.selectionPlayer.isSelectionValid.returns( true );
	sinon.spy( this.ui.selectionPlayerUi, 'toggle' );
	this.stubGetSelection( textNode, textNode );
	const event = $.Event( 'mouseup' );

	$( document ).triggerHandler( event );

	sinon.assert.neverCalledWith( this.ui.selectionPlayerUi.toggle, true );
} );

QUnit.test( 'addSelectionPlayer(): hide selection player initially', function ( assert ) {
	this.ui.addSelectionPlayer();

	assert.false( this.ui.selectionPlayerUi.isVisible() );
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
	this.ui.selectionPlayerUi = sinon.stub( new OO.ui.ButtonGroupWidget() );
	this.ui.$playerFooter = sinon.stub( $( '<div>' ) );

	this.ui.toggleVisibility();

	sinon.assert.calledWith( this.ui.toolbar.toggle, false );
	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, false );
	sinon.assert.called( this.ui.$playerFooter.hide );
} );

QUnit.test( 'toggleVisibility(): show', function () {
	this.ui.toolbar = sinon.stub( new OO.ui.Toolbar() );
	this.ui.selectionPlayerUi = sinon.stub( new OO.ui.ButtonWidget() );
	this.ui.$playerFooter = sinon.stub( $( '<div>' ) );
	this.ui.isShown.returns( false );

	this.ui.toggleVisibility();

	sinon.assert.calledWith( this.ui.toolbar.toggle, true );
	sinon.assert.calledWith( this.ui.selectionPlayerUi.toggle, true );
	sinon.assert.called( this.ui.$playerFooter.show );
} );
