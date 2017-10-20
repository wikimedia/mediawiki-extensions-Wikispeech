( function ( mw, $ ) {
	/**
	 * The player that appears when the user selects a bit of text.
	 *
	 * Includes logic for finding what to play, and starting and
	 * stopping within an utterance.
	 *
	 * @class ext.wikispeech.test.SelectionPlayer
	 * @constructor
	 */

	var utterances, sandbox, contentSelector;

	QUnit.module( 'ext.wikispeech.selectionPlayer', {
		setup: function () {
			mw.wikispeech.selectionPlayer =
				new mw.wikispeech.SelectionPlayer();
			mw.wikispeech.wikispeech = {
				playUtterance: sinon.spy(),
				// Call callback right away, essentially assuming that
				// the utterance has been prepared.
				prepareUtterance: sinon.stub().callsArg( 1 ),
				// Add function to be stubbed.
				stop: function () {}
			};
			sinon.stub(
				mw.wikispeech.wikispeech,
				'stop',
				function () {
					utterances[ 0 ].audio.pause();
				}
			);
			sandbox = sinon.sandbox.create();
			$( '#qunit-fixture' ).append(
				$( '<div></div>' ).attr( 'id', 'content' )
			);
			contentSelector =
				mw.config.get( 'wgWikispeechContentSelector' );
			utterances = [
				{
					audio: $( '<audio></audio>' ).get( 0 ),
					startOffset: 0,
					endOffset: 14,
					content: [ { string: 'Utterance zero.' } ]
				},
				{
					audio: $( '<audio></audio>' ).get( 0 ),
					content: [ { string: 'Utterance one.' } ]
				}
			];
			mw.wikispeech.wikispeech.utterances = utterances;
		},
		teardown: function () {
			sandbox.restore();
			// Remove the event listeners to not trigger them after
			// the tests have run.
			$( document ).off( 'mouseup' );
		}
	} );

	/**
	 * Create a mocked `Selection`.
	 *
	 * This is used to mock user selecting text. To create a selection
	 * that starts and end in the same text node, the third argument
	 * is the end offset. To create a selection that ends in a
	 * different text node, the third argument is the end node and the
	 * fourth is the end offset.
	 *
	 * Note that the end offset of the resulting `Selection` object is
	 * the position after the supplied end offset parameter. This is
	 * because the visual selection reaches up to but not including
	 * the end offset.
	 *
	 * @param {number} startOffset The offset of selection start.
	 * @param {HTMLElement} startContainer The node where selection starts.
	 * @param {number} endOffset The offset of selection end.
	 * @param {HTMLElement} [endContainer] The node where selection
	 *  ends. If omitted, this will be the same as `startContainer`.
	 * @return {Object} A mocked `Selection` object.
	 */

	function createMockedSelection(
		startOffset,
		startContainer,
		endOffset,
		endContainer
	) {
		endContainer = endContainer || startContainer;
		return {
			rangeCount: 1,
			getRangeAt: function () {
				return {
					startContainer: startContainer,
					startOffset: startOffset,
					endContainer: endContainer,
					endOffset: endOffset + 1
				};
			}
		};
	}

	QUnit.test( 'playSelection()', function ( assert ) {
		var textNode, selection;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with selected text.'
			//              [-----------]
			// The line above shows what part of the text that is
			// selected. The selection includes the characters above
			// the brackets and everything in between.
		);
		textNode = $( contentSelector ).contents().get( 0 );
		selection = createMockedSelection( 15, textNode, 27 );
		sandbox.stub( window, 'getSelection' ).returns( selection );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].endOffset = 28;
		utterances[ 0 ].content = [ {
			path: './text()'
		} ];
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startTime: 0.0,
				endTime: 1.0,
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'with',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startTime: 1.0,
				endTime: 2.0,
				startOffset: 10,
				endOffset: 13
			},
			{
				string: 'selected',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startTime: 2.0,
				endTime: 3.0,
				startOffset: 15,
				endOffset: 22
			},
			{
				string: 'text',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startTime: 3.0,
				endTime: 3.5,
				startOffset: 24,
				endOffset: 27
			}
		];
		sinon.spy( mw.wikispeech.selectionPlayer, 'setStartTime' );
		sinon.spy( mw.wikispeech.selectionPlayer, 'setEndTime' );

		mw.wikispeech.selectionPlayer.playSelection();

		sinon.assert.calledWith(
			mw.wikispeech.wikispeech.playUtterance,
			utterances[ 0 ]
		);
		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setStartTime,
			utterances[ 0 ],
			2.0
		);
		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setEndTime,
			utterances[ 0 ],
			3.5
		);
	} );

	QUnit.test( 'playSelection(): multiple ranges', function ( assert ) {
		var selection, textNode1, textNode2;

		assert.expect( 2 );
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with selected <del>not selectable</del>text.'
			//              [-------]                         [--]
		);
		textNode1 = $( contentSelector ).contents().get( 0 );
		textNode2 = $( contentSelector ).contents().get( 2 );
		selection = {
			rangeCount: 2,
			getRangeAt: function ( index ) {
				if ( index === 0 ) {
					return {
						startContainer: textNode1,
						startOffset: 15,
						endContainer: textNode1,
						endOffset: 24
					};
				} else if ( index === 1 ) {
					return {
						startContainer: textNode2,
						startOffset: 0,
						endContainer: textNode2,
						endOffset: 4
					};
				}
			}
		};
		sandbox.stub( window, 'getSelection' ).returns( selection );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].content = [
			{ path: './text()[1]' },
			{ path: './b/text()' },
			{ path: './text()[2]' }
		];
		utterances[ 0 ].endOffset = 18;
		utterances[ 0 ].tokens = [
			{
				string: 'selected',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 15,
				endOffset: 22,
				startTime: 1.0
			},
			{
				string: 'text',
				items: [ utterances[ 0 ].content[ 2 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 3,
				endTime: 2.0
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 2 ] ],
				utterance: utterances[ 0 ],
				startOffset: 4,
				endOffset: 4,
				endTime: 3.0
			}
		];
		sinon.spy( mw.wikispeech.selectionPlayer, 'setStartTime' );
		sinon.spy( mw.wikispeech.selectionPlayer, 'setEndTime' );

		mw.wikispeech.selectionPlayer.playSelection();

		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setStartTime,
			utterances[ 0 ],
			1.0
		);
		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setEndTime,
			utterances[ 0 ],
			2.0
		);
	} );

	QUnit.test( 'playSelection(): spanning multiple nodes', function ( assert ) {
		var textNode1, textNode2, selection;

		assert.expect( 2 );
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with selected text <b>and </b>more selected text.'
			//              [-----------------------------------------]
		);
		textNode1 = $( contentSelector ).contents().get( 0 );
		textNode2 = $( contentSelector ).contents().get( 2 );
		selection = createMockedSelection( 15, textNode1, 17, textNode2 );
		sandbox.stub( window, 'getSelection' ).returns( selection );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].content = [
			{ path: './text()[1]' },
			{ path: './b/text()' },
			{ path: './text()[2]' }
		];
		utterances[ 0 ].endOffset = 18;
		utterances[ 0 ].tokens = [
			{
				string: 'selected',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 15,
				endOffset: 22,
				startTime: 1.0
			},
			{
				string: 'text',
				items: [ utterances[ 0 ].content[ 2 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 17,
				endTime: 2.0
			}
		];
		sinon.spy( mw.wikispeech.selectionPlayer, 'setStartTime' );
		sinon.spy( mw.wikispeech.selectionPlayer, 'setEndTime' );

		mw.wikispeech.selectionPlayer.playSelection();

		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setStartTime,
			utterances[ 0 ],
			1.0
		);
		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setEndTime,
			utterances[ 0 ],
			2.0
		);
	} );

	QUnit.test( 'playSelection(): selected nodes are elements', function ( assert ) {
		var parent, selection;

		assert.expect( 2 );
		mw.wikispeech.test.util.setContentHtml(
			'<b>Utterance zero.</b>'
			//  [-------------]
		);
		parent = $( contentSelector + ' b' ).get( 0 );
		selection = createMockedSelection( 0, parent, 1 );
		sandbox.stub( window, 'getSelection' ).returns( selection );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].content = [ { path: './b/text()' } ];
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8,
				startTime: 1.0
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 14,
				endTime: 2.0
			}
		];
		sinon.spy( mw.wikispeech.selectionPlayer, 'setStartTime' );
		sinon.spy( mw.wikispeech.selectionPlayer, 'setEndTime' );

		mw.wikispeech.selectionPlayer.playSelection();

		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setStartTime,
			utterances[ 0 ],
			1.0
		);
		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setEndTime,
			utterances[ 0 ],
			2.0
		);
	} );

	QUnit.test( 'playSelection(): selected nodes are elements that also contain non utterance nodes', function ( assert ) {
		var parent, selection;

		assert.expect( 2 );
		mw.wikispeech.test.util.setContentHtml(
			'<b>Not an utterance<br />Utterance zero.<br />Not an utterance</b>'
			//  [---------------------------------------------------------]
		);
		parent = $( contentSelector + ' b' ).get( 0 );
		selection = createMockedSelection( 0, parent, 1 );
		sandbox.stub( window, 'getSelection' ).returns( selection );
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].content = [ { path: './b/text()[2]' } ];
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8,
				startTime: 1.0
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 14,
				endTime: 2.0
			}
		];
		sinon.spy( mw.wikispeech.selectionPlayer, 'setStartTime' );
		sinon.spy( mw.wikispeech.selectionPlayer, 'setEndTime' );

		mw.wikispeech.selectionPlayer.playSelection();

		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setStartTime,
			utterances[ 0 ],
			1.0
		);
		sinon.assert.calledWith(
			mw.wikispeech.selectionPlayer.setEndTime,
			utterances[ 0 ],
			2.0
		);
	} );

	QUnit.test( 'playSelectionIfValid(): valid', function ( assert ) {
		var actualResult;

		assert.expect( 2 );
		sinon.stub( mw.wikispeech.selectionPlayer, 'isSelectionValid' )
			.returns( true );
		sinon.stub( mw.wikispeech.selectionPlayer, 'playSelection' );

		actualResult = mw.wikispeech.selectionPlayer.playSelectionIfValid();

		sinon.assert.called( mw.wikispeech.selectionPlayer.playSelection );
		assert.strictEqual( actualResult, true );
	} );

	QUnit.test( 'playSelectionIfValid(): invalid', function ( assert ) {
		var actualResult;

		assert.expect( 2 );
		sinon.stub( mw.wikispeech.selectionPlayer, 'isSelectionValid' )
			.returns( false );
		sinon.stub( mw.wikispeech.selectionPlayer, 'playSelection' );

		actualResult = mw.wikispeech.selectionPlayer.playSelectionIfValid();

		sinon.assert.notCalled( mw.wikispeech.selectionPlayer.playSelection );
		assert.strictEqual( actualResult, false );
	} );

	QUnit.test( 'setEndTime()', function ( assert ) {
		this.clock = sinon.useFakeTimers();

		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].tokens = [ {} ];
		utterances[ 0 ].audio.currentTime = 0.5;
		mw.wikispeech.selectionPlayer.setEndTime( utterances[ 0 ], 1.5 );
		$( utterances[ 0 ].audio ).trigger( 'playing' );

		this.clock.tick( 1000 );

		assert.strictEqual( utterances[ 0 ].audio.paused, true );
		this.clock.restore();
	} );

	QUnit.test( 'resetPreviousEndUtterance()', function ( assert ) {
		assert.expect( 1 );
		this.clock = sinon.useFakeTimers();
		mw.wikispeech.test.util.setContentHtml(
			'Utterance with selected text.'
		);
		utterances[ 0 ].audio.src = 'loaded';
		utterances[ 0 ].endOffset = 28;
		utterances[ 0 ].content = [ { path: './text()' } ];
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'with',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: 'selected',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 15,
				endOffset: 22,
				startTime: 2.0
			},
			{
				string: 'text',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 24,
				endOffset: 27,
				endTime: 4.0
			}
		];
		mw.wikispeech.selectionPlayer.setEndTime( utterances[ 0 ], 1.0 );
		mw.wikispeech.selectionPlayer.previousEndUtterance = utterances[ 0 ];
		mw.wikispeech.selectionPlayer.resetPreviousEndUtterance();
		utterances[ 0 ].audio.play();
		$( utterances[ 0 ].audio ).trigger( 'playing' );

		this.clock.tick( 1000 );

		assert.strictEqual( utterances[ 0 ].audio.paused, false );
		this.clock.restore();
	} );

	QUnit.test( 'addSelectionPlayer(): Mouse up shows selection player', function ( assert ) {
		var textNode, expectedLeft, event;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml( 'LTR text.' );
		utterances[ 0 ].content = [ { path: './text()' } ];
		textNode = $( contentSelector ).contents().get( 0 );
		mw.wikispeech.selectionPlayer.addSelectionPlayer();
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

	QUnit.test( 'AddSelectionPlayer(): Mouse up shows selection player, RTL', function ( assert ) {
		var textNode, event;

		assert.expect( 3 );
		mw.wikispeech.test.util.setContentHtml(
			'<b style="direction: rtl">RTL text.</b>'
		);
		utterances[ 0 ].content = [ { path: './b/text()' } ];
		textNode = $( contentSelector + ' b' ).contents().get( 0 );
		mw.wikispeech.selectionPlayer.addSelectionPlayer();
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

	QUnit.test( 'AddSelectionPlayer(): Mouse up hides selection player when text is not selected', function ( assert ) {
		var event;

		assert.expect( 1 );
		mw.wikispeech.selectionPlayer.addSelectionPlayer();
		sandbox.stub( window, 'getSelection' ).returns( {
			isCollapsed: true
		} );
		$( '.ext-wikispeech-selection-player' ).css( 'visibility', 'visible' );
		event = $.Event( 'mouseup' );

		$( document ).trigger( event );

		assert.strictEqual(
			$( '.ext-wikispeech-selection-player' ).css( 'visibility' ),
			'hidden'
		);
	} );

	QUnit.test( 'AddSelectionPlayer(): Mouse up hides selection player when start of selection is not in an utterance node', function ( assert ) {
		var notUtteranceNode, utteranceNode, event;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<del>Not an utterance.</del> An utterance.'
		);
		notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
		utteranceNode = $( contentSelector ).contents().get( 1 );
		utterances[ 0 ].content = [ { path: './text()' } ];
		mw.wikispeech.selectionPlayer.addSelectionPlayer();
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

	QUnit.test( 'AddSelectionPlayer(): Mouse up hides selection player when end of selection is not in an utterance node', function ( assert ) {
		var notUtteranceNode, utteranceNode, event;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'An utterance. <del>Not an utterance.</del>'
		);
		notUtteranceNode = $( contentSelector + ' del' ).contents().get( 0 );
		utteranceNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content = [ { path: './text()' } ];
		mw.wikispeech.selectionPlayer.addSelectionPlayer();
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

	QUnit.test( 'getFirstTextNode()', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a>first text node<br />other text node</a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getFirstTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getFirstTextNode(): deeper than other text node', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a><b>first text node</b>other text node</a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' b' ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getFirstTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastTextNode()', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a>other text node<br />last text node</a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 2 );

		actualNode =
			mw.wikispeech.selectionPlayer.getLastTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastTextNode(): deeper than other text node', function ( assert ) {
		var parentNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a>other text node<b>other text node</b></a>'
		);
		parentNode = $( contentSelector + ' a' ).get( 0 );
		expectedNode = $( contentSelector + ' b' ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getLastTextNode( parentNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getFirstNodeInSelection()', function ( assert ) {
		var expectedNode, selection, actualNode;
		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'before<selected>first text node</selected>after'
			//               [-------------]

		);
		expectedNode =
			$( contentSelector + ' selected' ).contents().get( 0 );
		selection = createMockedSelection( 0, expectedNode, 14 );
		sandbox.stub( window, 'getSelection' ).returns( selection );

		actualNode =
			mw.wikispeech.selectionPlayer.getFirstNodeInSelection();

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getFirstNodeInSelection(): start offset greater than max', function ( assert ) {
		var expectedNode, selection, previousNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'before<selected>first text node</selected>after'
			//               [-------------]
		);
		expectedNode =
			$( contentSelector + ' selected' ).get( 0 );
		previousNode =
			$( contentSelector ).contents().get( 0 );
		selection =
			createMockedSelection( 6, previousNode, 14, expectedNode );
		sandbox.stub( window, 'getSelection' ).returns( selection );

		actualNode =
			mw.wikispeech.selectionPlayer.getFirstNodeInSelection();

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getFirstNodeInSelection(): start offset greater than max, no sibling', function ( assert ) {
		var expectedNode, previousNode, selection, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a><b>before</b></a><selected>first text node</selected>after'
			//                             [-------------]

		);
		expectedNode =
			$( contentSelector + ' selected' ).get( 0 );
		previousNode =
			$( contentSelector + ' a b' ).contents().get( 0 );
		selection =
			createMockedSelection( 6, previousNode, 14, expectedNode );
		sandbox.stub( window, 'getSelection' ).returns( selection );

		actualNode =
			mw.wikispeech.selectionPlayer.getFirstNodeInSelection();

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastNodeInSelection()', function ( assert ) {
		var expectedNode, selection, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'before<selected>last text node</selected>after'
			//               [------------]

		);
		expectedNode =
			$( contentSelector + ' selected' ).contents().get( 0 );
		selection = createMockedSelection( 0, expectedNode, 13 );
		sandbox.stub( window, 'getSelection' ).returns( selection );

		actualNode =
			mw.wikispeech.selectionPlayer.getLastNodeInSelection();

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastNodeInSelection(): end offset is zero', function ( assert ) {
		var expectedNode, nextNode, selection, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'before<selected>last text node</selected>after'
			//               [------------]
		);
		expectedNode =
			$( contentSelector + ' selected' ).get( 0 );
		nextNode = $( contentSelector ).contents().get( 2 );
		selection =
			createMockedSelection( 0, expectedNode, -1, nextNode );
		sandbox.stub( window, 'getSelection' ).returns( selection );

		actualNode =
			mw.wikispeech.selectionPlayer.getLastNodeInSelection();

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getLastNodeInSelection(): end offset is zero, no sibling', function ( assert ) {
		var expectedNode, nextNode, selection, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'before<selected>last text node</selected><a><b>after</b></a>'
			//               [------------]

		);
		expectedNode = $( contentSelector + ' selected' ).get( 0 );
		nextNode = $( contentSelector + ' a b' ).contents().get( 0 );
		selection = createMockedSelection( 0, expectedNode, -1, nextNode );
		sandbox.stub( window, 'getSelection' ).returns( selection );

		actualNode = mw.wikispeech.selectionPlayer.getLastNodeInSelection();

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getStartUtterance()', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 1 ].content[ 0 ].path = './text()';
		utterances[ 1 ].startOffset = 16;
		utterances[ 1 ].endOffset = 29;
		utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 16;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getStartUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getStartUtterance(): offset between utterances', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 1 ].content[ 0 ].path = './text()';
		utterances[ 1 ].startOffset = 16;
		utterances[ 1 ].endOffset = 29;
		utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 15;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getStartUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getStartUtterance(): offset between utterances and next utterance in different node', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()[1]';
		utterances[ 1 ].content[ 0 ].path = './a/text()';
		utterances[ 1 ].startOffset = 0;
		utterances[ 1 ].endOffset = 13;
		utterances[ 2 ] = {
			content: [ { path: './text()[2]' } ],
			startOffset: 1,
			endOffset: 14
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. <a>Utterance one.</a> Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 15;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getStartUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance()', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 1 ].content[ 0 ].path = './text()';
		utterances[ 1 ].startOffset = 16;
		utterances[ 1 ].endOffset = 29;
		utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 16;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance(): offset between utterances', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 1 ].content[ 0 ].path = './text()';
		utterances[ 1 ].startOffset = 16;
		utterances[ 1 ].endOffset = 29;
		utterances[ 2 ] = {
			content: [ { path: './text()' } ],
			startOffset: 31,
			endOffset: 44
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. Utterance one. Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 0 );
		offset = 30;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance(): offset between utterances and previous utterance in different node', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()[1]';
		utterances[ 1 ].content[ 0 ].path = './a/text()';
		utterances[ 1 ].startOffset = 0;
		utterances[ 1 ].endOffset = 13;
		utterances[ 2 ] = {
			content: [ { path: './text()[2]' } ],
			startOffset: 1,
			endOffset: 14
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. <a>Utterance one.</a> Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 2 );
		offset = 0;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getEndUtterance(): offset between utterances and previous utterance in different node with other utterance', function ( assert ) {
		var textNode, offset, actualUtterance;

		assert.expect( 1 );
		utterances[ 0 ].content[ 0 ].path = './text()[1]';
		utterances[ 1 ].content[ 0 ].path = './text()[1]';
		utterances[ 1 ].startOffset = 16;
		utterances[ 1 ].endOffset = 29;
		utterances[ 2 ] = {
			content: [ { path: './text()[2]' } ],
			startOffset: 1,
			endOffset: 14
		};
		mw.wikispeech.test.util.setContentHtml(
			'Utterance zero. Utterance one.<br /> Utterance two.'
		);
		textNode = $( contentSelector ).contents().get( 2 );
		offset = 0;

		actualUtterance =
			mw.wikispeech.selectionPlayer.getEndUtterance(
				textNode,
				offset
			);

		assert.strictEqual( actualUtterance, utterances[ 1 ] );
	} );

	QUnit.test( 'getNextTextNode()', function ( assert ) {
		var originalNode, expectedNode, actualNode;
		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'original node<br />next node'
		);
		originalNode = $( contentSelector ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 2 );

		actualNode =
			mw.wikispeech.selectionPlayer.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getNextTextNode(): node is one level down', function ( assert ) {
		var originalNode, expectedNode, actualNode;
		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'original node<a>next node</a>'
		);
		originalNode = $( contentSelector ).contents().get( 0 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getNextTextNode(): node is one level up', function ( assert ) {
		var originalNode, expectedNode, actualNode;
		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a>original node</a>next node'
		);
		originalNode = $( contentSelector + ' a' ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 1 );

		actualNode =
			mw.wikispeech.selectionPlayer.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getNextTextNode(): node contains non text nodes', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'original node<a><!--comment--></a>next node'
		);
		originalNode = $( contentSelector ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 2 );

		actualNode =
			mw.wikispeech.selectionPlayer.getNextTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode()', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'previous node<br />original node'
		);
		originalNode = $( contentSelector ).contents().get( 2 );
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode(): node is one level down', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'<a>previous node</a>original node'
		);
		originalNode = $( contentSelector ).contents().get( 1 );
		expectedNode = $( contentSelector + ' a' ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode(): node is one level up', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'previous node<a>original node</a>'
		);
		originalNode = $( contentSelector + ' a' ).contents().get( 0 );
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getPreviousTextNode(): node contains non text nodes', function ( assert ) {
		var originalNode, expectedNode, actualNode;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml(
			'previous node<a><!--comment--></a>original node'
		);
		originalNode = $( contentSelector ).contents().get( 2 );
		expectedNode = $( contentSelector ).contents().get( 0 );

		actualNode =
			mw.wikispeech.selectionPlayer.getPreviousTextNode( originalNode );

		assert.strictEqual( actualNode, expectedNode );
	} );

	QUnit.test( 'getStartToken()', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			mw.wikispeech.selectionPlayer.getStartToken(
				utterances[ 0 ],
				textNode,
				0
			);

		assert.strictEqual( actualToken, utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getStartToken(): between tokens', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			mw.wikispeech.selectionPlayer.getStartToken(
				utterances[ 0 ],
				textNode,
				9
			);

		assert.strictEqual( actualToken, utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getStartToken(): in different node', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance <br />zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content[ 0 ].path = './text()[1]';
		utterances[ 0 ].content[ 1 ] = { path: './text()[2]' };
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 1 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 3
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 1 ] ],
				utterance: utterances[ 0 ],
				startOffset: 4,
				endOffset: 4
			}
		];

		actualToken =
			mw.wikispeech.selectionPlayer.getStartToken(
				utterances[ 0 ],
				textNode,
				9
			);

		assert.strictEqual( actualToken, utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getEndToken()', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			mw.wikispeech.selectionPlayer.getEndToken(
				utterances[ 0 ],
				textNode,
				10
			);

		assert.strictEqual( actualToken, utterances[ 0 ].tokens[ 1 ] );
	} );

	QUnit.test( 'getEndToken(): between tokens', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content[ 0 ].path = './text()';
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 14,
				endOffset: 14
			}
		];

		actualToken =
			mw.wikispeech.selectionPlayer.getEndToken(
				utterances[ 0 ],
				textNode,
				9
			);

		assert.strictEqual( actualToken, utterances[ 0 ].tokens[ 0 ] );
	} );

	QUnit.test( 'getEndToken(): in different node', function ( assert ) {
		var textNode, actualToken;

		assert.expect( 1 );
		mw.wikispeech.test.util.setContentHtml( 'Utterance<br /> zero.' );
		textNode = $( contentSelector ).contents().get( 0 );
		utterances[ 0 ].content[ 0 ].path = './text()[1]';
		utterances[ 0 ].content[ 1 ] = { path: './text()[2]' };
		utterances[ 0 ].tokens = [
			{
				string: 'Utterance',
				items: [ utterances[ 0 ].content[ 0 ] ],
				utterance: utterances[ 0 ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				items: [ utterances[ 0 ].content[ 1 ] ],
				utterance: utterances[ 0 ],
				startOffset: 1,
				endOffset: 4
			},
			{
				string: '.',
				items: [ utterances[ 0 ].content[ 1 ] ],
				utterance: utterances[ 0 ],
				startOffset: 5,
				endOffset: 5
			}
		];

		actualToken =
			mw.wikispeech.selectionPlayer.getEndToken(
				utterances[ 0 ],
				textNode,
				0
			);

		assert.strictEqual( actualToken, utterances[ 0 ].tokens[ 0 ] );
	} );
}( mediaWiki, jQuery ) );
