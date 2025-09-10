/**
 * Loads and stores objects used by the extension.
 *
 * Contains functions for other modules to retrieve information
 * about the utterances.
 *
 * @class ext.wikispeech.Storage
 * @constructor
 */

const util = require( './ext.wikispeech.util.js' );

class Storage {
	constructor() {
		this.utterances = [];
		this.utterancesLoaded = $.Deferred();

		if ( mw.wikispeech && mw.wikispeech.consumerMode ) {
			const producerApiUrl = mw.wikispeech.producerUrl + '/api.php';
			this.api = new mw.ForeignApi( producerApiUrl );
		} else {
			this.api = new mw.Api();
		}

	}

	/**
	 * Load all utterances.
	 *
	 * Uses the MediaWiki API to get the segments of the text.
	 *
	 * @param {Object} window
	 */

	loadUtterances( window ) {
		const page = mw.config.get( 'wgPageName' );
		const options = {
			action: 'wikispeech-segment',
			page: page,
			'part-of-content': mw.user.options.get( 'wikispeechPartOfContent' )
		};
		if ( mw.wikispeech.consumerMode ) {
			options[ 'consumer-url' ] = window.location.origin +
				mw.config.get( 'wgScriptPath' );
		}
		this.api.get(
			options,
			{
				beforeSend: function ( jqXHR, settings ) {
					mw.log(
						'Requesting segments:', settings.url
					);
				}
			}
		).done( ( data ) => {
			mw.log( 'Segments received:', data );
			this.utterances = data[ 'wikispeech-segment' ].segments;

			// Add extra offset to the title if it has leading
			// whitespaces. When using the new skin, there are
			// whitespaces around the title that do not appear in
			// the display title. This leads to highlighting being
			// wrong.
			const titleUtterance = this.utterances[ 0 ];
			const firstNode = this.getNodeForItem( titleUtterance.content[ 0 ] );
			const leadingWhitespaces = firstNode.textContent.match( /^\s+/ );
			if ( leadingWhitespaces ) {
				const offset = leadingWhitespaces[ 0 ].length;
				titleUtterance.startOffset += offset;
				titleUtterance.endOffset += offset;
			}

			for ( let i = 0; i < this.utterances.length; i++ ) {
				const utterance = this.utterances[ i ];
				utterance.audio = $( '<audio>' ).get( 0 );
			}
			this.utterancesLoaded.resolve();
			this.prepareUtterance( this.utterances[ 0 ] );
		} );
	}

	/**
	 * Prepare an utterance for playback.
	 *
	 * Audio for the utterance is requested from the Speechoid service
	 * and event listeners are added. When an utterance starts
	 * playing, the next one is prepared, and when an utterance is
	 * done, the next utterance is played. This is meant to be a
	 * balance between not having to pause between utterance and
	 * not requesting more than needed.
	 *
	 * @param {Object} utterance The utterance to prepare.
	 * @return {jQuery.Promise}
	 */

	prepareUtterance( utterance ) {
		const $audio = $( utterance.audio );
		if ( !utterance.request ) {
			// Add event listener only once.
			$audio.on( 'playing', () => {
				let firstToken;

				// Highlight token only when the audio starts
				// playing, since we need the token info from the
				// response to know what to highlight.
				if (
					!this.player.playingSelection &&
						$audio.prop( 'currentTime' ) === 0
				) {
					firstToken = utterance.tokens[ 0 ];
					this.highlighter.startTokenHighlighting(
						firstToken
					);
				}
			} );
			const nextUtterance = this.getNextUtterance( utterance );
			if ( nextUtterance ) {
				$audio.on( {
					play: () => {
						this.prepareUtterance( nextUtterance );
					},
					ended: () => {
						this.player.skipAheadUtterance();
					}
				} );
			} else {
				// For last utterance, just stop the playback when
				// done.
				$audio.on( 'ended', () => {
					this.player.stop();
				} );
			}
		}
		if ( !utterance.request || utterance.request.state() === 'rejected' ) {
			// Only load audio for an utterance if it hasn't been
			// successfully loaded yet.
			utterance.request = this.loadAudio( utterance );
		}
		return utterance.request;
	}

	/**
	 * Load audio for an utterance.
	 *
	 * Sends a request to the Speechoid service and adds audio and tokens
	 * when the response is received.
	 *
	 * @param {Object} utterance The utterance to load audio for.
	 * @return {jQuery.Promise}
	 */

	loadAudio( utterance ) {
		const utteranceIndex = this.utterances.indexOf( utterance );
		mw.log(
			'Loading audio for utterance #' + utteranceIndex + ':',
			utterance
		);
		return this.requestTts( utterance.hash, window )
			.done( ( response ) => {
				const audioUrl = 'data:audio/ogg;base64,' +
					response[ 'wikispeech-listen' ].audio;
				mw.log(
					'Setting audio url for: [' + utteranceIndex + ']',
					utterance, '=',
					response[ 'wikispeech-listen' ].audio.length + ' base64 bytes'
				);
				$( utterance.audio ).attr( 'src', audioUrl );
				utterance.audio.playbackRate =
					mw.user.options.get( 'wikispeechSpeechRate' );
				this.addTokens( utterance, response[ 'wikispeech-listen' ].tokens );
			} );
	}

	/**
	 * Send a request to the Speechoid service.
	 *
	 * Request is sent via the "wikispeech-listen" API action. Language to
	 * use is retrieved from the current page.
	 *
	 * @param {string} segmentHash
	 * @param {Object} window
	 * @return {jQuery.Promise}
	 */

	requestTts( segmentHash, window ) {
		const language = mw.config.get( 'wgPageContentLanguage' );
		const voice = util.getUserVoice( language );
		const options = {
			action: 'wikispeech-listen',
			lang: language,
			revision: mw.config.get( 'wgRevisionId' ),
			segment: segmentHash
		};
		if ( voice !== '' ) {
			// Set voice if not default.
			options.voice = voice;
		}
		if ( mw.wikispeech.consumerMode ) {
			options[ 'consumer-url' ] = window.location.origin +
				mw.config.get( 'wgScriptPath' );
		}
		const request = this.api.get(
			options,
			{
				beforeSend: function ( jqXHR, settings ) {
					mw.log(
						'Sending TTS request: ' + settings.url
					);
				}
			}
		)
			.done( ( data ) => {
				mw.log( 'Response received:', data );
			} );
		return request;
	}

	/**
	 * Add tokens to an utterance.
	 *
	 * @param {Object} utterance The utterance to add tokens to.
	 * @param {Object[]} responseTokens Tokens from a Speechoid response,
	 *  where each token is an object. For these objects, the
	 *  property "orth" is the string used by the TTS to generate
	 *  audio for the token.
	 */

	addTokens( utterance, responseTokens ) {
		utterance.tokens = [];
		let searchOffset = 0;
		for ( let i = 0; i < responseTokens.length; i++ ) {
			const responseToken = responseTokens[ i ];
			let startTime;
			if ( i === 0 ) {
				// The first token in an utterance always start on
				// time zero.
				startTime = 0;
			} else {
				// Since the response only contains end times for
				// token, the start time for a token is set to the
				// end time of the previous one.
				startTime = responseTokens[ i - 1 ].endtime;
			}
			const token = {
				string: responseToken.orth,
				startTime: startTime,
				endTime: responseToken.endtime,
				utterance: utterance
			};
			utterance.tokens.push( token );
			if ( i > 0 ) {
				// Start looking for the next token after the
				// previous one, except for the first token, where
				// we want to start on zero.
				searchOffset += 1;
			}
			searchOffset = this.addOffsetsAndItems(
				token,
				searchOffset
			);
		}
	}

	/**
	 * Add properties for offsets and items to a token.
	 *
	 * The offsets are for the start and end of the token in the
	 * text node which they appear. These text nodes are not
	 * necessary the same.
	 *
	 * The items store information used to get the text nodes in
	 * which the token starts, ends and any text nodes in between.
	 *
	 * @param {Object} token The token to add properties to.
	 * @param {number} searchOffset The offset to start searching
	 *  from, in the concatenated string.
	 * @return {number} The end offset in the concatenated string.
	 */

	addOffsetsAndItems(
		token,
		searchOffset
	) {
		const utterance = token.utterance;
		let items = [];
		const startOffsetInUtteranceString =
			this.getStartOffsetInUtteranceString(
				token.string,
				utterance.content,
				items,
				searchOffset
			);
		const endOffsetInUtteranceString =
			startOffsetInUtteranceString +
			token.string.length - 1;

		// `items` now contains all the items in the utterance,
		// from the first one to the last, that contains at least
		// part of the token. To get only the ones that contain
		// part of the token, the items that appear before the
		// token are removed.
		let endOffsetForItem = 0;
		items =
			items.filter( ( item ) => {
				endOffsetForItem += item.string.length;
				return endOffsetForItem >
					startOffsetInUtteranceString;
			} );
		token.items = items;

		// Calculate start and end offset for the token, in the
		// text nodes it appears in, and add them to the
		// token.
		const firstItemIndex =
			utterance.content.indexOf( items[ 0 ] );
		const itemsBeforeStart =
			utterance.content.slice( 0, firstItemIndex );
		let itemsBeforeStartLength = 0;
		itemsBeforeStart.forEach( ( item ) => {
			itemsBeforeStartLength += item.string.length;
		} );
		token.startOffset =
			startOffsetInUtteranceString -
			itemsBeforeStartLength;
		if ( token.items[ 0 ] === utterance.content[ 0 ] ) {
			token.startOffset += utterance.startOffset;
		}
		const lastItemIndex =
			utterance.content.indexOf(
				util.getLast( items )
			);
		const itemsBeforeEnd = utterance.content.slice( 0, lastItemIndex );
		let itemsBeforeEndLength = 0;
		itemsBeforeEnd.forEach( ( item ) => {
			itemsBeforeEndLength += item.string.length;
		} );
		token.endOffset =
			endOffsetInUtteranceString - itemsBeforeEndLength;
		if (
			util.getLast( token.items ) ===
				utterance.content[ 0 ]
		) {
			token.endOffset += utterance.startOffset;
		}
		return endOffsetInUtteranceString;
	}

	/**
	 * Calculate the start offset of a token in the utterance string.
	 *
	 * The token is the first match found, starting at
	 * searchOffset.
	 *
	 * @param {string} token The token to search for.
	 * @param {Object[]} content The content of the utterance where
	 *  the token appear.
	 * @param {Object[]} items An array of items to which each
	 *  item, up to and including the last one that contains
	 *  part of the token, is added.
	 * @param {number} searchOffset Where we want to start looking
	 *  for the token in the utterance string.
	 * @return {number} The offset where the first character of
	 *  the token appears in the utterance string.
	 */

	getStartOffsetInUtteranceString(
		token,
		content,
		items,
		searchOffset
	) {
		let startOffsetInUtteranceString, stringBeforeReplace;

		// The concatenation of the strings from items. Used to
		// find tokens that span multiple text nodes.
		let concatenatedText = '';
		content.every( ( item ) => {
			// Look through the items until we find a substring
			// matching the token.
			// The `replaceAll` replaces non-breaking space with a
			// normal space. This is required if Speechoid returns
			// normal spaces in "orth" for a token. See
			// https://phabricator.wikimedia.org/T286997
			concatenatedText += item.string.replace( ' ', ' ' );

			// Eslint does not allow replaceAll().
			do {
				stringBeforeReplace = concatenatedText;
				concatenatedText = concatenatedText.replace( ' ', ' ' );
			} while ( stringBeforeReplace !== concatenatedText );

			items.push( item );
			if ( searchOffset > concatenatedText.length ) {
				// Don't look in text elements that end before
				// where we start looking.
				// continue
				return true;
			}
			startOffsetInUtteranceString = concatenatedText.indexOf(
				token, searchOffset
			);
			if ( startOffsetInUtteranceString >= 0 ) {
				// break
				return false;
			}
			return true;
		} );
		return startOffsetInUtteranceString;
	}

	/**
	 * Get the utterance after the given utterance.
	 *
	 * @param {Object} utterance The original utterance.
	 * @return {Object} The utterance after the original
	 *  utterance. null if utterance is the last one.
	 */

	getNextUtterance( utterance ) {
		return this.getUtteranceByOffset( utterance, 1 );
	}

	/**
	 * Get the utterance by offset from another utterance.
	 *
	 * @param {Object} utterance The original utterance.
	 * @param {number} offset The difference, in index, to the
	 *  wanted utterance. Can be negative for preceding
	 *  utterances.
	 * @return {Object} The utterance on the position before or
	 *  after the original utterance, as specified by
	 *  `offset`. null if the original utterance is null.
	 */

	getUtteranceByOffset( utterance, offset ) {
		if ( utterance === null ) {
			return null;
		}
		const index = this.utterances.indexOf( utterance );
		return this.utterances[ index + offset ];
	}

	/**
	 * Get the utterance before the given utterance.
	 *
	 * @param {Object} utterance The original utterance.
	 * @return {Object} The utterance before the original
	 *  utterance. null if the original utterance is the
	 *  first one.
	 */

	getPreviousUtterance( utterance ) {
		return this.getUtteranceByOffset( utterance, -1 );
	}

	/**
	 * Get the token following a given token.
	 *
	 * @param {Object} originalToken Find the next token after
	 *  this one.
	 * @return {Object} The first token following originalToken
	 *  that has time greater than zero and a transcription. null
	 *  if no such token is found. Will not look beyond
	 *  originalToken's utterance.
	 */

	getNextToken( originalToken ) {
		const index = originalToken.utterance.tokens.indexOf( originalToken );
		const succeedingTokens =
			originalToken.utterance.tokens.slice( index + 1 ).filter(
				( token ) => !this.isSilent( token ) );
		if ( succeedingTokens.length === 0 ) {
			return null;
		} else {
			return succeedingTokens[ 0 ];
		}
	}

	/**
	 * Test if a token is silent.
	 *
	 * Silent is here defined as either having no transcription
	 * (i.e. the empty string) or having no duration (i.e. start
	 * and end time is the same).
	 *
	 * @param {Object} token The token to test.
	 * @return {boolean} true if the token is silent, else false.
	 */

	isSilent( token ) {
		return token.startTime === token.endTime ||
			token.string === '';
	}

	/**
	 * Get the token preceding a given token.
	 *
	 * @param {Object} originalToken Find the token before this one.
	 * @return {Object} The first token following originalToken
	 *  that has time greater than zero and a transcription. null
	 *  if no such token is found. Will not look beyond
	 *  originalToken's utterance.
	 */

	getPreviousToken( originalToken ) {
		const index = originalToken.utterance.tokens.indexOf( originalToken );
		const precedingTokens =
			originalToken.utterance.tokens.slice( 0, index ).filter(
				( token ) => !this.isSilent( token ) );
		if ( precedingTokens.length === 0 ) {
			return null;
		} else {
			const previousToken = util.getLast( precedingTokens );
			return previousToken;
		}
	}

	/**
	 * Get the last non silent token in an utterance.
	 *
	 * @param {Object} utterance The utterance to get the last
	 *  token from.
	 * @return {Object} The last token in the utterance.
	 */

	getLastToken( utterance ) {
		const nonSilentTokens = utterance.tokens.filter( ( token ) => !this.isSilent( token ) );
		const lastToken = util.getLast( nonSilentTokens );
		return lastToken;
	}

	/**
	 * Get the first text node that is a descendant of the given node.
	 *
	 * Finds the depth first text node, i.e. in
	 *  `<a><b>1</b>2</a>`
	 * the node with text "1" is the first one. If the given node is
	 * itself a text node, it is simply returned.
	 *
	 * @param {HTMLElement} node The node under which to look for
	 *  text nodes.
	 * @param {boolean} inUtterance If true, the first text node
	 *  that is also in an utterance is returned.
	 * @return {Text} The first text node under `node`,
	 *  undefined if there are no text nodes.
	 */

	getFirstTextNode( node, inUtterance ) {
		if ( node.nodeType === 3 ) {
			if ( !inUtterance || this.isNodeInUtterance( node ) ) {
				// The given node is a text node. Check whether
				// the node is in an utterance, if that is
				// requested.
				return node;
			}
		} else {
			for ( let i = 0; i < node.childNodes.length; i++ ) {
				// Check children if the given node is an element.
				const child = node.childNodes[ i ];
				const textNode = this.getFirstTextNode( child, inUtterance );
				if ( textNode ) {
					return textNode;
				}
			}
		}
	}

	/**
	 * Check if a text node is in any utterance.
	 *
	 * Utterances don't have any direct references to nodes, but
	 * rather use XPath expressions to find the nodes that were used
	 * when creating them.
	 *
	 * @param {Text} node The text node to check.
	 * @return {boolean} true if the node is in any utterance, else false.
	 */

	isNodeInUtterance( node ) {
		for ( let i = 0; i < this.utterances.length; i++ ) {
			const utterance = this.utterances[ i ];
			for ( let j = 0; j < utterance.content.length; j++ ) {
				const item = utterance.content[ j ];
				if ( this.getNodeForItem( item ) === node ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get the utterance containing a point, searching forward.
	 *
	 * Finds the utterance that contains a point in the text,
	 * specified by a node and an offset in that node. Several
	 * utterances may contain parts of the same node, which is why
	 * the offset is needed.
	 *
	 * If the offset can't be found in the given node, later nodes
	 * are checked. This happens if the offset falls between two
	 * utterances.
	 *
	 * @param {Text} node The first node to check.
	 * @param {number} offset The offset in the node.
	 * @return {Object} The matching utterance.
	 */

	getStartUtterance( node, offset ) {
		for ( ; offset < node.textContent.length; offset++ ) {
			for ( let i = 0; i < this.utterances.length; i++ ) {
				const utterance = this.utterances[ i ];
				if (
					this.isPointInItems(
						node,
						utterance.content,
						offset,
						utterance.startOffset,
						utterance.endOffset
					)
				) {
					return utterance;
				}
			}
		}
		// No match found in the given node, check the next one.
		const nextTextNode = this.getNextTextNode( node );
		return this.getStartUtterance( nextTextNode, 0 );
	}

	/**
	 * Check if a point in the text is in any of a number of items.
	 *
	 * Checks if a node is present in any of the items. When a
	 * matching item is found, checks if the offset falls between
	 * the given min and max values.
	 *
	 * @param {Text} node The node to check.
	 * @param {Object[]} items Item objects containing a path to
	 *  the node they were created from.
	 * @param {number} offset Offset in the node.
	 * @param {number} minOffset The minimum offset to be
	 *  considered a match.
	 * @param {number} maxOffset The maximum offset to be
	 *  considered a match.
	 */

	isPointInItems(
		node,
		items,
		offset,
		minOffset,
		maxOffset
	) {
		if ( items.length === 1 ) {
			const item = items[ 0 ];
			if (
				this.getNodeForItem( item ) === node &&
					offset >= minOffset &&
					offset <= maxOffset
			) {
				// Just check if the offset is within the min and
				// max offsets, if there is only one item.
				return true;
			}
		} else {
			for ( let i = 0; i < items.length; i++ ) {
				const item = items[ i ];
				if ( this.getNodeForItem( item ) !== node ) {
					// Skip items that don't match the node we're
					// looking for.
					continue;
				}
				const index = items.indexOf( item );
				if ( index === 0 ) {
					if ( offset >= minOffset ) {
						// For the first node, check if position is
						// after the start of the utterance.
						return true;
					}
				} else if ( index === items.length - 1 ) {
					if ( offset <= maxOffset ) {
						// For the last node, check if position is
						// before end of utterance.
						return true;
					}
				} else {
					// Any other node should be entirely within the
					// utterance.
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get the first text node after a given node.
	 *
	 * @param {HTMLElement|Text} node Get the text node after
	 * this one.
	 * @return {Text} The first node after `node`.
	 */

	getNextTextNode( node ) {
		const nextNode = node.nextSibling;
		if ( nextNode === null ) {
			// No more text nodes, start traversing the DOM
			// upward, checking sibling of ancestors.
			return this.getNextTextNode( node.parentNode );
		} else if ( nextNode.nodeType === 1 ) {
			// Node is an element, find the first text node in
			// it's children.
			for ( let i = 0; i < nextNode.childNodes.length; i++ ) {
				const child = nextNode.childNodes[ i ];
				const textNode = this.getFirstTextNode( child );
				if ( textNode ) {
					return textNode;
				}
			}
			return this.getNextTextNode( nextNode );
		} else if ( nextNode.nodeType === 3 ) {
			return nextNode;
		}
	}

	/**
	 * Get the token containing a point, searching forward.
	 *
	 * Finds the token that contains a point in the text,
	 * specified by a node and an offset in that node. Several
	 * tokens may contain parts of the same node, which is why
	 * the offset is needed.
	 *
	 * If the offset can't be found in the given node, later nodes
	 * are checked. This happens if the offset falls between two
	 * tokens.
	 *
	 * @param {Object} utterance The utterance to look for tokens in.
	 * @param {Text} node The node that contains the token.
	 * @param {number} offset The offset in the node.
	 * @param {Object} The first token found.
	 */

	getStartToken( utterance, node, offset ) {
		for ( ; offset < node.textContent.length; offset++ ) {
			for ( let i = 0; i < utterance.tokens.length; i++ ) {
				const token = utterance.tokens[ i ];
				if (
					this.isPointInItems(
						node,
						token.items,
						offset,
						token.startOffset,
						token.endOffset
					)
				) {
					return token;
				}
			}
		}
		// If token wasn't found in the given node, check the next
		// one.
		const nextTextNode = this.getNextTextNode( node );
		return this.getStartToken( utterance, nextTextNode, 0 );
	}

	/**
	 * Get the last text node that is a descendant of given node.
	 *
	 * Finds the depth first text node, i.e. in
	 *  `<a>1<b>2</b></a>`
	 * the node with text "2" is the last one. If the given node
	 * is itself a text node, it is simply returned.
	 *
	 * @param {HTMLElement} node The node under which to look for
	 *  text nodes.
	 * @param {boolean} inUtterance If true, the last text node
	 *  that is also in an utterance is returned.
	 * @return {Text} The last text node under `node`,
	 *  undefined if there are no text nodes.
	 */

	getLastTextNode( node, inUtterance ) {
		if ( node.nodeType === 3 ) {
			if ( !inUtterance || this.isNodeInUtterance( node ) ) {
				// The given node is a text node. Check whether
				// the node is in an utterance, if that is
				// requested.
				return node;
			}
		} else {
			for ( let i = node.childNodes.length - 1; i >= 0; i-- ) {
				// Check children if the given node is an element.
				const child = node.childNodes[ i ];
				const textNode = this.getLastTextNode( child, inUtterance );
				if ( textNode ) {
					return textNode;
				}
			}
		}
	}

	/**
	 * Get the utterance containing a point, searching backward.
	 *
	 * Finds the utterance that contains a point in the text,
	 * specified by a node and an offset in that node. Several
	 * utterances may contain parts of the same node, which is why
	 * the offset is needed.
	 *
	 * If the offset can't be found in the given node, preceding
	 * nodes are checked. This happens if the offset falls between
	 * two utterances.
	 *
	 * @param {Text} node The first node to check.
	 * @param {number} offset The offset in the node.
	 * @return {Object} The matching utterance.
	 */

	getEndUtterance( node, offset ) {
		for ( ; offset >= 0; offset-- ) {
			for ( let i = 0; i < this.utterances.length; i++ ) {
				const utterance = this.utterances[ i ];
				if (
					this.isPointInItems(
						node,
						utterance.content,
						offset,
						utterance.startOffset,
						utterance.endOffset
					)
				) {
					return utterance;
				}
			}
		}
		const previousTextNode = this.getPreviousTextNode( node );
		return this.getEndUtterance(
			previousTextNode,
			previousTextNode.textContent.length
		);
	}

	/**
	 * Get the first text node before a given node.
	 *
	 * @param {HTMLElement|Text} node Get the text node before
	 *  this one.
	 * @return {Text} The first node before `node`.
	 */

	getPreviousTextNode( node ) {
		const previousNode = node.previousSibling;
		if ( previousNode === null ) {
			return this.getPreviousTextNode( node.parentNode );
		} else if ( previousNode.nodeType === 1 ) {
			for ( let i = previousNode.childNodes.length - 1; i >= 0; i-- ) {
				const child = previousNode.childNodes[ i ];
				const textNode = this.getLastTextNode( child );
				if ( textNode ) {
					return textNode;
				}
			}
			return this.getPreviousTextNode( previousNode );
		} else if ( previousNode.nodeType === 3 ) {
			return previousNode;
		}
	}

	/**
	 * Get the token containing a point, searching backward.
	 *
	 * Finds the token that contains a point in the text,
	 * specified by a node and an offset in that node. Several
	 * tokens may contain parts of the same node, which is why
	 * the offset is needed.
	 *
	 * If the offset can't be found in the given node, preceding
	 * nodes are checked. This happens if the offset falls between
	 * two tokens.
	 *
	 * @param {Object} utterance The utterance to look for tokens in.
	 * @param {Text} node The node that contains the token.
	 * @param {number} offset The offset in the node.
	 * @param {Object} The first token found.
	 */

	getEndToken( utterance, node, offset ) {
		for ( ; offset >= 0; offset-- ) {
			for ( let i = 0; i < utterance.tokens.length; i++ ) {
				const token = utterance.tokens[ i ];
				if (
					this.isPointInItems(
						node,
						token.items,
						offset,
						token.startOffset,
						token.endOffset
					)
				) {
					return token;
				}
			}
		}
		const previousTextNode = this.getPreviousTextNode( node );
		return this.getEndToken(
			utterance,
			previousTextNode,
			previousTextNode.textContent.length
		);
	}

	/**
	 * Find the text node from which a content item was created.
	 *
	 * The path property of the item is an XPath expression
	 * that is used to traverse the DOM tree.
	 *
	 * @param {Object} item The item to find the text node for.
	 * @return {Text} The text node associated with the item.
	 */

	getNodeForItem( item ) {
		if ( item.path === null ) {
			return null;
		}

		// The path should be unambiguous, so just get the first
		// matching node.
		const contentSelector = mw.config.get( 'wgWikispeechContentSelector' );
		const result = document.evaluate(
			item.path,
			$( contentSelector ).get( 0 ),
			null,
			XPathResult.FIRST_ORDERED_NODE_TYPE,
			null
		);
		const node = result.singleNodeValue;
		return node;
	}
}

module.exports = Storage;
