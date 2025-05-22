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
	const self = this;
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
		if ( !self.isTextSelected() ) {
			return false;
		}
		const firstNode = self.getFirstNodeInSelection();
		const firstTextNode =
			mw.wikispeech.storage.getFirstTextNode( firstNode, true );
		const lastNode = self.getLastNodeInSelection();
		const lastTextNode =
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
		const selection = window.getSelection();
		return !selection.isCollapsed;
	};

	/**
	 * Get the first node in the selection.
	 *
	 * Corrects node and offset that is sometimes incorrect in Firefox.
	 *
	 * @return {Text} The first node in the selection.
	 */

	this.getFirstNodeInSelection = function () {
		const selection = window.getSelection();
		const startRange = selection.getRangeAt( 0 );
		const startNode = startRange.startContainer;
		if (
			startNode.nodeType === 3 &&
				startRange.startOffset === startNode.textContent.length
		) {
			// Check if start offset is beyond the end of the text node.
			// This is needed because of a bug in Firefox that
			// causes incorrect selections, when double clicking
			// selects the start or end of a text node. See:
			// https://bugzilla.mozilla.org/show_bug.cgi?id=1298845
			let nodeBeforeActualNode = startNode;
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
		mw.wikispeech.player.playingSelection = true;
		const selection = window.getSelection();
		const startRange = selection.getRangeAt( 0 );
		const firstSelectionNode = self.getFirstNodeInSelection();
		let startOffset;
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
		const startNode =
			mw.wikispeech.storage.getFirstTextNode(
				firstSelectionNode,
				true
			);
		const startUtterance =
			mw.wikispeech.storage.getStartUtterance(
				startNode,
				startOffset
			);
		mw.wikispeech.player.currentUtterance = startUtterance;
		mw.wikispeech.storage.prepareUtterance(
			startUtterance
		)
			.done( () => {
				const startToken = mw.wikispeech.storage.getStartToken(
					startUtterance,
					startNode,
					startOffset
				);
				self.setStartTime( startUtterance, startToken.startTime );
				mw.wikispeech.player.playUtterance( startUtterance, false );
				mw.wikispeech.ui.setSelectionPlayerIconToStop();
			} );
		mw.wikispeech.ui.showBufferingIconIfAudioIsLoading(
			startUtterance.audio
		);

		const endRange = selection.getRangeAt( selection.rangeCount - 1 );
		const lastSelectionNode = self.getLastNodeInSelection();
		let endOffset;
		if (
			lastSelectionNode !== endRange.endContainer ||
				lastSelectionNode.nodeType === 1
		) {
			endOffset = lastSelectionNode.textContent.length - 1;
		} else {
			endOffset = endRange.endOffset - 1;
		}
		const endNode =
			mw.wikispeech.storage.getLastTextNode(
				lastSelectionNode,
				true
			);
		const endUtterance =
			mw.wikispeech.storage.getEndUtterance( endNode, endOffset );
		self.previousEndUtterance = endUtterance;
		mw.wikispeech.storage.prepareUtterance(
			endUtterance
		)
			.done( () => {
				// Prepare the end utterance, since token information
				// is needed to calculate the correct end token.
				const endToken = mw.wikispeech.storage.getEndToken(
					endUtterance,
					endNode,
					endOffset
				);
				self.setEndTime( endUtterance, endToken.endTime );
			} );
	};

	/**
	 * Set the time where an utterance will start playing.
	 *
	 * @param {Object} utterance The utterance to set start time
	 *  for.
	 * @param {number} startTime The time in milliseconds
	 *  to start playing at.
	 */

	this.setStartTime = function ( utterance, startTime ) {
		utterance.audio.currentTime = startTime / 1000;
	};

	/**
	 * Get the last node in the selection.
	 *
	 * Corrects node and offset that is sometimes incorrect in
	 * Firefox.
	 *
	 * @return {Text} The last node in the selection.
	 */

	this.getLastNodeInSelection = function () {
		const selection = window.getSelection();
		const endRange = selection.getRangeAt( selection.rangeCount - 1 );
		const endNode = endRange.endContainer;
		if (
			endNode.nodeType === 3 &&
				endRange.endOffset === 0
		) {
			// Check if end offset is zero. This is needed
			// because of a bug in Firefox that causes incorrect
			// selections, when double clicking selects the start
			// or end of a text node. See:
			// https://bugzilla.mozilla.org/show_bug.cgi?id=1298845
			let nodeAfterActualNode = endNode;
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
	 * @param {number} endTime The time in milliseconds to stop
	 *  playing after.
	 */

	this.setEndTime = function ( utterance, endTime ) {
		$( utterance.audio ).one( 'playing.end', () => {
			const timeLeft = endTime - utterance.audio.currentTime * 1000;
			utterance.stopTimeout =
				window.setTimeout(
					() => {
						mw.wikispeech.player.stop();
						self.resetPreviousEndUtterance();
					},
					timeLeft / mw.user.options.get( 'wikispeechSpeechRate' )
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
