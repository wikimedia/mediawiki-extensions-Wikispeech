( function ( mw, $ ) {

	/**
	 * The player that appears when the user selects a bit of text.
	 *
	 * Includes logic for finding what to play, and starting and
	 * stopping within an utterance.
	 *
	 * @class ext.wikispeech.SelectionPlayer
	 * @constructor
	 */

	function SelectionPlayer() {
		var self;

		self = this;
		self.previousEndUtterance = null;

		/**
		 * Play selected text if selection is valid.
		 *
		 * @return {boolean} true selection plays, else false.
		 */

		this.playSelectionIfValid = function () {
			if ( self.isSelectionValid() ) {
				self.playSelection();
				return true;
			} else {
				return false;
			}
		};

		/**
		 * Test if the selected text is valid for recitation.
		 *
		 * Valid here means that the start and end points of the
		 * selection are in nodes which are part of utterances. Nodes
		 * outside utterances may occur within the selection.
		 *
		 * @return {boolean} true if the selection is valid for
		 *  recitation, else false.
		 */

		this.isSelectionValid = function () {
			var firstNode, firstTextNode, lastNode, lastTextNode;

			if ( !self.isTextSelected() ) {
				return false;
			}
			firstNode = self.getFirstNodeInSelection();
			firstTextNode = self.getFirstTextNode( firstNode, true );
			lastNode = self.getLastNodeInSelection();
			lastTextNode = self.getLastTextNode( lastNode, true );
			if (
				self.isNodeInUtterance( firstTextNode ) &&
					self.isNodeInUtterance( lastTextNode )
			) {
				return true;
			} else {
				return false;
			}
		};

		/**
		 * Test if there is any selected text.
		 *
		 * @return {boolean} true if there is any text selected, else false.
		 */

		this.isTextSelected = function () {
			var selection = window.getSelection();
			return !selection.isCollapsed;
		};

		/**
		 * Get the first node in the selection.
		 *
		 * Corrects node and offset that is sometimes incorrect in Firefox.
		 *
		 * @return {TextNode} The first node in the selection.
		 */

		this.getFirstNodeInSelection = function () {
			var selection, startRange, startNode, nodeBeforeActualNode;

			selection = window.getSelection();
			startRange = selection.getRangeAt( 0 );
			startNode = startRange.startContainer;
			if (
				startNode.nodeType === 3 &&
					startRange.startOffset === startNode.textContent.length
			) {
				// Check if start offset is beyond the end of the text node.
				// This is needed because of a bug in Firefox that
				// causes incorrect selections, when double clicking
				// selects the start or end of a text node. See:
				// https://bugzilla.mozilla.org/show_bug.cgi?id=1298845
				nodeBeforeActualNode = startNode;
				while ( !nodeBeforeActualNode.nextSibling ) {
					nodeBeforeActualNode = nodeBeforeActualNode.parentNode;
				}
				return nodeBeforeActualNode.nextSibling;
			} else {
				return startNode;
			}
		};

		/**
		 * Get the first text node that is a descendant of given node.
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
				i < mw.wikispeech.wikispeech.utterances.length;
				i++
			) {
				utterance = mw.wikispeech.wikispeech.utterances[ i ];
				for ( j = 0; j < utterance.content.length; j++ ) {
					item = utterance.content[ j ];
					if ( mw.wikispeech.util.getNodeForItem( item ) === node ) {
						return true;
					}
				}
			}
			return false;
		};

		/**
		 * Play selected text.
		 *
		 * Plays utterances containing the selected text. The first
		 * utterance starts playing at the first token that is
		 * selected and the last utterance stops playing after the
		 * last.
		 */

		this.playSelection = function () {
			var startRange, startNode, startOffset, startUtterance,
				endRange, endNode, endOffset, endUtterance, selection,
				firstSelectionNode, lastSelectionNode;

			mw.wikispeech.wikispeech.playingSelection = true;
			selection = window.getSelection();
			startRange = selection.getRangeAt( 0 );
			firstSelectionNode = self.getFirstNodeInSelection();
			if (
				firstSelectionNode !== startRange.startContainer ||
					firstSelectionNode.nodeType === 1
			) {
				// If the start node has been changed, this is because
				// it was corrected in getFirstNodeInSelection(). If
				// this is the case, the selection actually starts at
				// the beginning of the current node. If the start
				// node is an element, start offset is also zero,
				// because if should start in the first child text
				// nodes of that node.
				startOffset = 0;
			} else {
				startOffset = startRange.startOffset;
			}
			startNode = self.getFirstTextNode( firstSelectionNode, true );
			startUtterance = self.getStartUtterance( startNode, startOffset );
			mw.wikispeech.wikispeech.prepareUtterance(
				startUtterance,
				function () {
					var startToken = self.getStartToken(
						startUtterance,
						startNode,
						startOffset
					);
					self.setStartTime( startUtterance, startToken.startTime );
					mw.wikispeech.wikispeech.playUtterance( startUtterance );
				}
			);

			endRange = startRange;
			endRange = selection.getRangeAt( selection.rangeCount - 1 );
			lastSelectionNode = self.getLastNodeInSelection();
			if (
				lastSelectionNode !== endRange.endContainer ||
					lastSelectionNode.nodeType === 1
			) {
				endOffset = lastSelectionNode.textContent.length - 1;
			} else {
				endOffset = endRange.endOffset - 1;
			}
			endNode = self.getLastTextNode( lastSelectionNode, true );
			endUtterance = self.getEndUtterance( endNode, endOffset );
			self.previousEndUtterance = endUtterance;
			mw.wikispeech.wikispeech.prepareUtterance(
				endUtterance,
				function () {
					// Prepare the end utterance, since token information
					// is needed to calculate the correct end token.
					var endToken = self.getEndToken(
						endUtterance,
						endNode,
						endOffset
					);
					self.setEndTime( endUtterance, endToken.endTime );
				}
			);
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
					i < mw.wikispeech.wikispeech.utterances.length;
					i++
				) {
					utterance = mw.wikispeech.wikispeech.utterances[ i ];
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
					mw.wikispeech.util.getNodeForItem( item ) === node &&
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
					if ( mw.wikispeech.util.getNodeForItem( item ) !== node ) {
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
		 * Set the time where an utterance will start playing.
		 *
		 * @param {Object} utterance The utterance to set start time
		 *  for.
		 * @param {number} startTime The time in seconds to start
		 *  playing at.
		 */

		this.setStartTime = function ( utterance, startTime ) {
			utterance.audio.currentTime = startTime;
		};

		/**
		 * Get the last node in the selection.
		 *
		 * Corrects node and offset that is sometimes incorrect in
		 * Firefox.
		 *
		 * @return {TextNode} The last node in the selection.
		 */

		this.getLastNodeInSelection = function () {
			var selection, endRange, endNode, nodeAfterActualNode;

			selection = window.getSelection();
			endRange = selection.getRangeAt( selection.rangeCount - 1 );
			endNode = endRange.endContainer;
			if (
				endNode.nodeType === 3 &&
					endRange.endOffset === 0
			) {
				// Check if end offset is zero. This is needed
				// because of a bug in Firefox that causes incorrect
				// selections, when double clicking selects the start
				// or end of a text node. See:
				// https://bugzilla.mozilla.org/show_bug.cgi?id=1298845
				nodeAfterActualNode = endNode;
				while ( !nodeAfterActualNode.previousSibling ) {
					nodeAfterActualNode = nodeAfterActualNode.parentNode;
				}
				return nodeAfterActualNode.previousSibling;
			} else {
				return endNode;
			}
		};

		/**
		 * Get the last text node that is a descendant of given node.
		 *
		 * Finds the depth first text node, i.e. in
		 *  `<a>1<b>2</b></a>`
		 * the node with text "2" is the last one. Only nodes that are
		 * in utterances are considered. If the given node is itself a
		 * text node, it is simply returned.
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
					i < mw.wikispeech.wikispeech.utterances.length;
					i++
				) {
					utterance = mw.wikispeech.wikispeech.utterances[ i ];
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
		 * Set the time where an utterance will stop playing.
		 *
		 * Create an event handler for when the utterance starts
		 * playing. The handler creates a timeout that triggers when
		 * the end time is reached, stopping playback.
		 *
		 * @param {Object} utterance The utterance to set end time for.
		 * @param {number} endTime The time in seconds to stop playing
		 *  after.
		 */

		this.setEndTime = function ( utterance, endTime ) {
			$( utterance.audio ).one( 'playing.end', function () {
				var timeLeft = endTime - utterance.audio.currentTime;
				utterance.stopTimeout =
					window.setTimeout(
						mw.wikispeech.wikispeech.stop,
						timeLeft * 1000
					);
			} );
		};

		/**
		 * Add a small player that appears when text is selected.
		 */

		this.addSelectionPlayer = function () {
			var $player = $( '<div></div>' )
				.addClass( 'ext-wikispeech-selection-player' )
				.appendTo( '#content' );
			$( '<button></button>' )
				.addClass( 'ext-wikispeech-play-stop-button' )
				.click( mw.wikispeech.wikispeech.playOrStop )
				.appendTo( $player );
			$( document ).on( 'mouseup', function () {
				if ( self.isSelectionValid() ) {
					self.showSelectionPlayer();
				} else {
					$( '.ext-wikispeech-selection-player' )
						.css( 'visibility', 'hidden' );
				}
			} );
			$( document ).on( 'click', function () {
				// A click listener is also needed because of the
				// order of events when text is deselected by clicking
				// it.
				if ( !self.isSelectionValid() ) {
					$( '.ext-wikispeech-selection-player' )
						.css( 'visibility', 'hidden' );
				}
			} );
		};

		/**
		 * Show the selection player below the end of the selection.
		 */

		this.showSelectionPlayer = function () {
			var selection, lastRange, lastRect, left, top;

			selection = window.getSelection();
			lastRange = selection.getRangeAt( selection.rangeCount - 1 );
			lastRect =
				mw.wikispeech.util.getLast( lastRange.getClientRects() );
			// Place the player under the end of the selected text.
			if ( self.getTextDirection( lastRange.endContainer ) === 'rtl' ) {
				// For RTL languages, the end of the text is the far left.
				left = lastRect.left + $( document ).scrollLeft();
			} else {
				// For LTR languages, the end of the text is the far
				// right. This is the default value for the direction
				// property.
				left =
					lastRect.right +
					$( document ).scrollLeft() -
					$( '.ext-wikispeech-selection-player' ).width();
			}
			$( '.ext-wikispeech-selection-player' ).css( 'left', left );
			top = lastRect.bottom + $( document ).scrollTop();
			$( '.ext-wikispeech-selection-player' ).css( 'top', top );
			$( '.ext-wikispeech-selection-player' )
				.css( 'visibility', 'visible' );
		};

		/**
		 * Get the text direction for a node.
		 *
		 * @return {string} The CSS value of the `direction` property
		 *  for the node, or for its parent if it is a text node.
		 */

		this.getTextDirection = function ( node ) {
			if ( node.nodeType === 3 ) {
				// For text nodes, get the property of the parent element.
				return $( node ).parent().css( 'direction' );
			} else {
				return $( node ).css( 'direction' );
			}
		};

		/**
		 * Remove timeout for stopping end utterance.
		 */

		this.resetPreviousEndUtterance = function () {
			if ( self.previousEndUtterance ) {
				// Remove any trigger for setting end time for an
				// utterance. Otherwise, this will trigger the next
				// time the utterance is the end utterance, possibly
				// stopping playback too early.
				$( self.previousEndUtterance.audio ).off( 'playing.end' );
				self.previousEndUtterance = null;
			}
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.selectionPlayer = new SelectionPlayer();
	mw.wikispeech.SelectionPlayer = SelectionPlayer;
}( mediaWiki, jQuery ) );
