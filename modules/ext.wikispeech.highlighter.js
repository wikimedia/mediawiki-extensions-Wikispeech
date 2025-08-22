/**
 * Handles highlighting parts of the page when reciting.
 *
 * @class ext.wikispeech.Highlighter
 * @constructor
 */

class Highlighter {
	constructor() {
		this.highlightTokenTimer = null;
		this.utteranceHighlightingClass =
			'ext-wikispeech-highlight-sentence';
		this.utteranceHighlightingSelector =
			'.' + this.utteranceHighlightingClass;
		this.storage = null;
	}

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

	highlightUtterance( utterance ) {
		const textNodes = utterance.content
			.map( ( item ) => this.storage.getNodeForItem( item ) )
			// Remove nulls that were added for items without nodes.
			.filter( ( item ) => item );
		// Class name is documented above
		// eslint-disable-next-line mediawiki/class-doc
		const span = $( '<span>' )
			.addClass( this.utteranceHighlightingClass )
			.get( 0 );
		this.wrapTextNodes(
			span,
			textNodes,
			utterance.startOffset,
			utterance.endOffset
		);
		$( this.utteranceHighlightingSelector ).each( function ( i ) {
			// Save the path to the text node, as it was before
			// adding the span. This will no longer be the correct
			// path, once the span is added. This enables adding
			// token highlighting within the utterance
			// highlighting.
			this.textPath = utterance.content[ i ].path;
		} );
	}

	/**
	 * Wrap text nodes in an element.
	 *
	 * Each text node is wrapped in an individual copy of the
	 * wrapper element. The first and last node will be partially
	 * wrapped, based on the offset values.
	 *
	 * @param {HTMLElement} wrapper The element used to wrap the
	 *  text nodes.
	 * @param {Text[]} textNodes The text nodes to wrap.
	 * @param {number} startOffset The start offset in the first
	 *  text node.
	 * @param {number} endOffset The end offset in the last text
	 *  node.
	 */

	wrapTextNodes(
		wrapper,
		textNodes,
		startOffset,
		endOffset
	) {
		let $nodesToWrap = $();
		const firstNode = textNodes[ 0 ];
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
			for ( let i = 1; i < textNodes.length - 1; i++ ) {
				const node = textNodes[ i ];
				// Wrap all the nodes between first and last
				// completely.
				$nodesToWrap = $nodesToWrap.add( node );
			}
			const lastNode = textNodes[ textNodes.length - 1 ];
			lastNode.splitText( endOffset + 1 );
			$nodesToWrap = $nodesToWrap.add( lastNode );
		}
		$nodesToWrap.wrap( wrapper );
	}

	/**
	 * Highlight a token in the original HTML.
	 *
	 * What part of the HTML to wrap is calculated from a token.
	 *
	 * @param {Object} token The token used to calculate what part
	 *  to highlight.
	 */

	startTokenHighlighting( token ) {
		if ( mw.user.options.get( 'wikispeechPartOfContent' ) ) {
			return;
		}

		this.removeWrappers( '.ext-wikispeech-highlight-word' );
		this.clearHighlightTokenTimer();
		this.highlightToken( token );
		this.setHighlightTokenTimer( token );
	}

	/**
	 * Highlight a token in the original HTML.
	 *
	 * What part of the HTML to wrap is calculated from a token.
	 *
	 * @param {Object} token The token used to calculate what part
	 *  to highlight.
	 */

	highlightToken( token ) {
		if ( mw.user.options.get( 'wikispeechPartOfContent' ) ) {
			return;
		}

		const span = $( '<span>' )
			.addClass( 'ext-wikispeech-highlight-word' )
			.get( 0 );
		const textNodes = token.items.map( ( item ) => {
			let textNode;

			if ( $( this.utteranceHighlightingSelector ).length ) {
				// Add the token highlighting within the
				// utterance highlightings, if there are any.
				textNode = this.getNodeInUtteranceHighlighting(
					item
				);
			} else {
				textNode = this.storage.getNodeForItem( item );
			}
			return textNode;
		} );
		let startOffset = token.startOffset;
		let endOffset = token.endOffset;
		if (
			$( this.utteranceHighlightingSelector ).length &&
				token.items[ 0 ] === token.utterance.content[ 0 ]
		) {
			// Modify the offset if the token is the first in the
			// utterance and there is an utterance
			// highlighting. The text node may have been split
			// when the utterance highlighting was applied.
			startOffset -= token.utterance.startOffset;
			endOffset -= token.utterance.startOffset;
		}
		this.wrapTextNodes(
			span,
			textNodes,
			startOffset,
			endOffset
		);
	}

	/**
	 * Get text node, within utterance highlighting, for an item.
	 *
	 * @param {Object} item The item to get text node for.
	 */

	getNodeInUtteranceHighlighting( item ) {
		// Get the text node from the utterance highlighting that
		// wrapped the node for `textElement`.
		const textNode = $( this.utteranceHighlightingSelector )
			.filter( function () {
				return this.textPath ===
					item.path;
			} )
			.contents()
			.get( 0 );
		return textNode;
	}

	/**
	 * Set a timer for when the next token should be highlighted.
	 *
	 * @param {Object} token The original token. The timer is set
	 *  for the token following this one.
	 */

	setHighlightTokenTimer( token ) {
		const currentTime = token.utterance.audio.currentTime * 1000;
		// The duration of the timer is the duration of the
		// current token.
		const duration = token.endTime - currentTime;
		const nextToken = this.storage.getNextToken( token );
		if ( nextToken ) {
			this.highlightTokenTimer = window.setTimeout(
				() => {
					this.removeWrappers(
						'.ext-wikispeech-highlight-word'
					);
					this.highlightToken( nextToken );
					// Add a new timer for the next token, when it
					// starts playing.
					this.setHighlightTokenTimer( nextToken );
				},
				duration / mw.user.options.get( 'wikispeechSpeechRate' )
			);
		}
	}

	/**
	 * Remove elements wrapping text nodes.
	 *
	 * Restores the text nodes to the way they were before they
	 * were wrapped.
	 *
	 * @param {string} wrapperSelector The selector for the
	 *  elements to remove
	 */

	removeWrappers( wrapperSelector ) {
		const parents = [];
		const $span = $( wrapperSelector );
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
	}

	/**
	 * Remove any sentence and word highlighting.
	 */

	clearHighlighting() {
		// Remove sentence highlighting.
		this.removeWrappers( '.ext-wikispeech-highlight-sentence' );
		// Remove word highlighting.
		this.removeWrappers( '.ext-wikispeech-highlight-word' );
		this.clearHighlightTokenTimer();
	}

	/**
	 * Clear the timer for highlighting tokens.
	 */

	clearHighlightTokenTimer() {
		clearTimeout( this.highlightTokenTimer );
	}
}

module.exports = Highlighter;
