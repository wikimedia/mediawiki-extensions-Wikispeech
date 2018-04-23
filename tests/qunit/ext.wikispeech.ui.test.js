( function ( mw, $ ) {
	var sandbox, contentSelector, selectionPlayer, player, ui;

	QUnit.module( 'ext.wikispeech.ui', {
		setup: function () {
			mw.wikispeech.player = sinon.stub( new mw.wikispeech.Player() );
			player = mw.wikispeech.player;
			mw.wikispeech.selectionPlayer =
				sinon.stub( new mw.wikispeech.SelectionPlayer() );
			selectionPlayer = mw.wikispeech.selectionPlayer;
			ui = new mw.wikispeech.Ui();
			$( '#qunit-fixture' ).append(
				$( '<div></div>' ).attr( 'id', 'content' )
			);
			contentSelector =
				mw.config.get( 'wgWikispeechContentSelector' );
			sandbox = sinon.sandbox.create();
		},
		teardown: function () {
			sandbox.restore();
			// Remove the event listeners to not trigger them after
			// the tests have run.
			$( document ).off( 'mouseup' );
			$( '#qunit-fixture' ).empty();
		}
	} );

	QUnit.test( 'addControlPanel()', function ( assert ) {
		assert.expect( 5 );

		ui.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-play-stop-button' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-ahead-sentence' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-back-sentence' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-ahead-word' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-skip-back-word' ).length,
			1
		);
	} );

	QUnit.test( 'addControlPanel(): add help button if page is set', function ( assert ) {
		assert.expect( 2 );
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechHelpPage', 'Help' );
		ui.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-help' ).length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-help' )
				.parent()
				.attr( 'href' ),
			'/wiki/Help'
		);
	} );

	QUnit.test( 'addControlPanel(): do not add help button if page is not set', function ( assert ) {
		assert.expect( 1 );
		mw.config.set( 'wgWikispeechHelpPage', null );

		ui.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel #ext-wikispeech-help' ).length,
			0
		);
	} );

	QUnit.test( 'addControlPanel(): add feedback button', function ( assert ) {
		assert.expect( 2 );
		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgWikispeechFeedbackPage', 'Feedback' );

		ui.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-feedback' )
				.length,
			1
		);
		assert.strictEqual(
			$( '#ext-wikispeech-control-panel .ext-wikispeech-feedback' )
				.parent()
				.attr( 'href' ),
			'/wiki/Feedback'
		);
	} );

	QUnit.test( 'addControlPanel(): do not add feedback button if page is not set', function ( assert ) {
		assert.expect( 1 );
		mw.config.set( 'wgWikispeechFeedbackPage', null );

		ui.addControlPanel();

		assert.strictEqual(
			$( '#ext-wikispeech-control-panel #ext-wikispeech-feedback' )
				.length,
			0
		);
	} );

	QUnit.test( 'addStackToPlayStopButton()', function ( assert ) {
		assert.expect( 2 );
		ui.addControlPanel();

		ui.addStackToPlayStopButton();

		assert.strictEqual(
			$( '.ext-wikispeech-play-stop-button .ext-wikispeech-play-stop-stack' ).length,
			1
		);
		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'hideBufferingIcon()', function ( assert ) {
		assert.expect( 1 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();
		$( '.ext-wikispeech-buffering-icon' ).css( 'visibility', 'visible' );

		ui.hideBufferingIcon();

		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'showBufferingIconIfAudioIsLoading()', function ( assert ) {
		var mockAudio;

		assert.expect( 1 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();
		mockAudio = { readyState: 0 };

		ui.showBufferingIconIfAudioIsLoading( mockAudio );

		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
	} );

	QUnit.test( 'showBufferingIconIfAudioIsLoading(): already loaded', function ( assert ) {
		var mockAudio;

		assert.expect( 1 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();
		mockAudio = { readyState: 2 };

		ui.showBufferingIconIfAudioIsLoading( mockAudio );

		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'addCanPlayListener()', function ( assert ) {
		var $audio;

		assert.expect( 1 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();
		$( '.ext-wikispeech-buffering-icon' ).css( 'visibility', 'visible' );
		$audio = $( '<audio></audio>' );
		ui.addCanPlayListener( $audio );

		$audio.trigger( 'canplay' );

		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'removeCanPlayListener()', function ( assert ) {
		var $audio;

		assert.expect( 1 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();
		$audio = $( '<audio></audio>' );
		$( '.ext-wikispeech-buffering-icon' ).css( 'visibility', 'visible' );
		ui.addCanPlayListener( $audio );
		ui.removeCanPlayListener( $audio );

		$audio.trigger( 'canplay' );

		assert.strictEqual(
			$( '.ext-wikispeech-buffering-icon' ).css( 'visibility' ),
			'visible'
		);
	} );

	QUnit.test( 'setPlayStopIconToStop()', function ( assert ) {
		assert.expect( 2 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();

		ui.setPlayStopIconToStop();

		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' ).hasClass( 'ext-wikispeech-stop' ),
			true
		);
		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' ).hasClass( 'ext-wikispeech-play' ),
			false
		);
	} );

	QUnit.test( 'setPlayStopIconToPlay()', function ( assert ) {
		assert.expect( 2 );
		ui.addControlPanel();
		ui.addStackToPlayStopButton();
		$( '.ext-wikispeech-play-stop' ).addClass( 'ext-wikispeech-stop' );
		$( '.ext-wikispeech-play-stop' )
			.removeClass( 'ext-wikispeech-play' );

		ui.setPlayStopIconToPlay();

		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' ).hasClass( 'ext-wikispeech-play' ),
			true
		);
		assert.strictEqual(
			$( '.ext-wikispeech-play-stop' ).hasClass( 'ext-wikispeech-stop' ),
			false
		);
	} );

	/**
	 * Test that clicking a button calls the correct function.
	 *
	 * @param {QUnit.assert} assert
	 * @param {Object} object The object on which to call the function.
	 * @param {string} functionName Name of the function that should
	 *  be called.
	 * @param {string} buttonSelector Id of the button that is clicked.
	 */
	function testClickButton(
		assert,
		object,
		functionName,
		buttonSelector
	) {
		assert.expect( 1 );
		ui.addControlPanel();

		$( buttonSelector ).click();

		assert.strictEqual( object[ functionName ].called, true );
	}

	QUnit.test( 'Clicking play/stop button', function ( assert ) {
		testClickButton(
			assert,
			player,
			'playOrStop',
			'.ext-wikispeech-play-stop-button'
		);
	} );

	QUnit.test( 'Clicking skip ahead sentence button', function ( assert ) {
		testClickButton(
			assert,
			player,
			'skipAheadUtterance',
			'.ext-wikispeech-skip-ahead-sentence'
		);
	} );

	QUnit.test( 'Clicking skip back sentence button', function ( assert ) {
		testClickButton(
			assert,
			player,
			'skipBackUtterance',
			'.ext-wikispeech-skip-back-sentence'
		);
	} );

	QUnit.test( 'Clicking skip ahead word button', function ( assert ) {
		testClickButton(
			assert,
			player,
			'skipAheadToken',
			'.ext-wikispeech-skip-ahead-word'
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up shows selection player', function ( assert ) {
		var textNode, expectedLeft, event;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
		textNode = $( contentSelector ).contents().get( 0 );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( true );
		sandbox.stub( window, 'getSelection' ).returns( {
			rangeCount: 1,
			getRangeAt: function () {
				return {
					getClientRects: function () {
						return [ {
							right: 15,
							bottom: 10
						} ];
					},
					startContainer: textNode,
					endContainer: textNode
				};
			}
		} );
		expectedLeft =
			15 -
			$( '.ext-wikispeech-selection-player' ).width() +
			$( document ).scrollLeft();
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'visibility' ),
			'visible'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'left' ),
			expectedLeft + 'px'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'top' ),
			10 + $( document ).scrollTop() + 'px'
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up shows selection player, RTL', function ( assert ) {
		var textNode, event;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml(
			'<b style="direction: rtl">RTL text.</b>'
		);
		textNode = $( contentSelector + ' b' ).contents().get( 0 );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( true );
		sandbox.stub( window, 'getSelection' ).returns( {
			rangeCount: 1,
			getRangeAt: function () {
				return {
					getClientRects: function () {
						return [ {
							left: 15,
							bottom: 10
						} ];
					},
					startContainer: textNode,
					endContainer: textNode
				};
			}
		} );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'visibility' ),
			'visible'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'left' ),
			'15px'
		);
		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'top' ),
			10 + $( document ).scrollTop() + 'px'
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when text is not selected', function ( assert ) {
		var event;

		assert.expect( 1 );
		ui.addSelectionPlayer();
		selectionPlayer.isSelectionValid.returns( false );
		$( '.ext-wikispeech-selection-player' ).css( 'visibility', 'visible' );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when start of selection is not in an utterance node', function ( assert ) {
		var notUtteranceNode, utteranceNode, event;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<del>Not an utterance.</del> An utterance.'
		);
		notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
		utteranceNode = $( contentSelector ).contents().get( 1 );
		ui.addSelectionPlayer();
		sandbox.stub( window, 'getSelection' ).returns( {
			rangeCount: 1,
			getRangeAt: function () {
				return {
					startContainer: notUtteranceNode,
					endContainer: utteranceNode
				};
			}
		} );
		$( '.ext-wikispeech-selection-player' ).css( 'visibility', 'visible' );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'addSelectionPlayer(): mouse up hides selection player when end of selection is not in an utterance node', function ( assert ) {
		var notUtteranceNode, utteranceNode, event;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'An utterance. <del>Not an utterance.</del>'
		);
		notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
		utteranceNode = $( contentSelector ).contents().get( 0 );
		ui.addSelectionPlayer();
		sandbox.stub( window, 'getSelection' ).returns( {
			rangeCount: 1,
			getRangeAt: function () {
				return {
					startContainer: utteranceNode,
					endContainer: notUtteranceNode
				};
			}
		} );
		$( '.ext-wikispeech-selection-player' ).css( 'visibility', 'visible' );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'visibility' ),
			'hidden'
		);
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
		assert.expect( 1 );
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

	QUnit.test( 'Pressing keyboard shortcut for play/stop', function ( assert ) {
		testKeyboardShortcut( assert, 'playOrStop', 32, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping ahead sentence', function ( assert ) {
		testKeyboardShortcut( assert, 'skipAheadUtterance', 39, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping back sentence', function ( assert ) {
		testKeyboardShortcut( assert, 'skipBackUtterance', 37, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping ahead word', function ( assert ) {
		testKeyboardShortcut( assert, 'skipAheadToken', 40, 'c' );
	} );

	QUnit.test( 'Pressing keyboard shortcut for skipping back word', function ( assert ) {
		testKeyboardShortcut( assert, 'skipBackToken', 38, 'c' );
	} );
}( mediaWiki, jQuery ) );
