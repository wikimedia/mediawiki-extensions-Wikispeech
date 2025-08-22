/**
 * The player that appears when the user selects a bit of text.
 *
 * Includes logic for finding what to play, and starting and
 * stopping within an utterance.
 *
 * @class ext.wikispeech.SelectionPlayer
 * @constructor
 */

class SelectionPlayer {
	constructor() {

		this.previousEndUtterance = null;
		this.ui = null;
		this.storage = null;

	}
	/**
	 * Play selected text if selection is valid.
	 *
	 * @return {boolean} true selection plays, else false.
	 */

	playSelectionIfValid() {
		if ( this.isSelectionValid() ) {
			this.playSelection();
			return true;
		} else {
			return false;
		}
	}

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

	isSelectionValid() {
		if ( !this.isTextSelected() ) {
			return false;
		}
		const firstNode = this.getFirstNodeInSelection();
		const firstTextNode =
			this.storage.getFirstTextNode( firstNode, true );
		const lastNode = this.getLastNodeInSelection();
		const lastTextNode =
			this.storage.getLastTextNode( lastNode, true );
		if (
			this.storage.isNodeInUtterance( firstTextNode ) &&
				this.storage.isNodeInUtterance( lastTextNode )
		) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Test if there is any selected text.
	 *
	 * @return {boolean} true if there is any text selected, else false.
	 */

	isTextSelected() {
		const selection = window.getSelection();
		return !selection.isCollapsed;
	}

	/**
	 * Get the first node in the selection.
	 *
	 * Corrects node and offset that is sometimes incorrect in Firefox.
	 *
	 * @return {Text} The first node in the selection.
	 */

	getFirstNodeInSelection() {
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
	}

	/**
	 * Play selected text.
	 *
	 * Plays utterances containing the selected text. The first
	 * utterance starts playing at the first token that is
	 * selected and the last utterance stops playing after the
	 * last.
	 */

	playSelection() {
		this.player.playingSelection = true;
		const selection = window.getSelection();
		const startRange = selection.getRangeAt( 0 );
		const firstSelectionNode = this.getFirstNodeInSelection();
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
			this.storage.getFirstTextNode(
				firstSelectionNode,
				true
			);
		const startUtterance =
			this.storage.getStartUtterance(
				startNode,
				startOffset
			);
		this.player.currentUtterance = startUtterance;
		this.storage.prepareUtterance(
			startUtterance
		)
			.done( () => {
				const startToken = this.storage.getStartToken(
					startUtterance,
					startNode,
					startOffset
				);
				this.setStartTime( startUtterance, startToken.startTime );
				this.player.playUtterance( startUtterance, false );
				this.ui.setSelectionPlayerIconToStop();
			} );
		this.ui.showBufferingIconIfAudioIsLoading(
			startUtterance.audio
		);

		const endRange = selection.getRangeAt( selection.rangeCount - 1 );
		const lastSelectionNode = this.getLastNodeInSelection();
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
			this.storage.getLastTextNode(
				lastSelectionNode,
				true
			);
		const endUtterance =
			this.storage.getEndUtterance( endNode, endOffset );
		this.previousEndUtterance = endUtterance;
		this.storage.prepareUtterance(
			endUtterance
		)
			.done( () => {
				// Prepare the end utterance, since token information
				// is needed to calculate the correct end token.
				const endToken = this.storage.getEndToken(
					endUtterance,
					endNode,
					endOffset
				);
				this.setEndTime( endUtterance, endToken.endTime );
			} );
	}

	/**
	 * Set the time where an utterance will start playing.
	 *
	 * @param {Object} utterance The utterance to set start time
	 *  for.
	 * @param {number} startTime The time in milliseconds
	 *  to start playing at.
	 */

	setStartTime( utterance, startTime ) {
		utterance.audio.currentTime = startTime / 1000;
	}

	/**
	 * Get the last node in the selection.
	 *
	 * Corrects node and offset that is sometimes incorrect in
	 * Firefox.
	 *
	 * @return {Text} The last node in the selection.
	 */

	getLastNodeInSelection() {
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
	}

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

	setEndTime( utterance, endTime ) {
		$( utterance.audio ).one( 'playing.end', () => {
			const timeLeft = endTime - utterance.audio.currentTime * 1000;
			utterance.stopTimeout =
				window.setTimeout(
					() => {
						this.player.stop();
						this.resetPreviousEndUtterance();
					},
					timeLeft / mw.user.options.get( 'wikispeechSpeechRate' )
				);
		} );
	}

	/**
	 * Remove timeout for stopping end utterance.
	 */

	resetPreviousEndUtterance() {
		if ( this.previousEndUtterance ) {
			// Remove any trigger for setting end time for an
			// utterance. Otherwise, this will trigger the next
			// time the utterance is the end utterance, possibly
			// stopping playback too early.
			$( this.previousEndUtterance.audio ).off( 'playing.end' );
			window.clearTimeout( this.previousEndUtterance.stopTimeout );
			this.previousEndUtterance.stopTimeout = null;
			this.previousEndUtterance = null;
		}
	}
}

module.exports = SelectionPlayer;
