/**
 * Play, pause stop and navigate in the recitation.
 *
 * @class ext.wikispeech.Player
 * @constructor
 */

const util = require( './ext.wikispeech.util.js' );

class Player {
	constructor() {
		this.currentUtterance = null;
		this.paused = false;
		this.playingSelection = false;

		this.ui = null;
		this.storage = null;
		this.highlighter = null;
		this.selectionPlayer = null;
	}

	/**
	 * Play or pause, depending on whether an utterance is playing.
	 */

	playOrPause() {
		if ( this.isPlaying() && !this.paused ) {
			this.pause();
		} else {
			this.play();
		}
	}

	/**
	 * Play or stop, depending on whether an utterance is playing.
	 */

	playOrStop() {
		if ( this.isPlaying() ) {
			this.stop();
		} else {
			this.play();
		}
	}

	/**
	 * Test if there currently is an utterance playing
	 *
	 * @return {boolean} true if there is an utterance playing,
	 *  else false.
	 */

	isPlaying() {
		return this.currentUtterance !== null;
	}

	/**
	 * Stop playing the utterance currently playing.
	 */

	stop() {

		this.ui.setAllPlayerIconsToPlay();

		this.paused = false;

		if ( this.isPlaying() ) {
			this.stopUtterance( this.currentUtterance );
			this.currentUtterance = null;
		}

		this.ui.hideBufferingIcon();

		this.playingSelection = false;
	}

	/**
	 * Pause playing the utterance currently playing, and resume from paused utterance.
	 */
	pause() {
		if ( this.isPlaying() && !this.paused ) {
			this.paused = true;
			this.pauseUtterance( this.currentUtterance );
		}
		if ( this.playingSelection ) {
			this.stop();
		}
		this.ui.setAllPlayerIconsToPlay();
		this.ui.hideBufferingIcon();
	}

	/**
	 * Start playing the first utterance or selected text, if any.
	 */
	play() {
		if ( this.playingSelection ) {
			this.stop();
		} else {
			this.ui.setPlayPauseIconToPause();
		}
		if ( this.paused ) {
			this.currentUtterance.audio.play();
			this.paused = false;

			const currentToken = this.getCurrentToken();
			if ( currentToken ) {
				this.highlighter.startTokenHighlighting( currentToken );
			}
			return;
		}
		this.storage.utterancesLoaded.done( () => {
			if ( !this.selectionPlayer.playSelectionIfValid() ) {
				this.playUtterance( this.storage.utterances[ 0 ] );
			}

		} );

	}

	/**
	 * Play the audio for an utterance.
	 *
	 * This also stops any currently playing utterance.
	 *
	 * @param {Object} utterance The utterance to play the audio
	 *  for.
	 * @param {boolean} [fromStart=true] Whether the utterance
	 *  should play from start or not.
	 */
	playUtterance( utterance, fromStart ) {
		fromStart = fromStart === undefined ? true : fromStart;
		if ( fromStart && this.isPlaying() ) {
			this.stopUtterance( this.currentUtterance );
		}
		this.currentUtterance = utterance;
		if ( !this.playingSelection ) {
			this.highlighter.highlightUtterance( utterance );
		}
		this.ui.showBufferingIconIfAudioIsLoading(
			utterance.audio
		);
		this.prepareAndPlayUtterance( utterance );
	}

	/**
	 * Ensure an utterance is ready for playback and play it.
	 *
	 * Plays utterance when it is ready. If the utterance fail to
	 * prepare and it is currently playing, a popup dialog will
	 * appear, letting the user retry or stop playback.
	 *
	 * @param {Object} utterance
	 */

	prepareAndPlayUtterance( utterance ) {
		this.storage.prepareUtterance( utterance )
			.done( () => {
				if ( utterance === this.currentUtterance && !this.paused ) {
					utterance.audio.play();
				}
			} )
			.fail( () => {
				if ( utterance !== this.currentUtterance ) {
					// Only show dialog if the current utterance
					// fails to load, to avoid multiple and less
					// relevant dialogs.
					return;
				}
				this.ui.showLoadAudioError()
					.done( ( data ) => {
						if ( !data || data.action === 'stop' ) {
							// Stop both when "Stop" is clicked
							// and when escape is pressed.
							this.stop();
						} else if ( data.action === 'retry' ) {
							this.prepareAndPlayUtterance( utterance );
						}
					} );
			} );
	}

	/**
	 * Stop and rewind the audio for an utterance.
	 *
	 * @param {Object} utterance The utterance to stop the audio
	 *  for.
	 */

	stopUtterance( utterance ) {
		utterance.audio.pause();
		// Rewind audio for next time it plays.
		utterance.audio.currentTime = 0;
		this.ui.removeCanPlayListener( $( utterance.audio ) );
		this.highlighter.clearHighlighting();
	}

	/**
	 * Pause the audio for an utterance.
	 *
	 * @param {Object} utterance The utterance to pause the audio
	 *  for.
	 */

	pauseUtterance( utterance ) {
		utterance.audio.pause();
		this.highlighter.clearHighlightTokenTimer();
	}

	/**
	 * Skip to the next utterance.
	 *
	 * Stop the current utterance and start playing the next one.
	 */

	skipAheadUtterance() {
		const nextUtterance =
			this.storage.getNextUtterance( this.currentUtterance );
		if ( nextUtterance ) {
			this.playUtterance( nextUtterance );
		} else {
			this.stop();
		}
	}

	/**
	 * Skip to the previous utterance.
	 *
	 * Stop the current utterance and start playing the previous
	 * one. If the first utterance is playing, restart it.
	 */

	skipBackUtterance() {
		const rewindThreshold = mw.config.get(
			'wgWikispeechSkipBackRewindsThreshold'
		);
		const time = this.currentUtterance.audio.currentTime;
		if (
			time > rewindThreshold ||
				this.currentUtterance === this.storage.utterances[ 0 ]
		) {
			// Restart the current utterance if it's the first one
			// or if it has played for longer than the skip back
			// threshold. The threshold is based on position in
			// the audio, rather than time played. This means it
			// scales with speech rate.
			this.currentUtterance.audio.currentTime = 0;
		} else {
			const previousUtterance =
				this.storage.getPreviousUtterance(
					this.currentUtterance
				);
			this.playUtterance( previousUtterance );
		}
	}

	/**
	 * Get the token being played.
	 *
	 * @return {Object} The token being played. Can return null.
	 */

	getCurrentToken() {
		if ( !this.currentUtterance || !this.currentUtterance.tokens ) {
			return null;
		}
		let currentToken = null;
		const tokens = this.currentUtterance.tokens;
		const currentTime = this.currentUtterance.audio.currentTime * 1000;
		const tokensWithDuration = tokens.filter( ( token ) => {
			const duration = token.endTime - token.startTime;
			return duration > 0;
		} );
		const lastTokenWithDuration =
			util.getLast( tokensWithDuration );
		if ( currentTime === lastTokenWithDuration.endTime ) {
			// If the current time is equal to the end time of the
			// last token, the last token is the current.
			currentToken = lastTokenWithDuration;
		} else {
			currentToken = tokensWithDuration.find( ( token ) => token.startTime <= currentTime &&
					token.endTime > currentTime );
		}
		return currentToken;
	}

	/**
	 * Skip to the next token.
	 *
	 * If there are no more tokens in the current utterance, skip
	 * to the next utterance.
	 */

	skipAheadToken() {
		if ( !this.isPlaying() ) {
			return;
		}

		const currentToken = this.getCurrentToken();
		const nextToken = this.storage.getNextToken( currentToken );

		if ( !nextToken ) {
			this.skipAheadUtterance();
			return;
		}

		this.currentUtterance.audio.currentTime = nextToken.startTime / 1000;

		this.highlighter.clearHighlighting();
		this.highlighter.highlightUtterance( this.currentUtterance );

		if ( this.paused ) {
			this.highlighter.highlightToken( nextToken );

		} else {
			this.highlighter.startTokenHighlighting( nextToken );
		}
	}

	/**
	 * Skip to the previous token.
	 *
	 * If there are no preceding tokens, skip to the last token of
	 * the previous utterance.
	 */

	skipBackToken() {
		if ( this.isPlaying() ) {
			const currentToken = this.getCurrentToken();
			let previousToken = this.storage.getPreviousToken( currentToken );

			if ( !previousToken ) {
				this.skipBackUtterance();
				previousToken = this.storage.getLastToken( this.currentUtterance );
			}

			if ( previousToken ) {
				this.highlighter.clearHighlighting();
				this.highlighter.highlightUtterance( this.currentUtterance );
			}

			if ( previousToken ) {
				this.currentUtterance.audio.currentTime = previousToken.startTime / 1000;

				if ( this.paused ) {
					this.highlighter.highlightToken( previousToken );
				} else {
					this.highlighter.startTokenHighlighting( previousToken );
				}
			}
		}
	}

}

module.exports = Player;
