let sandbox, contentSelector, selectionPlayer, player, storage;

QUnit.module( 'ext.wikispeech.selectionPlayer', {
	beforeEach: function () {
		mw.wikispeech.storage =
			sinon.stub( new mw.wikispeech.Storage() );
		storage = mw.wikispeech.storage;
		storage.utterances = [
			{
				audio: $( '<audio>' ).get( 0 ),
				startOffset: 0,
				endOffset: 14,
				content: [ { string: 'Utterance zero.' } ]
			},
			{
				audio: $( '<audio>' ).get( 0 ),
				content: [ { string: 'Utterance one.' } ]
			}
		];
		// Assume that the utterance is already prepared,
		// resolve immediately.
		storage.prepareUtterance.returns( $.Deferred().resolve() );
		mw.wikispeech.player =
			sinon.stub( new mw.wikispeech.Player() );
		player = mw.wikispeech.player;
		selectionPlayer =
			new mw.wikispeech.SelectionPlayer();
		sandbox = sinon.sandbox.create();
		$( '#qunit-fixture' ).append(
			$( '<div>' ).attr( 'id', 'content' )
		);
		mw.config.set( 'wgWikispeechContentSelector', '#mw-content-text' );
		contentSelector =
			mw.config.get( 'wgWikispeechContentSelector' );
		this.clock = sinon.useFakeTimers();
	},
	afterEach: function () {
		sandbox.restore();
		// Remove the event listeners to not trigger them after
		// the tests have run.
		$( document ).off( 'mouseup' );
		this.clock.restore();
		mw.user.options.set( 'wikispeechSpeechRate', 1.0 );
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
 * Note: The end offset of the resulting `Selection` object is
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

QUnit.skip( 'playSelection()', () => {
	mw.wikispeech.test.util.setContentHtml(
		'Utterance with selected text.'
		//              [-----------]
		// The line above shows what part of the text that is
		// selected. The selection includes the characters above
		// the brackets and everything in between.
	);
	const textNode = $( contentSelector ).contents().get( 0 );
	const selection = createMockedSelection( 15, textNode, 27 );
	sandbox.stub( window, 'getSelection' ).returns( selection );
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].endOffset = 28;
	storage.utterances[ 0 ].content = [ {
		path: './text()'
	} ];
	storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startTime: 0,
			endTime: 1000,
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'with',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startTime: 1000,
			endTime: 2000,
			startOffset: 10,
			endOffset: 13
		},
		{
			string: 'selected',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startTime: 2000,
			endTime: 3000,
			startOffset: 15,
			endOffset: 22
		},
		{
			string: 'text',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startTime: 3000,
			endTime: 3500,
			startOffset: 24,
			endOffset: 27
		}
	];
	storage.getStartUtterance.returns( storage.utterances[ 0 ] );
	storage.getStartToken.returns( storage.utterances[ 0 ].tokens[ 2 ] );
	storage.getEndUtterance.returns( storage.utterances[ 0 ] );
	storage.getEndToken.returns( storage.utterances[ 0 ].tokens[ 3 ] );
	sinon.spy( selectionPlayer, 'setStartTime' );
	sinon.spy( selectionPlayer, 'setEndTime' );

	selectionPlayer.playSelection();

	sinon.assert.calledWith(
		player.playUtterance,
		storage.utterances[ 0 ]
	);
	sinon.assert.calledWith(
		selectionPlayer.setStartTime,
		storage.utterances[ 0 ],
		2000
	);
	sinon.assert.calledWith(
		selectionPlayer.setEndTime,
		storage.utterances[ 0 ],
		3500
	);
} );

QUnit.skip( 'playSelection(): multiple ranges', () => {
	mw.wikispeech.test.util.setContentHtml(
		'Utterance with selected <del>not selectable</del>text.'
		//              [-------]                         [--]
	);
	const textNode1 = $( contentSelector ).contents().get( 0 );
	const textNode2 = $( contentSelector ).contents().get( 2 );
	const selection = {
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
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].content = [
		{ path: './text()[1]' },
		{ path: './b/text()' },
		{ path: './text()[2]' }
	];
	storage.utterances[ 0 ].endOffset = 18;
	storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startTime: 0,
			endTime: 1000,
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'with',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startTime: 1000,
			endTime: 2000,
			startOffset: 10,
			endOffset: 13
		},
		{
			string: 'selected',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 15,
			endOffset: 22,
			startTime: 3000
		},
		{
			string: 'text',
			items: [ storage.utterances[ 0 ].content[ 2 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 3,
			endTime: 5000
		},
		{
			string: '.',
			items: [ storage.utterances[ 0 ].content[ 2 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 4,
			endOffset: 4,
			endTime: 3000
		}
	];
	storage.getStartUtterance.returns( storage.utterances[ 0 ] );
	storage.getStartToken.returns( storage.utterances[ 0 ].tokens[ 2 ] );
	storage.getEndUtterance.returns( storage.utterances[ 0 ] );
	storage.getEndToken.returns( storage.utterances[ 0 ].tokens[ 3 ] );
	sinon.spy( selectionPlayer, 'setStartTime' );
	sinon.spy( selectionPlayer, 'setEndTime' );

	selectionPlayer.playSelection();

	sinon.assert.calledWith(
		selectionPlayer.setStartTime,
		storage.utterances[ 0 ],
		3000
	);
	sinon.assert.calledWith(
		selectionPlayer.setEndTime,
		storage.utterances[ 0 ],
		5000
	);
} );

QUnit.skip( 'playSelection(): spanning multiple nodes', () => {
	mw.wikispeech.test.util.setContentHtml(
		'Utterance with selected text <b>and </b>more selected text.'
		//              [-----------------------------------------]
	);
	const textNode1 = $( contentSelector ).contents().get( 0 );
	const textNode2 = $( contentSelector ).contents().get( 2 );
	const selection = createMockedSelection( 15, textNode1, 17, textNode2 );
	sandbox.stub( window, 'getSelection' ).returns( selection );
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].content = [
		{ path: './text()[1]' },
		{ path: './b/text()' },
		{ path: './text()[2]' }
	];
	storage.utterances[ 0 ].endOffset = 18;
	storage.utterances[ 0 ].tokens = [
		{
			string: 'selected',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 15,
			endOffset: 22,
			startTime: 1000
		},
		{
			string: 'text',
			items: [ storage.utterances[ 0 ].content[ 2 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 17,
			endTime: 2000
		}
	];
	storage.getStartUtterance.returns( storage.utterances[ 0 ] );
	storage.getStartToken.returns( storage.utterances[ 0 ].tokens[ 0 ] );
	storage.getEndUtterance.returns( storage.utterances[ 0 ] );
	storage.getEndToken.returns( storage.utterances[ 0 ].tokens[ 1 ] );
	sinon.spy( selectionPlayer, 'setStartTime' );
	sinon.spy( selectionPlayer, 'setEndTime' );

	selectionPlayer.playSelection();

	sinon.assert.calledWith(
		selectionPlayer.setStartTime,
		storage.utterances[ 0 ],
		1000
	);
	sinon.assert.calledWith(
		selectionPlayer.setEndTime,
		storage.utterances[ 0 ],
		2000
	);
} );

QUnit.skip( 'playSelection(): selected nodes are elements', () => {
	mw.wikispeech.test.util.setContentHtml(
		'<b>Utterance zero.</b>'
		//  [-------------]
	);
	const parent = $( contentSelector + ' b' ).get( 0 );
	const selection = createMockedSelection( 0, parent, 1 );
	sandbox.stub( window, 'getSelection' ).returns( selection );
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].content = [ { path: './b/text()' } ];
	storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8,
			startTime: 1000
		},
		{
			string: 'zero',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: '.',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 14,
			endTime: 2000
		}
	];
	storage.getStartUtterance.returns( storage.utterances[ 0 ] );
	storage.getStartToken.returns( storage.utterances[ 0 ].tokens[ 0 ] );
	storage.getEndUtterance.returns( storage.utterances[ 0 ] );
	storage.getEndToken.returns( storage.utterances[ 0 ].tokens[ 2 ] );
	sinon.spy( selectionPlayer, 'setStartTime' );
	sinon.spy( selectionPlayer, 'setEndTime' );

	selectionPlayer.playSelection();

	sinon.assert.calledWith(
		selectionPlayer.setStartTime,
		storage.utterances[ 0 ],
		1000
	);
	sinon.assert.calledWith(
		selectionPlayer.setEndTime,
		storage.utterances[ 0 ],
		2000
	);
} );

QUnit.skip( 'playSelection(): selected nodes are elements that also contain non-utterance nodes', () => {
	mw.wikispeech.test.util.setContentHtml(
		'<b>Not an utterance<br />Utterance zero.<br />Not an utterance</b>'
		//  [---------------------------------------------------------]
	);
	const parent = $( contentSelector + ' b' ).get( 0 );
	const selection = createMockedSelection( 0, parent, 1 );
	sandbox.stub( window, 'getSelection' ).returns( selection );
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].content = [ { path: './b/text()[2]' } ];
	storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8,
			startTime: 1000
		},
		{
			string: 'zero',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: '.',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 14,
			endTime: 2000
		}
	];
	storage.getStartUtterance.returns( storage.utterances[ 0 ] );
	storage.getStartToken.returns( storage.utterances[ 0 ].tokens[ 0 ] );
	storage.getEndUtterance.returns( storage.utterances[ 0 ] );
	storage.getEndToken.returns( storage.utterances[ 0 ].tokens[ 2 ] );
	sinon.spy( selectionPlayer, 'setStartTime' );
	sinon.spy( selectionPlayer, 'setEndTime' );

	selectionPlayer.playSelection();

	sinon.assert.calledWith(
		selectionPlayer.setStartTime,
		storage.utterances[ 0 ],
		1000
	);
	sinon.assert.calledWith(
		selectionPlayer.setEndTime,
		storage.utterances[ 0 ],
		2000
	);
} );

QUnit.skip( 'playSelectionIfValid(): valid', ( assert ) => {
	sinon.stub( selectionPlayer, 'isSelectionValid' )
		.returns( true );
	sinon.stub( selectionPlayer, 'playSelection' );

	const actualResult = selectionPlayer.playSelectionIfValid();

	sinon.assert.called( selectionPlayer.playSelection );
	assert.strictEqual( actualResult, true );
} );

QUnit.skip( 'playSelectionIfValid(): invalid', ( assert ) => {
	sinon.stub( selectionPlayer, 'isSelectionValid' )
		.returns( false );
	sinon.stub( selectionPlayer, 'playSelection' );

	const actualResult = selectionPlayer.playSelectionIfValid();

	sinon.assert.notCalled( selectionPlayer.playSelection );
	assert.strictEqual( actualResult, false );
} );

QUnit.skip( 'setEndTime()', function () {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [ {} ];
	storage.utterances[ 0 ].audio.currentTime = 0.5;
	selectionPlayer.setEndTime( storage.utterances[ 0 ], 1500 );
	$( storage.utterances[ 0 ].audio ).triggerHandler( 'playing' );
	sinon.stub( selectionPlayer, 'resetPreviousEndUtterance' );

	this.clock.tick( 1001 );

	sinon.assert.called( player.stop );
	sinon.assert.called( selectionPlayer.resetPreviousEndUtterance );
} );

QUnit.skip( 'setEndTime(): faster speech rate', function () {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [ {} ];
	storage.utterances[ 0 ].audio.currentTime = 0.5;
	selectionPlayer.setEndTime( storage.utterances[ 0 ], 1500 );
	mw.user.options.set( 'wikispeechSpeechRate', 2.0 );
	$( storage.utterances[ 0 ].audio ).triggerHandler( 'playing' );
	sinon.stub( selectionPlayer, 'resetPreviousEndUtterance' );

	this.clock.tick( 501 );

	sinon.assert.called( player.stop );
	sinon.assert.called( selectionPlayer.resetPreviousEndUtterance );
} );

QUnit.skip( 'setEndTime(): slower speech rate', function () {
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].tokens = [ {} ];
	storage.utterances[ 0 ].audio.currentTime = 0.5;
	selectionPlayer.setEndTime( storage.utterances[ 0 ], 1500 );
	mw.user.options.set( 'wikispeechSpeechRate', 0.5 );
	$( storage.utterances[ 0 ].audio ).triggerHandler( 'playing' );

	this.clock.tick( 1001 );

	sinon.assert.notCalled( player.stop );
} );

QUnit.skip( 'resetPreviousEndUtterance()', function () {
	mw.wikispeech.test.util.setContentHtml(
		'Utterance with selected text.'
	);
	storage.utterances[ 0 ].audio.src = 'loaded';
	storage.utterances[ 0 ].endOffset = 28;
	storage.utterances[ 0 ].content = [ { path: './text()' } ];
	storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'with',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: 'selected',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 15,
			endOffset: 22,
			startTime: 2000
		},
		{
			string: 'text',
			items: [ storage.utterances[ 0 ].content[ 0 ] ],
			utterance: storage.utterances[ 0 ],
			startOffset: 24,
			endOffset: 27,
			endTime: 4000
		}
	];
	selectionPlayer.setEndTime( storage.utterances[ 0 ], 1000 );
	selectionPlayer.previousEndUtterance = storage.utterances[ 0 ];
	selectionPlayer.resetPreviousEndUtterance();
	$( storage.utterances[ 0 ].audio ).triggerHandler( 'playing' );

	this.clock.tick( 1000 );

	sinon.assert.notCalled( player.stop );
} );

QUnit.skip( 'getFirstNodeInSelection()', ( assert ) => {
	mw.wikispeech.test.util.setContentHtml(
		'before<selected>first text node</selected>after'
		//               [-------------]

	);
	const expectedNode =
		$( contentSelector + ' selected' ).contents().get( 0 );
	const selection = createMockedSelection( 0, expectedNode, 14 );
	sandbox.stub( window, 'getSelection' ).returns( selection );

	const actualNode =
		selectionPlayer.getFirstNodeInSelection();

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.skip( 'getFirstNodeInSelection(): start offset greater than max', ( assert ) => {
	mw.wikispeech.test.util.setContentHtml(
		'before<selected>first text node</selected>after'
		//               [-------------]
	);
	const expectedNode =
		$( contentSelector + ' selected' ).get( 0 );
	const previousNode =
		$( contentSelector ).contents().get( 0 );
	const selection =
		createMockedSelection( 6, previousNode, 14, expectedNode );
	sandbox.stub( window, 'getSelection' ).returns( selection );

	const actualNode =
		selectionPlayer.getFirstNodeInSelection();

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.skip( 'getFirstNodeInSelection(): start offset greater than max, no sibling', ( assert ) => {
	mw.wikispeech.test.util.setContentHtml(
		'<a><b>before</b></a><selected>first text node</selected>after'
		//                             [-------------]

	);
	const expectedNode =
		$( contentSelector + ' selected' ).get( 0 );
	const previousNode =
		$( contentSelector + ' a b' ).contents().get( 0 );
	const selection =
		createMockedSelection( 6, previousNode, 14, expectedNode );
	sandbox.stub( window, 'getSelection' ).returns( selection );

	const actualNode =
		selectionPlayer.getFirstNodeInSelection();

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.skip( 'getLastNodeInSelection()', ( assert ) => {
	mw.wikispeech.test.util.setContentHtml(
		'before<selected>last text node</selected>after'
		//               [------------]

	);
	const expectedNode =
		$( contentSelector + ' selected' ).contents().get( 0 );
	const selection = createMockedSelection( 0, expectedNode, 13 );
	sandbox.stub( window, 'getSelection' ).returns( selection );

	const actualNode =
		selectionPlayer.getLastNodeInSelection();

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.skip( 'getLastNodeInSelection(): end offset is zero', ( assert ) => {
	mw.wikispeech.test.util.setContentHtml(
		'before<selected>last text node</selected>after'
		//               [------------]
	);
	const expectedNode =
		$( contentSelector + ' selected' ).get( 0 );
	const nextNode = $( contentSelector ).contents().get( 2 );
	const selection =
		createMockedSelection( 0, expectedNode, -1, nextNode );
	sandbox.stub( window, 'getSelection' ).returns( selection );

	const actualNode =
		selectionPlayer.getLastNodeInSelection();

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.skip( 'getLastNodeInSelection(): end offset is zero, no sibling', ( assert ) => {
	mw.wikispeech.test.util.setContentHtml(
		'before<selected>last text node</selected><a><b>after</b></a>'
		//               [------------]

	);
	const expectedNode = $( contentSelector + ' selected' ).get( 0 );
	const nextNode = $( contentSelector + ' a b' ).contents().get( 0 );
	const selection = createMockedSelection( 0, expectedNode, -1, nextNode );
	sandbox.stub( window, 'getSelection' ).returns( selection );

	const actualNode = selectionPlayer.getLastNodeInSelection();

	assert.strictEqual( actualNode, expectedNode );
} );
