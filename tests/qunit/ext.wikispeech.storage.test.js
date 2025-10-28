const Storage = require( 'ext.wikispeech/ext.wikispeech.storage.js' );
const Player = require( 'ext.wikispeech/ext.wikispeech.player.js' );
const util = require( './ext.wikispeech.test.util.js' );

QUnit.module( 'ext.wikispeech.storage', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.storage = new Storage();
		this.player = sinon.stub( new Player() );
		this.storage.player = this.player;

		this.storage.api = sinon.stub( new mw.Api() );
		$( '#qunit-fixture' ).append(
			$( '<div>' ).attr( 'id', 'content' )
		);
		mw.config.set( 'wgWikispeechContentSelector', '#mw-content-text' );
		mw.user.options.set( 'wikispeechPartOfContent', false );
		this.contentSelector = mw.config.get( 'wgWikispeechContentSelector' );
		this.storage.utterances = [
			{
				audio: $( '<audio>' ).get( 0 ),
				startOffset: 0,
				content: [ { string: 'Utterance zero.' } ]
			},
			{
				audio: $( '<audio>' ).get( 0 ),
				content: [ { string: 'Utterance one.' } ]
			}
		];
	},
	afterEach: function () {
		mw.user.options.set( 'wikispeechVoiceEn', '' );
		mw.user.options.set( 'wikispeechSpeechRate', 1.0 );
	}
} ) );

QUnit.test( 'loadUtterances()', function ( assert ) {
	sinon.stub( this.storage, 'prepareUtterance' );
	const mockWindow = { location: { origin: 'https://consumer.url' } };
	// eslint-disable-next-line no-jquery/no-parse-html-literal
	sinon.stub( this.storage, 'getNodeForItem' ).returns( $( '<h1>Page</h1>' ).get( 0 ) );
	mw.config.set( 'wgPageName', 'Page' );
	const response = {
		'wikispeech-segment': {
			segments: [ {
				startOffset: 0,
				endOffset: 3,
				content: [ {
					string: 'Page',
					path: 'path'
				} ],
				hash: 'hash1234'
			} ]
		}
	};
	this.storage.api.get.returns( $.Deferred().resolve( response ) );

	this.storage.loadUtterances( mockWindow );

	assert.deepEqual(
		this.storage.api.get.firstCall.args[ 0 ],
		{
			action: 'wikispeech-segment',
			page: 'Page',
			'part-of-content': false
		}
	);
	const expectedUtterances = [ {
		startOffset: 0,
		endOffset: 3,
		content: [ {
			string: 'Page',
			path: 'path'
		} ],
		hash: 'hash1234',
		audio: $( '<audio>' ).get( 0 )
	} ];
	assert.deepEqual(
		this.storage.utterances,
		expectedUtterances
	);
} );

QUnit.test( 'loadUtterances(): pass URL as consumer', function ( assert ) {
	const mockWindow = { location: { origin: 'https://consumer.url' } };
	mw.config.set( 'wgWikispeechProducerUrl', 'https://producer.url' );
	sinon.stub( this.storage, 'prepareUtterance' );
	// eslint-disable-next-line no-jquery/no-parse-html-literal
	sinon.stub( this.storage, 'getNodeForItem' ).returns( $( '<h1>Page</h1>' ).get( 0 ) );
	mw.config.set( 'wgPageName', 'Page' );
	mw.config.set( 'wgScriptPath', '/w' );

	const response = {
		'wikispeech-segment': {
			segments: [ {
				content: []
			} ]
		}
	};
	this.storage.api.get.returns( $.Deferred().resolve( response ) );

	this.storage.loadUtterances( mockWindow );

	assert.deepEqual(
		this.storage.api.get.firstCall.args[ 0 ],
		{
			action: 'wikispeech-segment',
			page: 'Page',
			'consumer-url': 'https://consumer.url/w',
			'part-of-content': false
		}
	);
} );

QUnit.test( 'loadUtterances(): part of content enabled', function ( assert ) {
	const mockWindow = { location: { origin: 'https://consumer.url' } };
	sinon.stub( this.storage, 'prepareUtterance' );
	// eslint-disable-next-line no-jquery/no-parse-html-literal
	sinon.stub( this.storage, 'getNodeForItem' ).returns( $( '<h1>Page</h1>' ).get( 0 ) );
	mw.config.set( 'wgPageName', 'Page' );
	mw.user.options.set( 'wikispeechPartOfContent', true );
	const response = {
		'wikispeech-segment': {
			segments: [ {
				content: []
			} ]
		}
	};
	this.storage.api.get.returns( $.Deferred().resolve( response ) );

	this.storage.loadUtterances( mockWindow );

	assert.deepEqual(
		this.storage.api.get.firstCall.args[ 0 ],
		{
			action: 'wikispeech-segment',
			page: 'Page',
			'part-of-content': true
		}
	);
} );

QUnit.test( 'loadUtterances(): offset leading whitespaces in title', function ( assert ) {
	mw.config.set( 'wgPageName', 'Page' );
	const mockWindow = { location: { origin: 'https://consumer.url' } };

	sinon.stub( this.storage, 'prepareUtterance' );
	// eslint-disable-next-line no-jquery/no-parse-html-literal
	sinon.stub( this.storage, 'getNodeForItem' ).returns( $( '<h1>   Page</h1>' ).get( 0 ) );
	const response = {
		'wikispeech-segment': {
			segments: [ {
				startOffset: 0,
				endOffset: 3,
				content: [ {
					string: 'Page',
					path: '//h1/text()'
				} ]
			} ]
		}
	};
	this.storage.api.get.returns( $.Deferred().resolve( response ) );

	this.storage.loadUtterances( mockWindow );

	assert.strictEqual( this.storage.utterances[ 0 ].startOffset, 3 );
	assert.strictEqual( this.storage.utterances[ 0 ].endOffset, 6 );
} );

QUnit.test( 'prepareUtterance()', function () {
	sinon.stub( this.storage, 'loadAudio' ).returns( $.Deferred().resolve() );

	this.storage.prepareUtterance( this.storage.utterances[ 0 ] );

	sinon.assert.calledWith(
		this.storage.loadAudio, this.storage.utterances[ 0 ]
	);
} );

QUnit.test( 'prepareUtterance(): do not request if waiting for response', function () {
	sinon.spy( this.storage, 'loadAudio' );
	this.storage.utterances[ 0 ].request = $.Deferred();

	this.storage.prepareUtterance( this.storage.utterances[ 0 ] );

	sinon.assert.notCalled( this.storage.loadAudio );

} );

QUnit.test( 'prepareUtterance(): do not load audio if already loaded', function () {
	this.storage.utterances[ 0 ].request = $.Deferred().resolve();
	sinon.spy( this.storage, 'loadAudio' );

	this.storage.prepareUtterance( this.storage.utterances[ 0 ] );

	sinon.assert.notCalled( this.storage.loadAudio );
} );

QUnit.test( 'prepareUtterance(): prepare next utterance when playing', function () {
	const utterance = this.storage.utterances[ 0 ];
	const nextUtterance = this.storage.utterances[ 1 ];
	sinon.spy( this.storage, 'prepareUtterance' );
	sinon.stub( this.storage, 'loadAudio' ).returns( $.Deferred().resolve() );
	this.storage.prepareUtterance( utterance );

	$( utterance.audio ).triggerHandler( 'play' );

	sinon.assert.calledWith( this.storage.prepareUtterance, nextUtterance );
} );

QUnit.test( 'prepareUtterance(): do not prepare next audio if it does not exist', function () {
	sinon.spy( this.storage, 'prepareUtterance' );
	sinon.stub( this.storage, 'loadAudio' ).returns( $.Deferred().resolve() );
	this.storage.prepareUtterance( this.storage.utterances[ 1 ] );

	$( this.storage.utterances[ 1 ].audio ).triggerHandler( 'play' );

	sinon.assert.calledOnce( this.storage.prepareUtterance );
} );

QUnit.test( 'prepareUtterance(): skip to next utterance when ended', function () {
	sinon.stub( this.storage, 'loadAudio' ).returns( $.Deferred().resolve() );
	this.storage.prepareUtterance( this.storage.utterances[ 0 ] );

	$( this.storage.utterances[ 0 ].audio ).triggerHandler( 'ended' );

	sinon.assert.called( this.player.skipAheadUtterance );
} );

QUnit.test( 'prepareUtterance(): stop when end of text is reached', function () {
	sinon.stub( this.storage, 'loadAudio' ).returns( $.Deferred().resolve() );
	const lastUtterance = this.storage.utterances[ 1 ];
	this.storage.prepareUtterance( lastUtterance );

	$( lastUtterance.audio ).triggerHandler( 'ended' );

	sinon.assert.called( this.player.stop );
} );

QUnit.test( 'loadAudio()', function ( assert ) {
	mw.config.set( 'wgRevisionId', 1 );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	this.storage.utterances[ 0 ].hash = 'hash1234';
	this.storage.api.get.returns( $.Deferred() );

	this.storage.loadAudio( this.storage.utterances[ 0 ] );

	assert.deepEqual(
		this.storage.api.get.firstCall.args[ 0 ],
		{
			action: 'wikispeech-listen',
			lang: 'en',
			revision: 1,
			segment: 'hash1234'
		}
	);
} );

QUnit.test( 'loadAudio(): request successful', function ( assert ) {
	mw.config.set( 'wgPageContentLanguage', 'en' );
	const response = {
		'wikispeech-listen': {
			audio: 'DummyBase64Audio=',
			tokens: [
				{ orth: 'Utterance' },
				{ orth: 'zero' },
				{ orth: '.' }
			]
		}
	};
	this.storage.api.get.returns( $.Deferred().resolve( response ) );
	sinon.stub( this.storage, 'addTokens' );
	mw.user.options.set( 'wikispeechSpeechRate', 2.0 );

	this.storage.loadAudio( this.storage.utterances[ 0 ] );

	assert.strictEqual(
		this.storage.utterances[ 0 ].audio.src,
		'data:audio/ogg;base64,DummyBase64Audio='
	);
	sinon.assert.calledWith(
		this.storage.addTokens,
		this.storage.utterances[ 0 ],
		[ { orth: 'Utterance' }, { orth: 'zero' }, { orth: '.' } ]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].audio.playbackRate, 2.0 );
} );

QUnit.test( 'loadAudio(): request failed', function ( assert ) {
	mw.config.set( 'wgPageContentLanguage', 'en' );
	this.storage.api.get.returns( $.Deferred().reject() );
	sinon.spy( this.storage, 'addTokens' );

	this.storage.loadAudio( this.storage.utterances[ 0 ] );

	sinon.assert.notCalled( this.storage.addTokens );
	assert.strictEqual( this.storage.utterances[ 0 ].audio.src, '' );
} );

QUnit.test( 'loadAudio(): non-default voice', function ( assert ) {
	mw.user.options.set( 'wikispeechVoiceEn', 'en-voice' );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgRevisionId', 1 );
	this.storage.utterances[ 0 ].hash = 'hash1234';
	this.storage.api.get.returns( $.Deferred() );

	this.storage.loadAudio( this.storage.utterances[ 0 ] );

	assert.deepEqual(
		this.storage.api.get.firstCall.args[ 0 ],
		{
			action: 'wikispeech-listen',
			lang: 'en',
			revision: 1,
			segment: 'hash1234',
			voice: 'en-voice'
		}
	);
} );

QUnit.test( 'requestTts(): pass URL as consumer', function ( assert ) {
	const mockWindow = { location: { origin: 'https://consumer.url' } };
	mw.config.set( 'wgWikispeechProducerUrl', 'https://producer.url' );
	mw.config.set( 'wgRevisionId', 1 );
	mw.config.set( 'wgPageContentLanguage', 'en' );
	mw.config.set( 'wgScriptPath', '/w' );
	this.storage.api.get.returns( $.Deferred() );

	this.storage.requestTts( 'hash1234', mockWindow );

	assert.deepEqual(
		this.storage.api.get.firstCall.args[ 0 ],
		{
			action: 'wikispeech-listen',
			lang: 'en',
			revision: 1,
			segment: 'hash1234',
			'consumer-url': 'https://consumer.url/w'
		}
	);
} );

QUnit.test( 'getUtteranceByOffset(): after', function ( assert ) {
	const actualUtterance =
		this.storage.getUtteranceByOffset( this.storage.utterances[ 0 ], 1 );

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getUtteranceByOffset(): before', function ( assert ) {
	const actualUtterance =
		this.storage.getUtteranceByOffset( this.storage.utterances[ 1 ], -1 );

	assert.strictEqual( actualUtterance, this.storage.utterances[ 0 ] );
} );

QUnit.test( 'getUtteranceByOffset(): original utterance is null', function ( assert ) {
	const actualUtterance = this.storage.getUtteranceByOffset( null, 1 );

	assert.strictEqual( actualUtterance, null );
} );

QUnit.test( 'addTokens()', function ( assert ) {
	util.setContentHtml( 'Utterance zero.' );
	const tokens = [
		{
			orth: 'Utterance',
			endtime: 1000
		},
		{
			orth: 'zero',
			endtime: 2000
		},
		{
			orth: '.',
			endtime: 3000
		}
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.deepEqual(
		[
			{
				string: 'Utterance',
				utterance: this.storage.utterances[ 0 ],
				startTime: 0,
				endTime: 1000,
				items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
				startOffset: 0,
				endOffset: 8
			},
			{
				string: 'zero',
				utterance: this.storage.utterances[ 0 ],
				startTime: 1000,
				endTime: 2000,
				items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
				startOffset: 10,
				endOffset: 13
			},
			{
				string: '.',
				utterance: this.storage.utterances[ 0 ],
				startTime: 2000,
				endTime: 3000,
				items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
				startOffset: 14,
				endOffset: 14
			}
		],
		this.storage.utterances[ 0 ].tokens
	);
} );

QUnit.test( 'addTokens(): handle tag', function ( assert ) {
	util.setContentHtml( 'Utterance with <b>tag</b>.' );
	this.storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
	this.storage.utterances[ 0 ].content[ 1 ] = { string: 'tag' };
	this.storage.utterances[ 0 ].content[ 2 ] = { string: '.' };
	const tokens = [
		{
			orth: 'Utterance',
			endtime: 1000
		},
		{
			orth: 'with',
			endtime: 2000
		},
		{
			orth: 'tag',
			endtime: 3000
		},
		{
			orth: '.',
			endtime: 4000
		}
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.deepEqual(
		this.storage.utterances[ 0 ].tokens[ 0 ].items,
		[ this.storage.utterances[ 0 ].content[ 0 ] ]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 0 ].startOffset, 0 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 0 ].endOffset, 8 );
	assert.deepEqual(
		this.storage.utterances[ 0 ].tokens[ 1 ].items,
		[ this.storage.utterances[ 0 ].content[ 0 ] ]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 1 ].startOffset, 10 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 1 ].endOffset, 13 );
	assert.deepEqual(
		this.storage.utterances[ 0 ].tokens[ 2 ].items,
		[ this.storage.utterances[ 0 ].content[ 1 ] ]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 2 ].startOffset, 0 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 2 ].endOffset, 2 );
	assert.deepEqual(
		this.storage.utterances[ 0 ].tokens[ 3 ].items,
		[ this.storage.utterances[ 0 ].content[ 2 ] ]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 3 ].startOffset, 0 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 3 ].endOffset, 0 );
} );

QUnit.test( 'addTokens(): handle removed element', function ( assert ) {
	util.setContentHtml(
		'Utterance with <del>removed tag</del>.'
	);
	this.storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
	this.storage.utterances[ 0 ].content[ 1 ] = { string: '.' };
	const tokens = [
		{
			orth: 'Utterance',
			endtime: 1000
		},
		{
			orth: 'with',
			endtime: 2000
		},
		{
			orth: '.',
			endtime: 3000
		}
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.deepEqual(
		this.storage.utterances[ 0 ].tokens[ 2 ].items,
		[ this.storage.utterances[ 0 ].content[ 1 ] ]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 2 ].startOffset, 0 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 2 ].endOffset, 0 );
} );

QUnit.test( 'addTokens(): divided tokens', function ( assert ) {
	util.setContentHtml(
		'Utterance with divided to<b>k</b>en.'
	);
	this.storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with divided to';
	this.storage.utterances[ 0 ].content[ 1 ] = { string: 'k' };
	this.storage.utterances[ 0 ].content[ 2 ] = { string: 'en.' };
	const tokens = [
		{ orth: 'Utterance' },
		{ orth: 'with' },
		{ orth: 'divided' },
		{ orth: 'token' },
		{ orth: '.' }
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.deepEqual(
		this.storage.utterances[ 0 ].tokens[ 3 ].items,
		[
			this.storage.utterances[ 0 ].content[ 0 ],
			this.storage.utterances[ 0 ].content[ 1 ],
			this.storage.utterances[ 0 ].content[ 2 ]
		]
	);
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 3 ].startOffset, 23 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 3 ].endOffset, 1 );
} );

QUnit.test( 'addTokens(): ambiguous tokens', function ( assert ) {
	util.setContentHtml( 'A word and the same word.' );
	this.storage.utterances[ 0 ].content[ 0 ].string = 'A word and the same word.';
	const tokens = [
		{ orth: 'A' },
		{ orth: 'word' },
		{ orth: 'and' },
		{ orth: 'the' },
		{ orth: 'same' },
		{ orth: 'word' },
		{ orth: '.' }
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.deepEqual( this.storage.utterances[ 0 ].tokens[ 1 ].startOffset, 2 );
	assert.deepEqual( this.storage.utterances[ 0 ].tokens[ 1 ].endOffset, 5 );
	assert.deepEqual( this.storage.utterances[ 0 ].tokens[ 5 ].startOffset, 20 );
	assert.deepEqual( this.storage.utterances[ 0 ].tokens[ 5 ].endOffset, 23 );
} );

QUnit.test( 'addTokens(): ambiguous tokens in tag', function ( assert ) {
	util.setContentHtml(
		'Utterance with <b>word and word</b>.'
	);
	this.storage.utterances[ 0 ].content[ 0 ].string = 'Utterance with ';
	this.storage.utterances[ 0 ].content[ 1 ] = { string: 'word and word' };
	this.storage.utterances[ 0 ].content[ 2 ] = { string: '.' };
	const tokens = [
		{ orth: 'Utterance' },
		{ orth: 'with' },
		{ orth: 'word' },
		{ orth: 'and' },
		{ orth: 'word' },
		{ orth: '.' }
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.deepEqual( this.storage.utterances[ 0 ].tokens[ 4 ].startOffset, 9 );
	assert.deepEqual( this.storage.utterances[ 0 ].tokens[ 4 ].endOffset, 12 );
} );

QUnit.test( 'addTokens(): multiple utterances', function ( assert ) {
	util.setContentHtml(
		'An utterance. Another utterance.'
	);
	this.storage.utterances[ 1 ].content[ 0 ].string =
		'Another utterance.';
	this.storage.utterances[ 1 ].startOffset = 14;
	const tokens = [
		{ orth: 'Another' },
		{ orth: 'utterance' },
		{ orth: '.' }
	];

	this.storage.addTokens( this.storage.utterances[ 1 ], tokens );

	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 0 ].startOffset, 14 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 0 ].endOffset, 20 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 1 ].startOffset, 22 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 1 ].endOffset, 30 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 2 ].startOffset, 31 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 2 ].endOffset, 31 );
} );

QUnit.test( 'addTokens(): multiple utterances and nodes', function ( assert ) {
	util.setContentHtml(
		'An utterance. Another <b>utterance</b>.'
	);
	this.storage.utterances[ 1 ].content = [
		{ string: 'Another ' },
		{ string: 'utterance' },
		{ string: '.' }
	];
	this.storage.utterances[ 1 ].startOffset = 14;
	const tokens = [
		{ orth: 'Another' },
		{ orth: 'utterance' },
		{ orth: '.' }
	];

	this.storage.addTokens( this.storage.utterances[ 1 ], tokens );

	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 0 ].startOffset, 14 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 0 ].endOffset, 20 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 1 ].startOffset, 0 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 1 ].endOffset, 8 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 2 ].startOffset, 0 );
	assert.deepEqual( this.storage.utterances[ 1 ].tokens[ 2 ].endOffset, 0 );
} );

QUnit.test( 'addTokens(): ambiguous, one character long tokens', function ( assert ) {
	util.setContentHtml( 'a a a.' );
	this.storage.utterances[ 0 ].content[ 0 ].string = 'a a a.';
	const tokens = [
		{ orth: 'a' },
		{ orth: 'a' },
		{ orth: 'a' },
		{ orth: '.' }
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 2 ].startOffset, 4 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 2 ].endOffset, 4 );
} );

QUnit.test( 'addTokens(): non-breaking space', function ( assert ) {
	// The spaces in the two following expressions are non-breaking.
	util.setContentHtml( '1 234 456' );
	this.storage.utterances[ 0 ].content[ 0 ].string = '1 234 456';
	const tokens = [
		{ orth: '1 234 456' }
	];

	this.storage.addTokens( this.storage.utterances[ 0 ], tokens );

	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 0 ].startOffset, 0 );
	assert.strictEqual( this.storage.utterances[ 0 ].tokens[ 0 ].endOffset, 8 );
} );

QUnit.test( 'isSilent(): no duration', function ( assert ) {
	const token = {
		string: 'no duration',
		startTime: 1000,
		endTime: 1000
	};
	const actual = this.storage.isSilent( token );

	assert.strictEqual( actual, true );
} );

QUnit.test( 'isSilent(): no transcription', function ( assert ) {
	const token = {
		string: '',
		startTime: 1000,
		endTime: 2000
	};
	const actual = this.storage.isSilent( token );

	assert.strictEqual( actual, true );
} );

QUnit.test( 'isSilent(): non-silent', function ( assert ) {
	const token = {
		string: 'token',
		startTime: 1000,
		endTime: 2000
	};
	const actual = this.storage.isSilent( token );

	assert.strictEqual( actual, false );
} );

QUnit.test( 'getNextToken()', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'original',
			utterance: this.storage.utterances[ 0 ],
			startTime: 0,
			endTime: 1000
		},
		{
			string: 'next',
			utterance: this.storage.utterances[ 0 ],
			startTime: 1000,
			endTime: 2000
		}
	];

	const actualToken =
		this.storage.getNextToken( this.storage.utterances[ 0 ].tokens[ 0 ] );

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getNextToken(): ignore silent tokens', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'starting token',
			utterance: this.storage.utterances[ 0 ],
			startTime: 0,
			endTime: 1000
		},
		{
			string: 'no duration',
			utterance: this.storage.utterances[ 0 ],
			startTime: 1000,
			endTime: 1000
		},
		{
			string: '',
			utterance: this.storage.utterances[ 0 ],
			startTime: 1000,
			endTime: 2000
		},
		{
			string: 'goal',
			utterance: this.storage.utterances[ 0 ],
			startTime: 2000,
			endTime: 3000
		}
	];

	const actualToken =
		this.storage.getNextToken( this.storage.utterances[ 0 ].tokens[ 0 ] );

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 3 ] );
} );

QUnit.test( 'getPreviousToken()', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'previous',
			utterance: this.storage.utterances[ 0 ],
			startTime: 0,
			endTime: 1000
		},
		{
			string: 'original',
			utterance: this.storage.utterances[ 0 ],
			startTime: 1000,
			endTime: 2000
		}
	];

	const actualToken =
		this.storage.getPreviousToken( this.storage.utterances[ 0 ].tokens[ 1 ] );

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getPreviousToken(): ignore silent tokens', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'goal',
			startTime: 0,
			endTime: 1000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: 'no duration',
			startTime: 1000,
			endTime: 1000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: '',
			startTime: 1000,
			endTime: 2000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: 'starting token',
			startTime: 2000,
			endTime: 3000,
			utterance: this.storage.utterances[ 0 ]
		}
	];
	this.storage.utterances[ 0 ].audio.currentTime = 2.1;

	const actualToken =
		this.storage.getPreviousToken( this.storage.utterances[ 0 ].tokens[ 3 ] );

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getLastToken()', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'token',
			startTime: 0,
			endTime: 1000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: 'last',
			startTime: 1000,
			endTime: 2000,
			utterance: this.storage.utterances[ 0 ]
		}
	];

	const actualToken =
		this.storage.getLastToken( this.storage.utterances[ 0 ] );

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getLastToken(): ignore silent tokens', function ( assert ) {
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'token',
			startTime: 0,
			endTime: 1000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: 'last',
			startTime: 1000,
			endTime: 2000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: 'no duration',
			startTime: 2000,
			endTime: 2000,
			utterance: this.storage.utterances[ 0 ]
		},
		{
			string: '',
			startTime: 2000,
			endTime: 3000,
			utterance: this.storage.utterances[ 0 ]
		}
	];

	const actualToken = this.storage.getLastToken( this.storage.utterances[ 0 ] );

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getFirstTextNode()', function ( assert ) {
	util.setContentHtml(
		'<a>first text node<br />other text node</a>'
	);
	const parentNode = $( this.contentSelector + ' a' ).get( 0 );
	const expectedNode = $( this.contentSelector + ' a' ).contents().get( 0 );

	const actualNode = this.storage.getFirstTextNode( parentNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getFirstTextNode(): deeper than other text node', function ( assert ) {
	util.setContentHtml(
		'<a><b>first text node</b>other text node</a>'
	);
	const parentNode = $( this.contentSelector + ' a' ).get( 0 );
	const expectedNode = $( this.contentSelector + ' b' ).contents().get( 0 );

	const actualNode = this.storage.getFirstTextNode( parentNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getFirstTextNode(): given node is a text node', function ( assert ) {
	util.setContentHtml(
		'first text node<br />other text node'
	);
	const expectedNode = $( this.contentSelector ).contents().get( 0 );

	const actualNode = this.storage.getFirstTextNode( expectedNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getLastTextNode()', function ( assert ) {
	util.setContentHtml(
		'<a>other text node<br />last text node</a>'
	);
	const parentNode = $( this.contentSelector + ' a' ).get( 0 );
	const expectedNode = $( this.contentSelector + ' a' ).contents().get( 2 );

	const actualNode = this.storage.getLastTextNode( parentNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getLastTextNode(): deeper than other text node', function ( assert ) {
	util.setContentHtml(
		'<a>other text node<b>other text node</b></a>'
	);
	const parentNode = $( this.contentSelector + ' a' ).get( 0 );
	const expectedNode = $( this.contentSelector + ' b' ).contents().get( 0 );

	const actualNode = this.storage.getLastTextNode( parentNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getLastTextNode(): given node is a text node', function ( assert ) {
	util.setContentHtml(
		'other text node<br />last text node'
	);
	const expectedNode = $( this.contentSelector ).contents().get( 2 );

	const actualNode = this.storage.getLastTextNode( expectedNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getStartUtterance()', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 0 ].endOffset = 14;
	this.storage.utterances[ 1 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].startOffset = 16;
	this.storage.utterances[ 1 ].endOffset = 29;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()' } ],
		startOffset: 31,
		endOffset: 44
	};
	util.setContentHtml(
		'Utterance zero. Utterance one. Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 0 );
	const offset = 16;

	const actualUtterance =
		this.storage.getStartUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getStartUtterance(): offset between utterances', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].startOffset = 16;
	this.storage.utterances[ 1 ].endOffset = 29;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()' } ],
		startOffset: 31,
		endOffset: 44
	};
	util.setContentHtml(
		'Utterance zero. Utterance one. Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 0 );
	const offset = 15;

	const actualUtterance =
		this.storage.getStartUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getStartUtterance(): offset between utterances and next utterance in different node', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
	this.storage.utterances[ 1 ].content[ 0 ].path = './a/text()';
	this.storage.utterances[ 1 ].startOffset = 0;
	this.storage.utterances[ 1 ].endOffset = 13;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()[2]' } ],
		startOffset: 1,
		endOffset: 14
	};
	util.setContentHtml(
		'Utterance zero. <a>Utterance one.</a> Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 0 );
	const offset = 15;

	const actualUtterance =
		this.storage.getStartUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getEndUtterance()', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].startOffset = 16;
	this.storage.utterances[ 1 ].endOffset = 29;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()' } ],
		startOffset: 31,
		endOffset: 44
	};
	util.setContentHtml(
		'Utterance zero. Utterance one. Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 0 );
	const offset = 16;

	const actualUtterance =
		this.storage.getEndUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getEndUtterance(): offset between utterances', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 1 ].startOffset = 16;
	this.storage.utterances[ 1 ].endOffset = 29;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()' } ],
		startOffset: 31,
		endOffset: 44
	};
	util.setContentHtml(
		'Utterance zero. Utterance one. Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 0 );
	const offset = 30;

	const actualUtterance =
		this.storage.getEndUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getEndUtterance(): offset between utterances and previous utterance in different node', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
	this.storage.utterances[ 1 ].content[ 0 ].path = './a/text()';
	this.storage.utterances[ 1 ].startOffset = 0;
	this.storage.utterances[ 1 ].endOffset = 13;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()[2]' } ],
		startOffset: 1,
		endOffset: 14
	};
	util.setContentHtml(
		'Utterance zero. <a>Utterance one.</a> Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 2 );
	const offset = 0;

	const actualUtterance =
		this.storage.getEndUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getEndUtterance(): offset between utterances and previous utterance in different node with other utterance', function ( assert ) {
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
	this.storage.utterances[ 1 ].content[ 0 ].path = './text()[1]';
	this.storage.utterances[ 1 ].startOffset = 16;
	this.storage.utterances[ 1 ].endOffset = 29;
	this.storage.utterances[ 2 ] = {
		content: [ { path: './text()[2]' } ],
		startOffset: 1,
		endOffset: 14
	};
	util.setContentHtml(
		'Utterance zero. Utterance one.<br /> Utterance two.'
	);
	const textNode = $( this.contentSelector ).contents().get( 2 );
	const offset = 0;

	const actualUtterance =
		this.storage.getEndUtterance(
			textNode,
			offset
		);

	assert.strictEqual( actualUtterance, this.storage.utterances[ 1 ] );
} );

QUnit.test( 'getNextTextNode()', function ( assert ) {
	util.setContentHtml(
		'original node<br />next node'
	);
	const originalNode = $( this.contentSelector ).contents().get( 0 );
	const expectedNode = $( this.contentSelector ).contents().get( 2 );

	const actualNode =
		this.storage.getNextTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getNextTextNode(): node is one level down', function ( assert ) {
	util.setContentHtml(
		'original node<a>next node</a>'
	);
	const originalNode = $( this.contentSelector ).contents().get( 0 );
	const expectedNode = $( this.contentSelector + ' a' ).contents().get( 0 );

	const actualNode =
		this.storage.getNextTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getNextTextNode(): node is one level up', function ( assert ) {
	util.setContentHtml(
		'<a>original node</a>next node'
	);
	const originalNode = $( this.contentSelector + ' a' ).contents().get( 0 );
	const expectedNode = $( this.contentSelector ).contents().get( 1 );

	const actualNode =
		this.storage.getNextTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getNextTextNode(): node contains non-text nodes', function ( assert ) {
	util.setContentHtml(
		'original node<a><!--comment--></a>next node'
	);
	const originalNode = $( this.contentSelector ).contents().get( 0 );
	const expectedNode = $( this.contentSelector ).contents().get( 2 );

	const actualNode =
		this.storage.getNextTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getPreviousTextNode()', function ( assert ) {
	util.setContentHtml(
		'previous node<br />original node'
	);
	const originalNode = $( this.contentSelector ).contents().get( 2 );
	const expectedNode = $( this.contentSelector ).contents().get( 0 );

	const actualNode =
		this.storage.getPreviousTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getPreviousTextNode(): node is one level down', function ( assert ) {
	util.setContentHtml(
		'<a>previous node</a>original node'
	);
	const originalNode = $( this.contentSelector ).contents().get( 1 );
	const expectedNode = $( this.contentSelector + ' a' ).contents().get( 0 );

	const actualNode =
		this.storage.getPreviousTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getPreviousTextNode(): node is one level up', function ( assert ) {
	util.setContentHtml(
		'previous node<a>original node</a>'
	);
	const originalNode = $( this.contentSelector + ' a' ).contents().get( 0 );
	const expectedNode = $( this.contentSelector ).contents().get( 0 );

	const actualNode =
		this.storage.getPreviousTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getPreviousTextNode(): node contains non-text nodes', function ( assert ) {
	util.setContentHtml(
		'previous node<a><!--comment--></a>original node'
	);
	const originalNode = $( this.contentSelector ).contents().get( 2 );
	const expectedNode = $( this.contentSelector ).contents().get( 0 );

	const actualNode =
		this.storage.getPreviousTextNode( originalNode );

	assert.strictEqual( actualNode, expectedNode );
} );

QUnit.test( 'getStartToken()', function ( assert ) {
	util.setContentHtml( 'Utterance zero.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'zero',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: '.',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 14
		}
	];

	const actualToken =
		this.storage.getStartToken(
			this.storage.utterances[ 0 ],
			textNode,
			0
		);

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getStartToken(): between tokens', function ( assert ) {
	util.setContentHtml( 'Utterance zero.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'zero',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: '.',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 14
		}
	];

	const actualToken =
		this.storage.getStartToken(
			this.storage.utterances[ 0 ],
			textNode,
			9
		);

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getStartToken(): in different node', function ( assert ) {
	util.setContentHtml( 'Utterance <br />zero.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
	this.storage.utterances[ 0 ].content[ 1 ] = { path: './text()[2]' };
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'zero',
			items: [ this.storage.utterances[ 0 ].content[ 1 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 3
		},
		{
			string: '.',
			items: [ this.storage.utterances[ 0 ].content[ 1 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 4,
			endOffset: 4
		}
	];

	const actualToken =
		this.storage.getStartToken(
			this.storage.utterances[ 0 ],
			textNode,
			9
		);

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getEndToken()', function ( assert ) {
	util.setContentHtml( 'Utterance zero.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'zero',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: '.',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 14
		}
	];

	const actualToken =
		this.storage.getEndToken(
			this.storage.utterances[ 0 ],
			textNode,
			10
		);

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 1 ] );
} );

QUnit.test( 'getEndToken(): between tokens', function ( assert ) {
	util.setContentHtml( 'Utterance zero.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()';
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'zero',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 10,
			endOffset: 13
		},
		{
			string: '.',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 14,
			endOffset: 14
		}
	];

	const actualToken =
		this.storage.getEndToken(
			this.storage.utterances[ 0 ],
			textNode,
			9
		);

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getEndToken(): in different node', function ( assert ) {
	util.setContentHtml( 'Utterance<br /> zero.' );
	const textNode = $( this.contentSelector ).contents().get( 0 );
	this.storage.utterances[ 0 ].content[ 0 ].path = './text()[1]';
	this.storage.utterances[ 0 ].content[ 1 ] = { path: './text()[2]' };
	this.storage.utterances[ 0 ].tokens = [
		{
			string: 'Utterance',
			items: [ this.storage.utterances[ 0 ].content[ 0 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 0,
			endOffset: 8
		},
		{
			string: 'zero',
			items: [ this.storage.utterances[ 0 ].content[ 1 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 1,
			endOffset: 4
		},
		{
			string: '.',
			items: [ this.storage.utterances[ 0 ].content[ 1 ] ],
			utterance: this.storage.utterances[ 0 ],
			startOffset: 5,
			endOffset: 5
		}
	];

	const actualToken =
		this.storage.getEndToken(
			this.storage.utterances[ 0 ],
			textNode,
			0
		);

	assert.strictEqual( actualToken, this.storage.utterances[ 0 ].tokens[ 0 ] );
} );

QUnit.test( 'getNodeForItem()', function ( assert ) {
	util.setContentHtml( 'Text node.' );
	const item = { path: './text()' };

	const textNode = this.storage.getNodeForItem( item );

	assert.strictEqual(
		textNode,
		$( this.contentSelector ).contents().get( 0 )
	);
} );

QUnit.test( 'getNodeForItem(): path is null', function ( assert ) {
	const item = { path: null };
	const textNode = this.storage.getNodeForItem( item );
	assert.strictEqual( textNode, null );
} );
