/**
 * @module ext.wikispeech.feedback
 */

/**
 * Dialog for reporting pronunciation errors.
 *
 * @extends OO.ui.ProcessDialog
 *
 * @param {Object} config
 */
function PronunciationErrorDialog( config ) {
	config = config || {};
	OO.ui.ProcessDialog.call( this, config );
	this.selectedWord = config.selectedWord;
	this.context = config.context;
}

OO.inheritClass( PronunciationErrorDialog, OO.ui.ProcessDialog );

PronunciationErrorDialog.static.name = 'pronunciationErrorDialog';
PronunciationErrorDialog.static.title = mw.msg( 'wikispeech-report-pronunciation-error' );
PronunciationErrorDialog.static.actions = [
	{ action: 'submit', label: mw.msg( 'wikispeech-report-pronunciation-error-button' ), flags: 'primary' },
	{ action: 'cancel', label: mw.msg( 'ooui-dialog-message-reject' ), flags: 'safe' }
];

PronunciationErrorDialog.prototype.initialize = function () {
	PronunciationErrorDialog.super.prototype.initialize.apply( this, arguments );

	this.wordInput = new OO.ui.TextInputWidget( {
		value: this.selectedWord
	} );

	this.contextInput = new OO.ui.TextInputWidget( {
		value: this.context
	} );

	this.extraInput = new OO.ui.TextInputWidget();

	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	this.content.$element.append(
		$( '<p>' ).text( mw.msg( 'wikispeech-word' ) ),
		this.wordInput.$element,
		$( '<p>' ).text( mw.msg( 'wikispeech-report-pronunciation-error-context' ) ),
		this.contextInput.$element,
		$( '<p>' ).text( mw.msg( 'wikispeech-report-pronunciation-error-other' ) ),
		this.extraInput.$element
	);

	this.$body.append( this.content.$element );
};

/**
 * Handle dialog actions.
 *
 * @param {string} action
 * @return {OO.ui.Process}
 */
PronunciationErrorDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'submit' ) {
		return new OO.ui.Process( () => {
			this.close( {
				action: 'submit',
				selectedText: this.wordInput.getValue(),
				context: this.contextInput.getValue(),
				extra: this.extraInput.getValue()
			} );
		} );
	} else if ( action === 'cancel' ) {
		return new OO.ui.Process( () => {
			this.close( {
				action: 'cancel'
			} );
		} );
	}
	return PronunciationErrorDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * Report a pronunciation error by adding a row to a wiki page.
 *
 * @param {Object} options
 * @return {Promise}
 */
function reportPronunciationError( options ) {
	const producerUrl = mw.config.get( 'wgWikispeechProducerUrl' );
	let api;

	if ( producerUrl ) {
		const reportPronunciationUrl = mw.config.get( 'wgWikispeechReportPronunciationUrl' );
		api = new mw.ForeignApi( reportPronunciationUrl );
	} else {
		api = new mw.Api();
	}

	const pageTitle = options.pageTitle;

	return api.get( {
		action: 'query',
		prop: 'revisions',
		rvprop: 'content',
		titles: pageTitle,
		formatversion: 2,
		format: 'json'
	} ).then( ( data ) => {
		const page = data.query.pages[ 0 ];
		if ( page.missing ) {
			throw new Error( mw.msg( 'wikispeech-non-existing-page' ) );
		}
		const content = page.revisions[ 0 ].content;

		const tableEndIndex = content.lastIndexOf( '|}' );
		if ( tableEndIndex === -1 ) {
			throw new Error( mw.msg( 'wikispeech-report-table-missing' ) );
		}

		const row = `|-\n| ${ options.date } || ${ options.word } || [${ options.fullUrl } ${ options.pageName.replace( /_/g, ' ' ) } ] || ${ options.context } || ${ options.extra } ||\n`;

		const newContent =
			content.slice( 0, tableEndIndex ) +
			row +
			content.slice( tableEndIndex );

		return api.postWithToken( 'csrf', {
			action: 'edit',
			title: pageTitle,
			text: newContent,
			format: 'json'
		} );
	} );
}

const feedback = {};

/**
 * Open the pronunciation error dialog and handle submission.
 * if a valid word is selected, it is pre filled.
 *
 * @param {Object} params
 * @param params.storage
 * @param params.selectionPlayer
 */
feedback.openPronunciationErrorDialog = function ( { storage, selectionPlayer } ) {
	const selection = window.getSelection();

	const server = mw.config.get( 'wgServer' );
	const articlePath = mw.config.get( 'wgArticlePath' );
	const pageName = mw.config.get( 'wgPageName' );
	const fullUrl = server + articlePath.replace( '$1', pageName );

	const lang = mw.config.get( 'wgContentLanguage' );
	const feedbackPage = mw.config.get( 'wgWikispeechFeedbackPage' ).replace( '$lang', lang );

	let selectedWord = '';
	let context = '';

	const openDialog = () => {
		const dialog = new PronunciationErrorDialog( { selectedWord, context } );
		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog ).closed.then( ( data ) => {
			if ( data && data.action === 'submit' ) {
				const word = data.selectedText || selectedWord;
				const contextValue = data.context || context;
				const extra = data.extra;
				const date = new Date().toISOString().split( 'T' )[ 0 ];

				reportPronunciationError( {
					pageTitle: feedbackPage,
					word,
					context: contextValue,
					extra,
					date,
					fullUrl,
					pageName
				} ).then( () => {
					const pageTitle = feedbackPage.replace( /_/g, ' ' );
					let pageUrl;
					const producerUrl = mw.config.get( 'wgWikispeechProducerUrl' );

					if ( producerUrl ) {
						const apiUrl = mw.config.get( 'wgWikispeechReportPronunciationUrl' );
						const producerBase = apiUrl.replace( /\/api\.php$/, '' );

						pageUrl = producerBase + '/index.php?' +
						new URLSearchParams( { title: feedbackPage } );
					} else {
						pageUrl = mw.util.getUrl( feedbackPage );
					}

					const $link = $( '<a>' )
						.attr( {
							href: pageUrl,
							target: '_blank'
						} )
						.text( pageTitle );

					const message = mw.msg( 'wikispeech-pronunciation-saved' );
					mw.notify(
						$( '<div>' ).text( message + ' ' ).append( $link ),
						{ type: 'success' }
					);
				} ).catch( ( err ) => {
					mw.log.warn( err );
					mw.notify( err && err.message ? err.message : mw.msg( 'wikispeech-report-pronunciation-post-error' ), { type: 'error' } );
				} );

			}
		} );
	};

	if ( selectionPlayer.isSelectionValid() ) {
		const range = selection.getRangeAt( 0 );
		const startNode = storage.getFirstTextNode( selectionPlayer.getFirstNodeInSelection(), true );
		const endNode = storage.getLastTextNode( selectionPlayer.getLastNodeInSelection(), true );
		const startOffset = range.startOffset;
		const endOffset = range.endOffset - 1;

		const startUtterance = storage.getStartUtterance( startNode, startOffset );
		const endUtterance = storage.getEndUtterance( endNode, endOffset );

		storage.prepareUtterance( startUtterance ).done( () => {
			storage.prepareUtterance( endUtterance ).done( () => {
				const startToken = storage.getStartToken( startUtterance, startNode, startOffset );
				const endToken = storage.getEndToken( endUtterance, endNode, endOffset );

				const selectedText = selection.toString();

				if ( !selectedText.trim() || !/^[A-Za-zÅÄÖåäö]+$/.test( startToken.string ) ) {
					mw.notify( mw.msg( 'wikispeech-no-word-error' ), { type: 'warn' } );
					return;
				}
				if ( startToken !== endToken ) {
					mw.notify( mw.msg( 'wikispeech-one-word-only' ), { type: 'warn' } );
					return;
				}

				selectedWord = startToken.string;
				context = startToken.utterance.content.map( ( item ) => item.text ).join( ' ' );
				openDialog();
			} );
		} );
	} else {
		mw.notify( mw.msg( 'wikispeech-tip-highlight-word' ), { type: 'info' } );
		openDialog();
	}
};
feedback.reportPronunciationError = reportPronunciationError;
module.exports = feedback;
