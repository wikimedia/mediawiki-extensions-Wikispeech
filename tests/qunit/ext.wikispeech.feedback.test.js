const Feedback = require( 'ext.wikispeech/ext.wikispeech.feedback.js' );

QUnit.module( 'ext.wikispeech.feedback', QUnit.newMwEnvironment() );

QUnit.test( 'reportPronunciationError(): with producerUrl', ( assert ) => {
	mw.config.set( 'wgWikispeechProducerUrl', 'https://producer.url' );
	mw.config.set( 'wgWikispeechReportPronunciationUrl', 'https://api.url' );
	const fakeApi = {
		get: sinon.stub(),
		postWithToken: sinon.stub()
	};
	sinon.stub( mw, 'ForeignApi' ).returns( fakeApi );

	const response = {
		query: {
			pages: [ {
				revisions: [ {
					content: 'Some existing content\n|}'
				} ]
			} ]
		}
	};

	fakeApi.get.returns( Promise.resolve( response ) );

	return Feedback.reportPronunciationError( {
		pageTitle: 'TestPage',
		word: 'Test',
		context: 'Test',
		extra: '',
		date: '2026-01-01',
		fullUrl: 'https://example.com',
		pageName: 'TestPage'
	} ).then( () => {
		assert.strictEqual( fakeApi.postWithToken.called, true );
		const args = fakeApi.postWithToken.firstCall.args[ 1 ];
		assert.strictEqual( args.title, 'TestPage' );
		assert.true( args.text.includes( 'Test' ) );
	} );
} );
