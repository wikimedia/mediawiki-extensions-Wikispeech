const Highlighter = require( 'ext.wikispeech/ext.wikispeech.highlighter.js' );
const util = require( './ext.wikispeech.test.util.js' );
const Storage = require( 'ext.wikispeech/ext.wikispeech.storage.js' );

QUnit.module( 'ext.wikispeech.highlighter', {
	beforeEach: function () {
		this.highlighter = new Highlighter();
		mw.config.set( 'wgWikispeechContentSelector', '#mw-content-text' );
		this.contentSelector = mw.config.get( 'wgWikispeechContentSelector' );
		util.setContentHtml( 'Utterance zero.' );
		this.storage = sinon.stub( new Storage() );
		this.highlighter.storage = this.storage;
		this.storage.utterances = [
			{
				startOffset: 0,
				endOffset: 14,
				content: [ { path: './text()' } ],
				audio: $( '<audio>' ).get( 0 )
			}
		];
		this.clock = sinon.useFakeTimers();
	},
	afterEach: function () {
		this.clock.restore();
		mw.user.options.set( 'wikispeechSpeechRate', 1.0 );
	}
} );

QUnit.test( 'highlightUtterance()', function ( assert ) {
	this.storage.getNodeForItem.returns(
		$( this.contentSelector ).contents().get( 0 )
	);

	this.highlighter.highlightUtterance( this.storage.utterances[ 0 ] );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">Utterance zero.</span>'
	);
	assert.strictEqual(
		$( '.ext-wikispeech-highlight-sentence' ).prop( 'textPath' ),
		'./text()'
	);
} );

QUnit.test( 'highlightUtterance(): multiple utterances', function ( assert ) {
	util.setContentHtml(
		'Utterance zero. Utterance one. Utterance two.'
	);
	this.storage.getNodeForItem.returns(
		$( this.contentSelector ).contents().get( 0 )
	);
	this.storage.utterances[ 1 ] = {
		startOffset: 16,
		endOffset: 29,
		content: [ { path: './text()' } ]
	};

	this.highlighter.highlightUtterance( this.storage.utterances[ 1 ] );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span> Utterance two.'
	);
} );

QUnit.test( 'highlightUtterance(): with tags', function ( assert ) {
	util.setContentHtml(
		'<p>Utterance with <b>a</b> tag.</p>'
	);
	this.storage.getNodeForItem.onCall( 0 ).returns(
		$( this.contentSelector + ' p' ).contents().get( 0 )
	);
	this.storage.getNodeForItem.onCall( 1 ).returns(
		$( this.contentSelector + ' p b' ).contents().get( 0 )
	);
	this.storage.getNodeForItem.onCall( 2 ).returns(
		$( this.contentSelector + ' p' ).contents().get( 2 )
	);
	this.storage.utterances[ 0 ] = {
		startOffset: 0,
		endOffset: 4,
		content: [
			{ path: './p/text()[1]' },
			{ path: './p/b/text()' },
			{ path: './p/text()[2]' }
		]
	};

	this.highlighter.highlightUtterance( this.storage.utterances[ 0 ] );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<p><span class="ext-wikispeech-highlight-sentence">Utterance with </span><b><span class="ext-wikispeech-highlight-sentence">a</span></b><span class="ext-wikispeech-highlight-sentence"> tag.</span></p>'
	);
} );

QUnit.test( 'highlightUtterance(): wrap middle text nodes properly', function ( assert ) {
	util.setContentHtml( 'First<br />middle<br />last. Next utterance.' );
	this.storage.getNodeForItem.onCall( 0 ).returns(
		$( this.contentSelector ).contents().get( 0 )
	);
	this.storage.getNodeForItem.onCall( 1 ).returns(
		$( this.contentSelector ).contents().get( 2 )
	);
	this.storage.getNodeForItem.onCall( 2 ).returns(
		$( this.contentSelector ).contents().get( 4 )
	);
	this.storage.utterances[ 0 ] = {
		startOffset: 0,
		endOffset: 4,
		content: [
			{ path: './text()[1]' },
			{ path: './text()[2]' },
			{ path: './text()[3]' }
		]
	};

	this.highlighter.highlightUtterance( this.storage.utterances[ 0 ] );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">First</span><br><span class="ext-wikispeech-highlight-sentence">middle</span><br><span class="ext-wikispeech-highlight-sentence">last.</span> Next utterance.'
	);
} );

QUnit.test( 'removeWrappers()', function ( assert ) {
	util.setContentHtml( '<span class="wrapper">Utterance zero.</span>' );

	this.highlighter.removeWrappers( '.wrapper' );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero.'
	);
	assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
} );

QUnit.test( 'removeWrappers(): restore text nodes as one', function ( assert ) {
	util.setContentHtml( 'prefix <span class="wrapper">Utterance zero.</span> suffix' );

	this.highlighter.removeWrappers( '.wrapper' );

	assert.strictEqual( $( this.contentSelector ).html(),
		'prefix Utterance zero. suffix'
	);
	assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
	assert.strictEqual( $( this.contentSelector ).contents().length, 1 );
} );

QUnit.test( 'removeWrappers(): restore text nodes as one with inner wrapper', function ( assert ) {
	util.setContentHtml( '<span class="outer-wrapper">Utterance <span class="inner-wrapper">zero</span>.</span>' );

	this.highlighter.removeWrappers( '.outer-wrapper' );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance <span class="inner-wrapper">zero</span>.'
	);
	assert.strictEqual( $( '.outer-wrapper' ).contents().length, 0 );
} );

QUnit.test( 'removeWrappers(): multiple wrappers', function ( assert ) {
	util.setContentHtml( '<span class="wrapper">Utterance</span> <span class="wrapper">zero.</span>' );

	this.highlighter.removeWrappers( '.wrapper' );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero.'
	);
	assert.strictEqual( $( this.contentSelector ).contents().length, 1 );
	assert.strictEqual( $( '.wrapper' ).contents().length, 0 );
} );

QUnit.test( 'highlightToken()', function ( assert ) {
	this.storage.getNodeForItem.returns(
		$( this.contentSelector ).contents().get( 0 )
	);

	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		startOffset: 0,
		endOffset: 8,
		items: [ this.storage.utterances[ 0 ].content[ 0 ] ]
	};

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-word">Utterance</span> zero.'
	);
} );

QUnit.test( 'highlightToken(): multiple utterances', function ( assert ) {
	util.setContentHtml( 'Utterance zero. Utterance one.' );
	this.storage.getNodeForItem.returns(
		$( this.contentSelector ).contents().get( 0 )
	);
	this.storage.utterances[ 1 ] = {
		startOffset: 16,
		content: [ { path: './text()' } ]
	};
	const highlightedToken = {
		utterance: this.storage.utterances[ 1 ],
		startOffset: 16,
		endOffset: 24,
		items: [ this.storage.utterances[ 1 ].content[ 0 ] ]
	};
	this.storage.utterances[ 1 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-word">Utterance</span> one.'
	);
} );

QUnit.test( 'highlightToken(): with utterance highlighting', function ( assert ) {
	util.setContentHtml( '<span class="ext-wikispeech-highlight-sentence">Utterance with token.</span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './text()' );
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		startOffset: 15,
		endOffset: 19,
		items: [ this.storage.utterances[ 0 ].content[ 0 ] ]
	};
	this.storage.utterances[ 0 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span>'
	);
} );

QUnit.test( 'highlightToken(): with utterance highlighting and multiple utterances', function ( assert ) {
	util.setContentHtml(
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>'
	);
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './text()' );
	this.storage.utterances[ 1 ] = {
		startOffset: 16,
		content: [ { path: './text()' } ]
	};
	const highlightedToken = {
		utterance: this.storage.utterances[ 1 ],
		startOffset: 16,
		endOffset: 24,
		items: [ this.storage.utterances[ 1 ].content[ 0 ] ]
	};
	this.storage.utterances[ 1 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
	);
} );

QUnit.test( 'highlightToken(): with utterance highlighting and other spans', function ( assert ) {
	util.setContentHtml( '<span><span class="ext-wikispeech-highlight-sentence">Utterance with token.</span></span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './span/text()' );
	this.storage.utterances[ 0 ].content[ 0 ] = { path: './span/text()' };
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		startOffset: 15,
		endOffset: 19,
		items: [ this.storage.utterances[ 0 ].content[ 0 ] ]
	};
	this.storage.utterances[ 0 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<span><span class="ext-wikispeech-highlight-sentence">Utterance with <span class="ext-wikispeech-highlight-word">token</span>.</span></span>'
	);
} );

QUnit.test( 'highlightToken(): with tags', function ( assert ) {
	util.setContentHtml( 'Utterance with <br />token.' );
	this.storage.getNodeForItem.returns(
		$( this.contentSelector ).contents().get( 2 )
	);
	this.storage.utterances[ 0 ].content[ 0 ] = { path: './text()[2]' };
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		startOffset: 0,
		endOffset: 4,
		items: [ this.storage.utterances[ 0 ].content[ 0 ] ]
	};
	this.storage.utterances[ 0 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance with <br><span class="ext-wikispeech-highlight-word">token</span>.'
	);
} );

QUnit.test( 'highlightToken(): with multiple utterance highlightings', function ( assert ) {
	util.setContentHtml( '<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, phrase two.</span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.get( 2 ).textPath = './text()[2]';
	this.storage.utterances[ 0 ].content[ 0 ] = { path: './text()[2]' };
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		startOffset: 2,
		endOffset: 7,
		items: [ this.storage.utterances[ 0 ].content[ 0 ] ]
	};
	this.storage.utterances[ 0 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'<span class="ext-wikispeech-highlight-sentence">Phrase </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">, <span class="ext-wikispeech-highlight-word">phrase</span> two.</span>'
	);
} );

QUnit.test( 'highlightToken(): with multiple utterance highlightings and text nodes', function ( assert ) {
	util.setContentHtml( 'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence">Utterance one.</span>' );
	$( '.ext-wikispeech-highlight-sentence' )
		.prop( 'textPath', './text()[2]' );
	this.storage.utterances[ 1 ] = {
		startOffset: 2,
		content: [ { path: './text()[2]' } ]
	};
	const highlightedToken = {
		utterance: this.storage.utterances[ 1 ],
		startOffset: 2,
		endOffset: 10,
		items: [ this.storage.utterances[ 1 ].content[ 0 ] ]
	};
	this.storage.utterances[ 1 ].tokens = [ highlightedToken ];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance <b>zero</b>. <span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">Utterance</span> one.</span>'
	);
} );

QUnit.test( 'highlightToken(): utterance highlighting starts in a new text node', function ( assert ) {
	util.setContentHtml( 'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance </span><b><span class="ext-wikispeech-highlight-sentence">one</span></b><span class="ext-wikispeech-highlight-sentence">.</span>' );
	$( '.ext-wikispeech-highlight-sentence' ).get( 1 ).textPath =
		'./b/text()';
	this.storage.utterances[ 1 ] = {
		startOffset: 2,
		content: [
			{ path: './text()[1]' },
			{ path: './b/text()' },
			{ path: './text()[2]' }
		]
	};
	const highlightedToken = {
		utterance: this.storage.utterances[ 1 ],
		startOffset: 0,
		endOffset: 2,
		items: [ this.storage.utterances[ 1 ].content[ 1 ] ]
	};
	this.storage.utterances[ 1 ].tokens = [
		{},
		highlightedToken
	];

	this.highlighter.highlightToken( highlightedToken );

	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero. <span class="ext-wikispeech-highlight-sentence">Utterance </span><b><span class="ext-wikispeech-highlight-sentence"><span class="ext-wikispeech-highlight-word">one</span></span></b><span class="ext-wikispeech-highlight-sentence">.</span>'
	);
} );

QUnit.test( 'highlightToken(): no highlighting when reading part of content', function ( assert ) {
	this.storage.getNodeForItem.returns(
		$( this.contentSelector ).contents().get( 0 )
	);
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		startOffset: 0,
		endOffset: 8,
		items: [ this.storage.utterances[ 0 ].content[ 0 ] ]
	};
	mw.user.options.set( 'wikispeechPartOfContent', true );
	this.highlighter.highlightToken( highlightedToken );
	assert.strictEqual(
		$( this.contentSelector ).html(),
		'Utterance zero.'
	);
} );

QUnit.test( 'setHighlightTokenTimer()', function () {
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		endTime: 1000
	};
	const nextToken = { utterance: this.storage.utterances[ 0 ] };
	this.storage.utterances[ 0 ].tokens = [
		highlightedToken,
		nextToken
	];
	sinon.stub( this.highlighter, 'highlightToken' );
	this.storage.getNextToken.returns( nextToken );

	this.highlighter.setHighlightTokenTimer( highlightedToken );
	this.clock.tick( 1001 );

	sinon.assert.calledWith( this.highlighter.highlightToken, nextToken );
} );

QUnit.test( 'setHighlightTokenTimer(): faster speech rate', function () {
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		endTime: 1000
	};
	const nextToken = { utterance: this.storage.utterances[ 0 ] };
	this.storage.utterances[ 0 ].tokens = [
		highlightedToken,
		nextToken
	];
	sinon.stub( this.highlighter, 'highlightToken' );
	this.storage.getNextToken.returns( nextToken );
	mw.user.options.set( 'wikispeechSpeechRate', 2.0 );

	this.highlighter.setHighlightTokenTimer( highlightedToken );
	this.clock.tick( 501 );

	sinon.assert.calledWith( this.highlighter.highlightToken, nextToken );
} );

QUnit.test( 'setHighlightTokenTimer(): slower speech rate', function () {
	const highlightedToken = {
		utterance: this.storage.utterances[ 0 ],
		endTime: 1000
	};
	const nextToken = { utterance: this.storage.utterances[ 0 ] };
	this.storage.utterances[ 0 ].tokens = [
		highlightedToken,
		nextToken
	];
	sinon.stub( this.highlighter, 'highlightToken' );
	this.storage.getNextToken.returns( nextToken );
	mw.user.options.set( 'wikispeechSpeechRate', 0.5 );

	this.highlighter.setHighlightTokenTimer( highlightedToken );
	this.clock.tick( 1001 );

	sinon.assert.neverCalledWith( this.highlighter.highlightToken, nextToken );
} );

QUnit.test( 'startTokenHighlighting(): do not highlight token if parts of content is enabled', function () {
	mw.user.options.set( 'wikispeechPartOfContent', true );
	sinon.spy( this.highlighter, 'highlightToken' );
	this.highlighter.startTokenHighlighting( { utterance: 'token' } );
	sinon.assert.notCalled( this.highlighter.highlightToken );
} );
