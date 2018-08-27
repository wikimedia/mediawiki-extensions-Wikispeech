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
			firstTextNode =
				mw.wikispeech.storage.getFirstTextNode( firstNode, true );
			lastNode = self.getLastNodeInSelection();
			lastTextNode =
				mw.wikispeech.storage.getLastTextNode( lastNode, true );
			if (
				mw.wikispeech.storage.isNodeInUtterance( firstTextNode ) &&
					mw.wikispeech.storage.isNodeInUtterance( lastTextNode )
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

			mw.wikispeech.player.playingSelection = true;
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
			startNode =
				mw.wikispeech.storage.getFirstTextNode(
					firstSelectionNode,
					true
				);
			startUtterance =
				mw.wikispeech.storage.getStartUtterance(
					startNode,
					startOffset
				);
			mw.wikispeech.storage.prepareUtterance(
				startUtterance,
				function () {
					var startToken = mw.wikispeech.storage.getStartToken(
						startUtterance,
						startNode,
						startOffset
					);
					self.setStartTime( startUtterance, startToken.startTime );
					mw.wikispeech.player.playUtterance( startUtterance );
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
			endNode =
				mw.wikispeech.storage.getLastTextNode(
					lastSelectionNode,
					true
				);
			endUtterance =
				mw.wikispeech.storage.getEndUtterance( endNode, endOffset );
			self.previousEndUtterance = endUtterance;
			mw.wikispeech.storage.prepareUtterance(
				endUtterance,
				function () {
					// Prepare the end utterance, since token information
					// is needed to calculate the correct end token.
					var endToken = mw.wikispeech.storage.getEndToken(
						endUtterance,
						endNode,
						endOffset
					);
					self.setEndTime( endUtterance, endToken.endTime );
				}
			);
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
						function () {
							mw.wikispeech.player.stop();
							self.resetPreviousEndUtterance();
						},
						timeLeft * 1000
					);
			} );
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
				window.clearTimeout( self.previousEndUtterance.stopTimeout );
				self.previousEndUtterance.stopTimeout = null;
				self.previousEndUtterance = null;
			}
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.selectionPlayer = new SelectionPlayer();
	mw.wikispeech.SelectionPlayer = SelectionPlayer;
}( mediaWiki, jQuery ) );
