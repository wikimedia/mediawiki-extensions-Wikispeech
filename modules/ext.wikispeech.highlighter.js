( function ( mw, $ ) {

	/**
	 * Handles highlighting parts of the page when reciting.
	 *
	 * @class ext.wikispeech.Highlighter
	 * @constructor
	 */

	function Highlighter() {
		var self = this;
		self.highlightTokenTimer = null;
		self.utteranceHighlightingClass =
			'ext-wikispeech-highlight-sentence';
		self.utteranceHighlightingSelector =
			'.' + self.utteranceHighlightingClass;

		/**
		 * Highlight text associated with an utterance.
		 *
		 * Adds highlight spans to the text nodes from which the
		 * tokens of the utterance were created. For first and last node,
		 * it's possible that only part of the text is highlighted,
		 * since they may contain start/end of next/previous
		 * utterance.
		 *
		 * @param {Object} utterance The utterance to add
		 *  highlighting to.
		 */

		this.highlightUtterance = function ( utterance ) {
			var textNodes, span;

			textNodes = utterance.content.map( function ( item ) {
				return mw.wikispeech.storage.getNodeForItem( item );
			} );
			span = $( '<span></span>' )
				.addClass( self.utteranceHighlightingClass )
				.get( 0 );
			self.wrapTextNodes(
				span,
				textNodes,
				utterance.startOffset,
				utterance.endOffset
			);
			$( self.utteranceHighlightingSelector ).each( function ( i ) {
				// Save the path to the text node, as it was before
				// adding the span. This will no longer be the correct
				// path, once the span is added. This enables adding
				// token highlighting within the utterance
				// highlighting.
				this.textPath = utterance.content[ i ].path;
			} );
		};

		/**
		 * Wrap text nodes in an element.
		 *
		 * Each text node is wrapped in an individual copy of the
		 * wrapper element. The first and last node will be partially
		 * wrapped, based on the offset values.
		 *
		 * @param {HTMLElement} wrapper The element used to wrap the
		 *  text nodes.
		 * @param {TextNode[]} textNodes The text nodes to wrap.
		 * @param {number} startOffset The start offset in the first
		 *  text node.
		 * @param {number} endOffset The end offset in the last text
		 *  node.
		 */

		this.wrapTextNodes = function (
			wrapper,
			textNodes,
			startOffset,
			endOffset
		) {
			var $nodesToWrap, firstNode, i, lastNode, node;

			$nodesToWrap = $();
			firstNode = textNodes[ 0 ];
			if ( textNodes.length === 1 ) {
				// If there is only one node that should be wrapped,
				// split it twice; once for the start and once for the
				// end offset.
				firstNode.splitText( startOffset );
				firstNode.nextSibling.splitText( endOffset + 1 - startOffset );
				$nodesToWrap = $nodesToWrap.add( firstNode.nextSibling );
			} else {
				firstNode.splitText( startOffset );
				// The first half of a split node remains as the
				// original node. Since we want the second half, we add
				// the following node.
				$nodesToWrap = $nodesToWrap.add( firstNode.nextSibling );
				for ( i = 1; i < textNodes.length - 1; i++ ) {
					node = textNodes[ i ];
					// Wrap all the nodes between first and last
					// completely.
					$nodesToWrap = $nodesToWrap.add( node );
				}
				lastNode = textNodes[ textNodes.length - 1 ];
				lastNode.splitText( endOffset + 1 );
				$nodesToWrap = $nodesToWrap.add( lastNode );
			}
			$nodesToWrap.wrap( wrapper );
		};

		/**
		 * Highlight a token in the original HTML.
		 *
		 * What part of the HTML to wrap is calculated from a token.
		 *
		 * @param {Object} token The token used to calculate what part
		 *  to highlight.
		 */

		this.startTokenHighlighting = function ( token ) {
			self.removeWrappers( '.ext-wikispeech-highlight-word' );
			self.clearHighlightTokenTimer();
			self.highlightToken( token );
			self.setHighlightTokenTimer( token );
		};

		/**
		 * Highlight a token in the original HTML.
		 *
		 * What part of the HTML to wrap is calculated from a token.
		 *
		 * @param {Object} token The token used to calculate what part
		 *  to highlight.
		 */

		this.highlightToken = function ( token ) {
			var span, textNodes, startOffset, endOffset;

			span = $( '<span></span>' )
				.addClass( 'ext-wikispeech-highlight-word' )
				.get( 0 );
			textNodes = token.items.map( function ( item ) {
				var textNode;

				if ( $( self.utteranceHighlightingSelector ).length ) {
					// Add the token highlighting within the
					// utterance highlightings, if there are any.
					textNode = self.getNodeInUtteranceHighlighting(
						item
					);
				} else {
					textNode = mw.wikispeech.storage.getNodeForItem( item );
				}
				return textNode;
			} );
			startOffset = token.startOffset;
			endOffset = token.endOffset;
			if (
				$( self.utteranceHighlightingSelector ).length &&
					token.items[ 0 ] === token.utterance.content[ 0 ]
			) {
				// Modify the offset if the token is the first in the
				// utterance and there is an utterance
				// highlighting. The text node may have been split
				// when the utterance highlighting was applied.
				startOffset -= token.utterance.startOffset;
				endOffset -= token.utterance.startOffset;
			}
			self.wrapTextNodes(
				span,
				textNodes,
				startOffset,
				endOffset
			);
		};

		/**
		 * Get text node, within utterance highlighting, for an item.
		 *
		 * @param {Object} item The item to get text node for.
		 */

		this.getNodeInUtteranceHighlighting = function ( item ) {
			// Get the text node from the utterance highlighting that
			// wrapped the node for `textElement`.
			var textNode = $( self.utteranceHighlightingSelector )
				.filter( function () {
					return this.textPath ===
						item.path;
				} )
				.contents()
				.get( 0 );
			return textNode;
		};

		/**
		 * Set a timer for when the next token should be highlighted.
		 *
		 * @param {Object} token The original token. The timer is set
		 *  for the token following this one.
		 */

		this.setHighlightTokenTimer = function ( token ) {
			var currentTime, duration, nextToken;

			currentTime = token.utterance.audio.currentTime;
			// The duration of the timer is the duration of the
			// current token.
			duration = token.endTime - currentTime;
			nextToken = mw.wikispeech.storage.getNextToken( token );
			if ( nextToken ) {
				self.highlightTokenTimer = window.setTimeout(
					function () {
						self.removeWrappers(
							'.ext-wikispeech-highlight-word'
						);
						self.highlightToken( nextToken );
						// Add a new timer for the next token, when it
						// starts playing.
						self.setHighlightTokenTimer( nextToken );
					},
					duration * 1000 /
						mw.user.options.get( 'wikispeechSpeechRate' )
				);
			}
		};

		/**
		 * Remove elements wrapping text nodes.
		 *
		 * Restores the text nodes to the way they were before they
		 * were wrapped.
		 *
		 * @param {string} wrapperSelector The selector for the
		 *  elements to remove
		 */

		this.removeWrappers = function ( wrapperSelector ) {
			var parents, $span;

			parents = [];
			$span = $( wrapperSelector );
			$span.each( function () {
				parents.push( this.parentNode );
			} );
			$span.contents().unwrap();
			if ( parents.length > 0 ) {
				// Merge first and last text nodes, if the original was
				// divided by adding the <span>.
				parents[ 0 ].normalize();
				parents[ parents.length - 1 ].normalize();
			}
		};

		/**
		 * Remove any sentence and word highlighting.
		 */

		this.clearHighlighting = function () {
			// Remove sentence highlighting.
			self.removeWrappers( '.ext-wikispeech-highlight-sentence' );
			// Remove word highlighting.
			self.removeWrappers( '.ext-wikispeech-highlight-word' );
			self.clearHighlightTokenTimer();
		};

		/**
		 * Clear the timer for highlighting tokens.
		 */

		this.clearHighlightTokenTimer = function () {
			clearTimeout( self.highlightTokenTimer );
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.highlighter = new Highlighter();
	mw.wikispeech.Highlighter = Highlighter;
}( mediaWiki, jQuery ) );
