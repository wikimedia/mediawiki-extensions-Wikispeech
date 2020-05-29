( function ( mw, $ ) {

	/**
	 * Loads and stores objects used by the extension.
	 *
	 * Contains functions for other modules to retrieve information
	 * about the utterances.
	 *
	 * @class ext.wikispeech.Storage
	 * @constructor
	 */

	function Storage() {
		var self;

		self = this;
		self.utterances = [];

		/**
		 * Load all utterances.
		 *
		 * Uses the MediaWiki API to get the segments of the text.
		 */

		this.loadUtterances = function () {
			var api, page;

			api = new mw.Api();
			page = mw.config.get( 'wgPageName' );
			api.post(
				{
					action: 'wikispeech',
					page: [ page ]
				},
				{
					beforeSend: function ( jqXHR, settings ) {
						mw.log(
							'Requesting segments:', settings.url + '?' +
								settings.data
						);
					}
				}
			).done( function ( data ) {
				var utterance, i;

				mw.log( 'Segments received:', data );
				self.utterances = data.wikispeech.segments;
				for ( i = 0; i < self.utterances.length; i++ ) {
					utterance = self.utterances[ i ];
					utterance.audio = $( '<audio></audio>' ).get( 0 );
				}
				self.prepareUtterance( self.utterances[ 0 ] );
			} );
		};

		/**
		 * Prepare an utterance for playback.
		 *
		 * Audio for the utterance is requested from the Speechoid service
		 * and event listeners are added. When an utterance starts
		 * playing, the next one is prepared, and when an utterance is
		 * done, the next utterance is played. This is meant to be a
		 * balance between not having to pause between utterance and
		 * not requesting more than needed.

		 * @param {Object} utterance The utterance to prepare.
		 * @param {Function} callback A function to call when the
		 *  utterance is ready to play. Fires immediately if the
		 *  utterance has already been prepared.
		 */

		this.prepareUtterance = function ( utterance, callback ) {
			var $audio, nextUtterance;

			$audio = $( utterance.audio );
			if ( $audio.attr( 'src' ) ) {
				if ( callback ) {
					// Audio already loaded, call callback, if any.
					callback();
				}
			} else if ( utterance.request ) {
				// Request is ongoing, add callback to fire when it's
				// done.
				utterance.request.done( callback );
			} else {
				// Only load audio for an utterance if it isn't
				// already loaded or waiting for response from Speechoid.
				self.loadAudio( utterance, callback );
				nextUtterance = self.getNextUtterance( utterance );
				$audio.on( {
					playing: function () {
						var firstToken;

						// Highlight token only when the audio starts
						// playing, since we need the token info from
						// the response to know what to highlight.
						if (
							!mw.wikispeech.player.playingSelection &&
								$audio.prop( 'currentTime' ) === 0
						) {
							firstToken = utterance.tokens[ 0 ];
							mw.wikispeech.highlighter.startTokenHighlighting(
								firstToken
							);
						}
					}
				} );
				if ( nextUtterance ) {
					$audio.on( {
						play: function () {
							self.prepareUtterance( nextUtterance );
						},
						ended: function () {
							mw.wikispeech.player.skipAheadUtterance();
						}
					} );
				} else {
					// For last utterance, just stop the playback when
					// done.
					$audio.on( 'ended', function () {
						mw.wikispeech.player.stop();
					} );
				}
			}
		};

		/**
		 * Load audio for an utterance.
		 *
		 * Sends a request to the Speechoid service and adds audio and tokens
		 * when the response is received.
		 *
		 * @param {Object} utterance The utterance to load audio for.
		 * @param {Function} callback Function to call when audio is loaded.
		 */

		this.loadAudio = function ( utterance, callback ) {
			var text, audioUrl, utteranceIndex;

			utteranceIndex = self.utterances.indexOf( utterance );
			mw.log(
				'Loading audio for utterance #' + utteranceIndex + ':',
				utterance
			);
			text = '';
			utterance.content.forEach( function ( item ) {
				text += item.string;
			} );
			utterance.request = self.requestTts( text );
			utterance.request.done( function ( response ) {
				audioUrl = response.wikispeechlisten.audio;
				mw.log(
					'Setting audio url for: [' + utteranceIndex + ']',
					utterance, '=', audioUrl
				);
				utterance.audio.setAttribute( 'src', audioUrl );
				utterance.audio.playbackRate =
					mw.user.options.get( 'wikispeechSpeechRate' );

				self.addTokens( utterance, response.wikispeechlisten.tokens );
				if ( callback ) {
					callback();
				}
			} )
				.always( function () {
					// Remove request on success or failure, to allow new
					// requests if this one fails.
					utterance.request = null;
				} );
		};

		/**
		 * Send a request to the Speechoid service.
		 *
		 * Request is sent via the "wikispeechlisten" API action. Language to
		 * use is retrieved from the current page.
		 *
		 * @param {string} text The utterance string to send in the
		 *  request.
		 * @return {jQuery.Promise}
		 */

		this.requestTts = function ( text ) {
			var request, language, voiceKey, voice, options, api;

			language = mw.config.get( 'wgPageContentLanguage' );
			// Capitalize first letter in language code.
			voiceKey = 'wikispeechVoice' +
				language[ 0 ].toUpperCase() +
				language.slice( 1 );
			voice = mw.user.options.get( voiceKey );
			options = {
				action: 'wikispeechlisten',
				lang: language,
				input: text
			};
			if ( voice !== '' ) {
				// Set voice if not default.
				options.voice = voice;
			}
			api = new mw.Api();
			request = api.post(
				options,
				{
					beforeSend: function ( jqXHR, settings ) {
						mw.log(
							'Sending TTS request: ' + settings.url + '?' +
								settings.data
						);
					}
				}
			)
				.done( function ( data ) {
					mw.log( 'Response received:', data );
				} )
				.fail( function ( jqXHR, textStatus ) {
					mw.log.warn(
						'Request failed, error type "' + textStatus + '":',
						this.url + '?' + this.data
					);
				} );
			return request;
		};

		/**
		 * Add tokens to an utterance.
		 *
		 * @param {Object} utterance The utterance to add tokens to.
		 * @param {Object[]} responseTokens Tokens from a Speechoid response,
		 *  where each token is an object. For these objects, the
		 *  property "orth" is the string used by the TTS to generate
		 *  audio for the token.
		 */

		this.addTokens = function ( utterance, responseTokens ) {
			var i, token, startTime, searchOffset, responseToken;

			utterance.tokens = [];
			searchOffset = 0;
			for ( i = 0; i < responseTokens.length; i++ ) {
				responseToken = responseTokens[ i ];
				if ( i === 0 ) {
					// The first token in an utterance always start on
					// time zero.
					startTime = 0.0;
				} else {
					// Since the response only contains end times for
					// token, the start time for a token is set to the
					// end time of the previous one.
					startTime = responseTokens[ i - 1 ].endtime;
				}
				token = {
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
				searchOffset = self.addOffsetsAndItems(
					token,
					searchOffset
				);
			}
		};

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

		this.addOffsetsAndItems = function (
			token,
			searchOffset
		) {
			var startOffsetInUtteranceString,
				endOffsetInUtteranceString, endOffsetForItem,
				firstItemIndex, itemsBeforeStart, lastItemIndex,
				itemsBeforeEnd, items, itemsBeforeStartLength,
				itemsBeforeEndLength, utterance;

			utterance = token.utterance;
			items = [];
			startOffsetInUtteranceString =
				self.getStartOffsetInUtteranceString(
					token.string,
					utterance.content,
					items,
					searchOffset
				);
			endOffsetInUtteranceString =
				startOffsetInUtteranceString +
				token.string.length - 1;

			// `items` now contains all the items in the utterance,
			// from the first one to the last, that contains at least
			// part of the token. To get only the ones that contain
			// part of the token, the items that appear before the
			// token are removed.
			endOffsetForItem = 0;
			items =
				items.filter( function ( item ) {
					endOffsetForItem += item.string.length;
					return endOffsetForItem >
						startOffsetInUtteranceString;
				} );
			token.items = items;

			// Calculate start and end offset for the token, in the
			// text nodes it appears in, and add them to the
			// token.
			firstItemIndex =
				utterance.content.indexOf( items[ 0 ] );
			itemsBeforeStart =
				utterance.content.slice( 0, firstItemIndex );
			itemsBeforeStartLength = 0;
			itemsBeforeStart.forEach( function ( item ) {
				itemsBeforeStartLength += item.string.length;
			} );
			token.startOffset =
				startOffsetInUtteranceString -
				itemsBeforeStartLength;
			if ( token.items[ 0 ] === utterance.content[ 0 ] ) {
				token.startOffset += utterance.startOffset;
			}
			lastItemIndex =
				utterance.content.indexOf(
					mw.wikispeech.util.getLast( items )
				);
			itemsBeforeEnd = utterance.content.slice( 0, lastItemIndex );
			itemsBeforeEndLength = 0;
			itemsBeforeEnd.forEach( function ( item ) {
				itemsBeforeEndLength += item.string.length;
			} );
			token.endOffset =
				endOffsetInUtteranceString - itemsBeforeEndLength;
			if (
				mw.wikispeech.util.getLast( token.items ) ===
					utterance.content[ 0 ]
			) {
				token.endOffset += utterance.startOffset;
			}
			return endOffsetInUtteranceString;
		};

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

		this.getStartOffsetInUtteranceString = function (
			token,
			content,
			items,
			searchOffset
		) {
			var concatenatedText, startOffsetInUtteranceString;

			// The concatenation of the strings from items. Used to
			// find tokens that span multiple text nodes.
			concatenatedText = '';
			$.each( content, function () {
				// Look through the items until we find a
				// substring matching the token.
				concatenatedText += this.string;
				items.push( this );
				if ( searchOffset > concatenatedText.length ) {
					// Don't look in text elements that end before
					// where we start looking.
					return;
				}
				startOffsetInUtteranceString = concatenatedText.indexOf(
					token, searchOffset
				);
				if ( startOffsetInUtteranceString >= 0 ) {
					return false;
				}
			} );
			return startOffsetInUtteranceString;
		};

		/**
		 * Get the utterance after the given utterance.
		 *
		 * @param {Object} utterance The original utterance.
		 * @return {Object} The utterance after the original
		 *  utterance. null if utterance is the last one.
		 */

		this.getNextUtterance = function ( utterance ) {
			return self.getUtteranceByOffset( utterance, 1 );
		};

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

		this.getUtteranceByOffset = function ( utterance, offset ) {
			var index;

			if ( utterance === null ) {
				return null;
			}
			index = self.utterances.indexOf( utterance );
			return self.utterances[ index + offset ];
		};

		/**
		 * Get the utterance before the given utterance.
		 *
		 * @param {Object} utterance The original utterance.
		 * @return {Object} The utterance before the original
		 *  utterance. null if the original utterance is the
		 *  first one.
		 */

		this.getPreviousUtterance = function ( utterance ) {
			return self.getUtteranceByOffset( utterance, -1 );
		};

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

		this.getNextToken = function ( originalToken ) {
			var index, succeedingTokens;

			index = originalToken.utterance.tokens.indexOf( originalToken );
			succeedingTokens =
				originalToken.utterance.tokens.slice( index + 1 ).filter(
					function ( token ) {
						return !self.isSilent( token );
					} );
			if ( succeedingTokens.length === 0 ) {
				return null;
			} else {
				return succeedingTokens[ 0 ];
			}
		};

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

		this.isSilent = function ( token ) {
			return token.startTime === token.endTime ||
				token.string === '';
		};

		/**
		 * Get the token preceding a given token.
		 *
		 * @param {Object} originalToken Find the token before this one.
		 * @return {Object} The first token following originalToken
		 *  that has time greater than zero and a transcription. null
		 *  if no such token is found. Will not look beyond
		 *  originalToken's utterance.
		 */

		this.getPreviousToken = function ( originalToken ) {
			var index, precedingTokens, previousToken;

			index = originalToken.utterance.tokens.indexOf( originalToken );
			precedingTokens =
				originalToken.utterance.tokens.slice( 0, index ).filter(
					function ( token ) {
						return !self.isSilent( token );
					} );
			if ( precedingTokens.length === 0 ) {
				return null;
			} else {
				previousToken = mw.wikispeech.util.getLast( precedingTokens );
				return previousToken;
			}
		};

		/**
		 * Get the last non silent token in an utterance.
		 *
		 * @param {Object} utterance The utterance to get the last
		 *  token from.
		 * @return {Object} The last token in the utterance.
		 */

		this.getLastToken = function ( utterance ) {
			var nonSilentTokens, lastToken;

			nonSilentTokens = utterance.tokens.filter( function ( token ) {
				return !self.isSilent( token );
			} );
			lastToken = mw.wikispeech.util.getLast( nonSilentTokens );
			return lastToken;
		};

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
		 * @return {TextNode} The first text node under `node`,
		 *  undefined if there are no text nodes.
		 */

		this.getFirstTextNode = function ( node, inUtterance ) {
			var textNode, child, i;

			if ( node.nodeType === 3 ) {
				if ( !inUtterance || self.isNodeInUtterance( node ) ) {
					// The given node is a text node. Check whether
					// the node is in an utterance, if that is
					// requested.
					return node;
				}
			} else {
				for ( i = 0; i < node.childNodes.length; i++ ) {
					// Check children if the given node is an element.
					child = node.childNodes[ i ];
					textNode = self.getFirstTextNode( child, inUtterance );
					if ( textNode ) {
						return textNode;
					}
				}
			}
		};

		/**
		 * Check if a text node is in any utterance.
		 *
		 * Utterances don't have any direct references to nodes, but
		 * rather use XPath expressions to find the nodes that were used
		 * when creating them.
		 *
		 * @param {TextNode} node The text node to check.
		 * @return {boolean} true if the node is in any utterance, else false.
		 */

		this.isNodeInUtterance = function ( node ) {
			var utterance, item, i, j;

			for (
				i = 0;
				i < self.utterances.length;
				i++
			) {
				utterance = self.utterances[ i ];
				for ( j = 0; j < utterance.content.length; j++ ) {
					item = utterance.content[ j ];
					if ( self.getNodeForItem( item ) === node ) {
						return true;
					}
				}
			}
			return false;
		};

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
		 * @param {TextNode} node The first node to check.
		 * @param {number} offset The offset in the node.
		 * @return {Object} The matching utterance.
		 */

		this.getStartUtterance = function ( node, offset ) {
			var utterance, i, nextTextNode;

			for ( ; offset < node.textContent.length; offset++ ) {
				for (
					i = 0;
					i < self.utterances.length;
					i++
				) {
					utterance = self.utterances[ i ];
					if (
						self.isPointInItems(
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
			nextTextNode = self.getNextTextNode( node );
			return self.getStartUtterance( nextTextNode, 0 );
		};

		/**
		 * Check if a point in the text is in any of a number of items.
		 *
		 * Checks if a node is present in any of the items. When a
		 * matching item is found, checks if the offset falls between
		 * the given min and max values.
		 *
		 * @param {TextNode} node The node to check.
		 * @param {Object[]} items Item objects containing a path to
		 *  the node they were created from.
		 * @param {number} offset Offset in the node.
		 * @param {number} minOffset The minimum offset to be
		 *  considered a match.
		 * @param {number} maxOffset The maximum offset to be
		 *  considered a match.
		 */

		this.isPointInItems = function (
			node,
			items,
			offset,
			minOffset,
			maxOffset
		) {
			var item, i, index;

			if ( items.length === 1 ) {
				item = items[ 0 ];
				if (
					self.getNodeForItem( item ) === node &&
						offset >= minOffset &&
						offset <= maxOffset
				) {
					// Just check if the offset is within the min and
					// max offsets, if there is only one item.
					return true;
				}
			} else {
				for ( i = 0; i < items.length; i++ ) {
					item = items[ i ];
					if ( self.getNodeForItem( item ) !== node ) {
						// Skip items that don't match the node we're
						// looking for.
						continue;
					}
					index = items.indexOf( item );
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
		};

		/**
		 * Get the first text node after a given node.
		 *
		 * @param {HTMLElement|TextNode} node Get the text node after
		 * this one.
		 * @return {TextNode} The first node after `node`.
		 */

		this.getNextTextNode = function ( node ) {
			var nextNode, textNode, child, i;

			nextNode = node.nextSibling;
			if ( nextNode === null ) {
				// No more text nodes, start traversing the DOM
				// upward, checking sibling of ancestors.
				return self.getNextTextNode( node.parentNode );
			} else if ( nextNode.nodeType === 1 ) {
				// Node is an element, find the first text node in
				// it's children.
				for ( i = 0; i < nextNode.childNodes.length; i++ ) {
					child = nextNode.childNodes[ i ];
					textNode = self.getFirstTextNode( child );
					if ( textNode ) {
						return textNode;
					}
				}
				return self.getNextTextNode( nextNode );
			} else if ( nextNode.nodeType === 3 ) {
				return nextNode;
			}
		};

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
		 * @param {TextNode} node The node that contains the token.
		 * @param {number} offset The offset in the node.
		 * @param {Object} The first token found.
		 */

		this.getStartToken = function ( utterance, node, offset ) {
			var token, i, nextTextNode;

			for ( ; offset < node.textContent.length; offset++ ) {
				for ( i = 0; i < utterance.tokens.length; i++ ) {
					token = utterance.tokens[ i ];
					if (
						self.isPointInItems(
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
			nextTextNode = self.getNextTextNode( node );
			return self.getStartToken( utterance, nextTextNode, 0 );
		};

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
		 * @return {TextNode} The last text node under `node`,
		 *  undefined if there are no text nodes.
		 */

		this.getLastTextNode = function ( node, inUtterance ) {
			var i, child, textNode;

			if ( node.nodeType === 3 ) {
				if ( !inUtterance || self.isNodeInUtterance( node ) ) {
					// The given node is a text node. Check whether
					// the node is in an utterance, if that is
					// requested.
					return node;
				}
			} else {
				for ( i = node.childNodes.length - 1; i >= 0; i-- ) {
					// Check children if the given node is an element.
					child = node.childNodes[ i ];
					textNode = self.getLastTextNode( child, inUtterance );
					if ( textNode ) {
						return textNode;
					}
				}
			}
		};

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
		 * @param {TextNode} node The first node to check.
		 * @param {number} offset The offset in the node.
		 * @return {Object} The matching utterance.
		 */

		this.getEndUtterance = function ( node, offset ) {
			var utterance, i, previousTextNode;

			for ( ; offset >= 0; offset-- ) {
				for (
					i = 0;
					i < self.utterances.length;
					i++
				) {
					utterance = self.utterances[ i ];
					if (
						self.isPointInItems(
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
			previousTextNode = self.getPreviousTextNode( node );
			return self.getEndUtterance(
				previousTextNode,
				previousTextNode.textContent.length
			);
		};

		/**
		 * Get the first text node before a given node.
		 *
		 * @param {HTMLElement|TextNode} node Get the text node before
		 *  this one.
		 * @return {TextNode} The first node before `node`.
		 */

		this.getPreviousTextNode = function ( node ) {
			var previousNode, i, child, textNode;

			previousNode = node.previousSibling;
			if ( previousNode === null ) {
				return self.getPreviousTextNode( node.parentNode );
			} else if ( previousNode.nodeType === 1 ) {
				for (
					i = previousNode.childNodes.length - 1;
					i >= 0;
					i--
				) {
					child = previousNode.childNodes[ i ];
					textNode = self.getLastTextNode( child );
					if ( textNode ) {
						return textNode;
					}
				}
				return self.getPreviousTextNode( previousNode );
			} else if ( previousNode.nodeType === 3 ) {
				return previousNode;
			}
		};

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
		 * @param {TextNode} node The node that contains the token.
		 * @param {number} offset The offset in the node.
		 * @param {Object} The first token found.
		 */

		this.getEndToken = function ( utterance, node, offset ) {
			var token, i, previousTextNode;

			for ( ; offset >= 0; offset-- ) {
				for ( i = 0; i < utterance.tokens.length; i++ ) {
					token = utterance.tokens[ i ];
					if (
						self.isPointInItems(
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
			previousTextNode = self.getPreviousTextNode( node );
			return self.getEndToken(
				utterance,
				previousTextNode,
				previousTextNode.textContent.length
			);
		};

		/**
		 * Find the text node from which a content item was created.
		 *
		 * The path property of the item is an XPath expression
		 * that is used to traverse the DOM tree.
		 *
		 * @param {Object} item The item to find the text node for.
		 * @return {TextNode} The text node associated with the item.
		 */

		this.getNodeForItem = function ( item ) {
			var node, result, contentSelector;

			// The path should be unambiguous, so just get the first
			// matching node.
			contentSelector = mw.config.get( 'wgWikispeechContentSelector' );
			result = document.evaluate(
				item.path,
				$( contentSelector ).get( 0 ),
				null,
				XPathResult.FIRST_ORDERED_NODE_TYPE,
				null
			);
			node = result.singleNodeValue;
			return node;
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.Storage = Storage;
	mw.wikispeech.storage = new Storage();
}( mediaWiki, jQuery ) );
