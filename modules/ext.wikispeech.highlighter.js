( function ( mw, $ ) {
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
		 * tokens of $utterance were created. For first and last node,
		 * it's possible that only part of the text is highlighted,
		 * since they may contain start/end of next/previous
		 * utterance.
		 *
		 * @param {jQuery} $utterance The utterance to add
		 *  highlighting to.
		 */

		this.highlightUtterance = function ( $utterance ) {
			var startOffset, endOffset, span, textNodes;

			startOffset =
				parseInt( $utterance.attr( 'start-offset' ), 10 );
			endOffset =
				parseInt( $utterance.attr( 'end-offset' ), 10 );
			textNodes = $utterance.find( 'text' ).map( function () {
				return self.getNodeForTextElement( this );
			} ).get();
			span = $( '<span></span>' )
				.addClass( self.utteranceHighlightingClass )
				.get( 0 );
			self.wrapTextNodes(
				span,
				textNodes,
				startOffset,
				endOffset
			);
			$( self.utteranceHighlightingSelector ).each( function ( i ) {
				// Save the path to the text node, as it was before
				// adding the span. This will no longer be the correct
				// path, once the span is added. This enables adding
				// token highlighting within the utterance
				// highlighting.
				this.textPath = $utterance
					.find( 'text' )
					.get( i )
					.getAttribute( 'path' );
			} );
		};

		/**
		 * Find the text node from which a `<text>` element was created.
		 *
		 * The path attribute of textElement is an XPath expression
		 * and is used to traverse the DOM tree.
		 *
		 * @param {HTMLElement} textElement The `<text>` element to find
		 *  the text node for.
		 * @return {TextNode} The text node associated with textElement.
		 */

		this.getNodeForTextElement = function ( textElement ) {
			var path, node, result, contentWrapperSelector;

			path = textElement.getAttribute( 'path' );
			contentWrapperSelector =
				'.' + mw.config.get( 'wgWikispeechContentWrapperClass' );
			// The path should be unambiguous, so just get the first
			// matching node.
			result = document.evaluate(
				path,
				$( contentWrapperSelector ).get( 0 ),
				null,
				XPathResult.FIRST_ORDERED_NODE_TYPE,
				null
			);
			node = result.singleNodeValue;
			return node;
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
		 * What part of the HTML to wrap is calculated from a token
		 * element.
		 *
		 * @param {HTMLElement} tokenElement The token element used to
		 *  calculate what part to highlight.
		 */

		this.highlightToken = function ( tokenElement ) {
			var span, textNodes, $utterance, utteranceOffset;

			span = $( '<span></span>' )
				.addClass( 'ext-wikispeech-highlight-word' )
				.get( 0 );
			textNodes = tokenElement.textElements.map(
				function ( textElement ) {
					var textNode;

					if ( $( self.utteranceHighlightingSelector ).length ) {
						// Add the the token highlighting within the
						// utterance highlightings, if there are any.
						textNode = self.getNodeInUtteranceHighlighting(
							textElement
						);
					} else {
						textNode = self.getNodeForTextElement( textElement );
					}
					return textNode;
				}
			);
			$utterance =
				$( tokenElement ).parentsUntil( 'utterance' ).parent();
			utteranceOffset = 0;
			if ( $( self.utteranceHighlightingSelector ).length ) {
				utteranceOffset =
					parseInt( $utterance.attr( 'start-offset' ), 10 );
			}
			self.wrapTextNodes(
				span,
				textNodes,
				tokenElement.startOffset - utteranceOffset,
				tokenElement.endOffset - utteranceOffset
			);
			self.setHighlightTokenTimer( tokenElement );
		};

		/**
		 * Get text node, within utterance highlighting, for a text element.
		 *
		 * @param {HTMLElement} textElement The text element to get
		 *  text node for.
		 */

		this.getNodeInUtteranceHighlighting = function ( textElement ) {
			// Get the text node from the utterance highlighting that
			// wrapped the node for `textElement`.
			var textNode = $( self.utteranceHighlightingSelector )
				.filter( function () {
					return this.textPath ===
						textElement.getAttribute( 'path' );
				} )
				.contents()
				.get( 0 );
			return textNode;
		};

		/**
		 * Set a timer for when the next token should be highlighted.
		 *
		 * @param {HTMLElement} tokenElement The original token
		 *  element. The timer is set for the token element following
		 *  this one.
		 */

		this.setHighlightTokenTimer = function ( tokenElement ) {
			var $utterance, currentTime, duration, nextTokenElement;

			// Make sure there is only one timer running.
			window.clearTimeout( self.highlightTokenTimer );
			$utterance =
				$( tokenElement ).parentsUntil( 'utterance' ).parent();
			currentTime = $utterance.children( 'audio' ).prop( 'currentTime' );
			// The duration of the timer is the duration of the
			// current token.
			duration = tokenElement.endTime - currentTime;
			nextTokenElement =
				mw.wikispeech.wikispeech.getNextToken( $( tokenElement ) );
			if ( nextTokenElement ) {
				self.highlightTokenTimer = window.setTimeout(
					function () {
						self.removeWrappers(
							'.ext-wikispeech-highlight-word'
						);
						self.highlightToken( nextTokenElement );
						// Add a new timer for the next token, when it
						// starts playing.
						self.setHighlightTokenTimer( nextTokenElement );
					},
					duration * 1000
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
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.highlighter = new Highlighter();
	mw.wikispeech.Highlighter = Highlighter;
}( mediaWiki, jQuery ) );
