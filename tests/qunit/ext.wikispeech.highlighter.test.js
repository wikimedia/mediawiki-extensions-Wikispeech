let contentSelector, util, highlighter, storage;

QUnit.module( 'ext.wikispeech.highlighter', {
	beforeEach: function () {
		util = mw.wikispeech.test.util;
		mw.config.set( 'wgWikispeechContentSelector', '#mw-content-text' );
		contentSelector = mw.config.get( 'wgWikispeechContentSelector' );
		util.setContentHtml( 'Utterance zero.' );
		mw.wikispeech.storage =
			sinon.stub( new mw.wikispeech.Storage() );
		storage = mw.wikispeech.storage;
		storage.utterances = [
			{
				startOffset: 0,
				endOffset: 14,
				content: [ { path: './text()' } ],
				audio: $( '<audio>' ).get( 0 )
			}
		];
		highlighter = new mw.wikispeech.Highlighter();
		this.clock = sinon.useFakeTimers();
		mw.user.options.set( 'wikispeechPartOfContent', false );
	},
	afterEach: function () {
		this.clock.restore();
		mw.user.options.set( 'wikispeechSpeechRate', 1.0 );
	}
} );

QUnit.test( 'highlightUtterance()', ( assert ) => {
	storage.getNodeForItem.returns(
		$( contentSelector ).contents().get( 0 )
	);

	highlighter.highlightUtterance( storage.utterances[ 0 ] );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">Utterance zero.</span>'
	);
	assert.strictEqual(
		$( '.ext-wikispeech-highlight-sentence' ).prop( 'textPath' ),
		'./text()'
	);
} );

QUnit.test( 'highlightUtterance(): multiple utterances', ( assert ) => {
	util.setContentHtml(
		'Utterance zero. Utterance one. Utterance two.'
	);
	storage.getNodeForItem.returns(
		$( contentSelector ).contents().get( 0 )
	);
	storage.utterances[ 1 ] = {
		startOffset: 16,
		endOffset: 29,
		content: [ { path: './text()' } ]
	};

	highlighter.highlightUtterance( storage.utterances[ 1 ] );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span> Utterance two.'
	);
} );

QUnit.test( 'highlightUtterance(): with tags', ( assert ) => {
	util.setContentHtml(
		'<p>Utterance with <b>a</b> tag.</p>'
	);
	storage.getNodeForItem.onCall( 0 ).returns(
		$( contentSelector + ' p' ).contents().get( 0 )
	);
	storage.getNodeForItem.onCall( 1 ).returns(
		$( contentSelector + ' p b' ).contents().get( 0 )
	);
	storage.getNodeForItem.onCall( 2 ).returns(
		$( contentSelector + ' p' ).contents().get( 2 )
	);
	storage.utterances[ 0 ] = {
		startOffset: 0,
		endOffset: 4,
		content: [
			{ path: './p/text()[1]' },
			{ path: './p/b/text()' },
			{ path: './p/text()[2]' }
		]
	};

	highlighter.highlightUtterance( storage.utterances[ 0 ] );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<p><span class="ext-wikispeech-highlight-sentence">Utterance with </span><b><span class="ext-wikispeech-highlight-sentence">a</span></b><span class="ext-wikispeech-highlight-sentence"> tag.</span></p>'
	);
} );

QUnit.test( 'highlightUtterance(): wrap middle text nodes properly', ( assert ) => {
	util.setContentHtml( 'First<br />middle<br />last. Next utterance.' );
	storage.getNodeForItem.onCall( 0 ).returns(
		$( contentSelector ).contents().get( 0 )
	);
	storage.getNodeForItem.onCall( 1 ).returns(
		$( contentSelector ).contents().get( 2 )
	);
	storage.getNodeForItem.onCall( 2 ).returns(
		$( contentSelector ).contents().get( 4 )
	);
	storage.utterances[ 0 ] = {
		startOffset: 0,
		endOffset: 4,
		content: [
			{ path: './text()[1]' },
			{ path: './text()[2]' },
			{ path: './text()[3]' }
		]
	};

	highlighter.highlightUtterance( storage.utterances[ 0 ] );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">First</span><br><span class="ext-wikispeech-highlight-sentence">middle</span><br><span class="ext-wikispeech-highlight-sentence">last.</span> Next utterance.'
	);
} );

QUnit.test( 'removeWrappers()', ( assert ) => {
	util.setContentHtml( '<span class="wrapper">Utterance zero.</span>' );

	highlighter.removeWrappers( '.wrapper' );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero.'
	);
	assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
} );

QUnit.test( 'removeWrappers(): restore text nodes as one', ( assert ) => {
	util.setContentHtml( 'prefix <span class="wrapper">Utterance zero.</span> suffix' );

	highlighter.removeWrappers( '.wrapper' );

	assert.strictEqual( $( contentSelector ).html(),
		'prefix Utterance zero. suffix'
	);
	assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
	assert.strictEqual( $( contentSelector ).contents().length, 1 );
} );

QUnit.test( 'removeWrappers(): restore text nodes as one with inner wrapper', ( assert ) => {
	util.setContentHtml( '<span class="outer-wrapper">Utterance <span class="inner-wrapper">zero</span>.</span>' );

	highlighter.removeWrappers( '.outer-wrapper' );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance <span class="inner-wrapper">zero</span>.'
	);
	assert.strictEqual( $( '.outer-wrapper' ).contents().length, 0 );
} );

QUnit.test( 'removeWrappers(): multiple wrappers', ( assert ) => {
	util.setContentHtml( '<span class="wrapper">Utterance</span> <span class="wrapper">zero.</span>' );

	highlighter.removeWrappers( '.wrapper' );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero.'
	);
	assert.strictEqual( $( contentSelector ).contents().length, 1 );
	assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
} );

QUnit.test( 'highlightToken()', ( assert ) => {
	storage.getNodeForItem.returns(
		$( contentSelector ).contents().get( 0 )
	);

	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		startOffset: 0,
		endOffset: 8,
		items: [ storage.utterances[ 0 ].content[ 0 ] ]
	};

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-word">Utterance</span> zero.'
	);
} );

QUnit.test( 'highlightToken(): multiple utterances', ( assert ) => {
	util.setContentHtml( 'Utterance zero. Utterance one.' );
	storage.getNodeForItem.returns(
		$( contentSelector ).contents().get( 0 )
	);
	storage.utterances[ 1 ] = {
		startOffset: 16,
		content: [ { path: './text()' } ]
	};
	const highlightedToken = {
		utterance: storage.utterances[ 1 ],
		startOffset: 16,
		endOffset: 24,
		items: [ storage.utterances[ 1 ].content[ 0 ] ]
	};
	storage.utterances[ 1 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-word">Utterance</span> one.'
	);
} );

QUnit.test( 'highlightToken(): with utterance highlighting', ( assert ) => {
	util.setContentHtml( '<span class="ext-wikispeech-highlight-sentence">Utterance with token.</span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './text()' );
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		startOffset: 15,
		endOffset: 19,
		items: [ storage.utterances[ 0 ].content[ 0 ] ]
	};
	storage.utterances[ 0 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span>'
	);
} );

QUnit.test( 'highlightToken(): with utterance highlighting and multiple utterances', ( assert ) => {
	util.setContentHtml(
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>'
	);
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './text()' );
	storage.utterances[ 1 ] = {
		startOffset: 16,
		content: [ { path: './text()' } ]
	};
	const highlightedToken = {
		utterance: storage.utterances[ 1 ],
		startOffset: 16,
		endOffset: 24,
		items: [ storage.utterances[ 1 ].content[ 0 ] ]
	};
	storage.utterances[ 1 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
	);
} );

QUnit.test( 'highlightToken(): with utterance highlighting and other spans', ( assert ) => {
	util.setContentHtml( '<span><span class="ext-wikispeech-highlight-sentence">Utterance with token.</span></span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './span/text()' );
	storage.utterances[ 0 ].content[ 0 ] = { path: './span/text()' };
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		startOffset: 15,
		endOffset: 19,
		items: [ storage.utterances[ 0 ].content[ 0 ] ]
	};
	storage.utterances[ 0 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<span><span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span></span>'
	);
} );

QUnit.test( 'highlightToken(): with tags', ( assert ) => {
	util.setContentHtml( 'Utterance with <br />token.' );
	storage.getNodeForItem.returns(
		$( contentSelector ).contents().get( 2 )
	);
	storage.utterances[ 0 ].content[ 0 ] = { path: './text()[2]' };
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		startOffset: 0,
		endOffset: 4,
		items: [ storage.utterances[ 0 ].content[ 0 ] ]
	};
	storage.utterances[ 0 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance with <br><span class="ext-wikispeech-highlight-word">token</span>.'
	);
} );

QUnit.test( 'highlightToken(): with multiple utterance highlightings', ( assert ) => {
	util.setContentHtml( '<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, phrase two.</span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.get( 2 ).textPath = './text()[2]';
	storage.utterances[ 0 ].content[ 0 ] = { path: './text()[2]' };
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		startOffset: 2,
		endOffset: 7,
		items: [ storage.utterances[ 0 ].content[ 0 ] ]
	};
	storage.utterances[ 0 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, <span class="ext-wikispeech-highlight-word">phrase</span> two.</span>'
	);
} );

QUnit.test( 'highlightToken(): with multiple utterance highlightings and text nodes', ( assert ) => {
	util.setContentHtml( 'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './text()[2]' );
	storage.utterances[ 1 ] = {
		startOffset: 2,
		content: [ { path: './text()[2]' } ]
	};
	const highlightedToken = {
		utterance: storage.utterances[ 1 ],
		startOffset: 2,
		endOffset: 10,
		items: [ storage.utterances[ 1 ].content[ 0 ] ]
	};
	storage.utterances[ 1 ].tokens = [ highlightedToken ];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
	);
} );

QUnit.test( 'highlightToken(): utterance highlighting starts in a new text node', ( assert ) => {
	util.setContentHtml( 'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">.</span>' );
	$( '.ext-wikispeech-highlight-sentence' ).get( 1 ).textPath =
		'./b/text()';
	storage.utterances[ 1 ] = {
		startOffset: 2,
		content: [
			{ path: './text()[1]' },
			{ path: './b/text()' },
			{ path: './text()[2]' }
		]
	};
	const highlightedToken = {
		utterance: storage.utterances[ 1 ],
		startOffset: 0,
		endOffset: 2,
		items: [ storage.utterances[ 1 ].content[ 1 ] ]
	};
	storage.utterances[ 1 ].tokens = [
		{},
		highlightedToken
	];

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance </span><b><span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">one</span></span></b><span class="ext-wikispeech-highlight-sentence">.</span>'
	);
} );

QUnit.test( 'highlightToken(): no highlighting when reading part of content', ( assert ) => {
	storage.getNodeForItem.returns(
		$( contentSelector ).contents().get( 0 )
	);
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		startOffset: 0,
		endOffset: 8,
		items: [ storage.utterances[ 0 ].content[ 0 ] ]
	};
	mw.user.options.set( 'wikispeechPartOfContent', true );

	highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( contentSelector ).html(),
		'Utterance zero.'
	);
} );

QUnit.test( 'setHighlightTokenTimer()', function () {
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		endTime: 1000
	};
	const nextToken = { utterance: storage.utterances[ 0 ] };
	storage.utterances[ 0 ].tokens = [
		highlightedToken,
		nextToken
	];
	sinon.stub( highlighter, 'highlightToken' );
	storage.getNextToken.returns( nextToken );

	highlighter.setHighlightTokenTimer( highlightedToken );
	this.clock.tick( 1001 );

	sinon.assert.calledWith( highlighter.highlightToken, nextToken );
} );

QUnit.test( 'setHighlightTokenTimer(): faster speech rate', function () {
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		endTime: 1000
	};
	const nextToken = { utterance: storage.utterances[ 0 ] };
	storage.utterances[ 0 ].tokens = [
		highlightedToken,
		nextToken
	];
	sinon.stub( highlighter, 'highlightToken' );
	storage.getNextToken.returns( nextToken );
	mw.user.options.set( 'wikispeechSpeechRate', 2.0 );

	highlighter.setHighlightTokenTimer( highlightedToken );
	this.clock.tick( 501 );

	sinon.assert.calledWith( highlighter.highlightToken, nextToken );
} );

QUnit.test( 'setHighlightTokenTimer(): slower speech rate', function () {
	const highlightedToken = {
		utterance: storage.utterances[ 0 ],
		endTime: 1000
	};
	const nextToken = { utterance: storage.utterances[ 0 ] };
	storage.utterances[ 0 ].tokens = [
		highlightedToken,
		nextToken
	];
	sinon.stub( highlighter, 'highlightToken' );
	storage.getNextToken.returns( nextToken );
	mw.user.options.set( 'wikispeechSpeechRate', 0.5 );

	highlighter.setHighlightTokenTimer( highlightedToken );
	this.clock.tick( 1001 );

	sinon.assert.neverCalledWith( highlighter.highlightToken, nextToken );
} );

QUnit.test( 'startTokenHighlighting(): do not highlight token if parts of content is enabled', () => {
	mw.user.options.set( 'wikispeechPartOfContent', true );
	sinon.spy( highlighter, 'highlightToken' );

	highlighter.startTokenHighlighting( { utterance: 'token' } );

	sinon.assert.notCalled( highlighter.highlightToken );
} );
