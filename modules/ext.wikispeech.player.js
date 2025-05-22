/**
 * Play, pause stop and navigate in the recitation.
 *
 * @class ext.wikispeech.Player
 * @constructor
 */

function Player() {
	const self = this;
	self.currentUtterance = null;
	self.paused = false;

	/**
	 * Play or pause, depending on whether an utterance is playing.
	 */

	this.playOrPause = function () {
		if ( self.isPlaying() && !self.paused ) {
			self.pause();
		} else {
			self.play();
		}
	};

	/**
	 * Play or stop, depending on whether an utterance is playing.
	 */

	this.playOrStop = function () {
		if ( self.isPlaying() ) {
			self.stop();
		} else {
			self.play();
		}
	};

	/**
	 * Test if there currently is an utterance playing
	 *
	 * @return {boolean} true if there is an utterance playing,
	 *  else false.
	 */

	this.isPlaying = function () {
		return self.currentUtterance !== null;
	};

	/**
	 * Stop playing the utterance currently playing.
	 */

	this.stop = function () {

		mw.wikispeech.ui.setAllPlayerIconsToPlay();

		self.paused = false;

		if ( self.isPlaying() ) {
			self.stopUtterance( self.currentUtterance );
			self.currentUtterance = null;
		}

		mw.wikispeech.ui.hideBufferingIcon();

		self.playingSelection = false;
	};

	/**
	 * Pause playing the utterance currently playing, and resume from paused utterance.
	 */
	this.pause = function () {
		if ( self.isPlaying() && !self.paused ) {
			self.paused = true;
			self.pauseUtterance( self.currentUtterance );
		}
		if ( self.playingSelection ) {
			self.stop();
		}
		mw.wikispeech.ui.setAllPlayerIconsToPlay();
		mw.wikispeech.ui.hideBufferingIcon();
	};

	/**
	 * Start playing the first utterance or selected text, if any.
	 */
	this.play = function () {
		if ( self.playingSelection ) {
			self.stop();
		} else {
			mw.wikispeech.ui.setPlayPauseIconToPause();
		}
		if ( self.paused ) {
			self.currentUtterance.audio.play();
			self.paused = false;

			const currentToken = self.getCurrentToken();
			if ( currentToken ) {
				mw.wikispeech.highlighter.startTokenHighlighting( currentToken );
			}
			return;
		}
		mw.wikispeech.storage.utterancesLoaded.done( () => {
			if ( !mw.wikispeech.selectionPlayer.playSelectionIfValid() ) {
				self.playUtterance( mw.wikispeech.storage.utterances[ 0 ] );
			}

		} );

	};

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

	this.playUtterance = function ( utterance, fromStart ) {
		fromStart = fromStart === undefined ? true : fromStart;
		if ( fromStart && self.isPlaying() ) {
			self.stopUtterance( self.currentUtterance );
		}
		self.currentUtterance = utterance;
		if ( !self.playingSelection ) {
			mw.wikispeech.highlighter.highlightUtterance( utterance );
		}
		mw.wikispeech.ui.showBufferingIconIfAudioIsLoading(
			utterance.audio
		);
		self.prepareAndPlayUtterance( utterance );
	};

	/**
	 * Ensure an utterance is ready for playback and play it.
	 *
	 * Plays utterance when it is ready. If the utterance fail to
	 * prepare and it is currently playing, a popup dialog will
	 * appear, letting the user retry or stop playback.
	 *
	 * @param {Object} utterance
	 */

	this.prepareAndPlayUtterance = function ( utterance ) {
		mw.wikispeech.storage.prepareUtterance( utterance )
			.done( () => {
				if ( utterance === self.currentUtterance && !self.paused ) {
					utterance.audio.play();
				}
			} )
			.fail( () => {
				if ( utterance !== self.currentUtterance ) {
					// Only show dialog if the current utterance
					// fails to load, to avoid multiple and less
					// relevant dialogs.
					return;
				}
				mw.wikispeech.ui.showLoadAudioError()
					.done( ( data ) => {
						if ( !data || data.action === 'stop' ) {
							// Stop both when "Stop" is clicked
							// and when escape is pressed.
							self.stop();
						} else if ( data.action === 'retry' ) {
							self.prepareAndPlayUtterance( utterance );
						}
					} );
			} );
	};

	/**
	 * Stop and rewind the audio for an utterance.
	 *
	 * @param {Object} utterance The utterance to stop the audio
	 *  for.
	 */

	this.stopUtterance = function ( utterance ) {
		utterance.audio.pause();
		// Rewind audio for next time it plays.
		utterance.audio.currentTime = 0;
		mw.wikispeech.ui.removeCanPlayListener( $( utterance.audio ) );
		mw.wikispeech.highlighter.clearHighlighting();
	};

	/**
	 * Pause the audio for an utterance.
	 *
	 * @param {Object} utterance The utterance to pause the audio
	 *  for.
	 */

	this.pauseUtterance = function ( utterance ) {
		utterance.audio.pause();
		mw.wikispeech.highlighter.clearHighlightTokenTimer();
	};

	/**
	 * Skip to the next utterance.
	 *
	 * Stop the current utterance and start playing the next one.
	 */

	this.skipAheadUtterance = function () {
		const nextUtterance =
			mw.wikispeech.storage.getNextUtterance( self.currentUtterance );
		if ( nextUtterance ) {
			self.playUtterance( nextUtterance );
		} else {
			self.stop();
		}
	};

	/**
	 * Skip to the previous utterance.
	 *
	 * Stop the current utterance and start playing the previous
	 * one. If the first utterance is playing, restart it.
	 */

	this.skipBackUtterance = function () {
		const rewindThreshold = mw.config.get(
			'wgWikispeechSkipBackRewindsThreshold'
		);
		const time = self.currentUtterance.audio.currentTime;
		if (
			time > rewindThreshold ||
				self.currentUtterance === mw.wikispeech.storage.utterances[ 0 ]
		) {
			// Restart the current utterance if it's the first one
			// or if it has played for longer than the skip back
			// threshold. The threshold is based on position in
			// the audio, rather than time played. This means it
			// scales with speech rate.
			self.currentUtterance.audio.currentTime = 0;
		} else {
			const previousUtterance =
				mw.wikispeech.storage.getPreviousUtterance(
					self.currentUtterance
				);
			self.playUtterance( previousUtterance );
		}
	};

	/**
	 * Get the token being played.
	 *
	 * @return {Object} The token being played. Can return null.
	 */

	this.getCurrentToken = function () {
		if ( !self.currentUtterance || !self.currentUtterance.tokens ) {
			return null;
		}
		let currentToken = null;
		const tokens = self.currentUtterance.tokens;
		const currentTime = self.currentUtterance.audio.currentTime * 1000;
		const tokensWithDuration = tokens.filter( ( token ) => {
			const duration = token.endTime - token.startTime;
			return duration > 0;
		} );
		const lastTokenWithDuration =
			mw.wikispeech.util.getLast( tokensWithDuration );
		if ( currentTime === lastTokenWithDuration.endTime ) {
			// If the current time is equal to the end time of the
			// last token, the last token is the current.
			currentToken = lastTokenWithDuration;
		} else {
			currentToken = tokensWithDuration.find( ( token ) => token.startTime <= currentTime &&
					token.endTime > currentTime );
		}
		return currentToken;
	};

	/**
	 * Skip to the next token.
	 *
	 * If there are no more tokens in the current utterance, skip
	 * to the next utterance.
	 */

	this.skipAheadToken = function () {
		if ( !self.isPlaying() ) {
			return;
		}

		const currentToken = self.getCurrentToken();
		const nextToken = mw.wikispeech.storage.getNextToken( currentToken );

		if ( !nextToken ) {
			self.skipAheadUtterance();
			return;
		}

		self.currentUtterance.audio.currentTime = nextToken.startTime / 1000;

		mw.wikispeech.highlighter.clearHighlighting();
		mw.wikispeech.highlighter.highlightUtterance( self.currentUtterance );

		if ( self.paused ) {
			mw.wikispeech.highlighter.highlightToken( nextToken );

		} else {
			mw.wikispeech.highlighter.startTokenHighlighting( nextToken );
		}
	};

	/**
	 * Skip to the previous token.
	 *
	 * If there are no preceding tokens, skip to the last token of
	 * the previous utterance.
	 */

	this.skipBackToken = function () {
		if ( self.isPlaying() ) {
			const currentToken = self.getCurrentToken();
			let previousToken = mw.wikispeech.storage.getPreviousToken( currentToken );

			if ( !previousToken ) {
				self.skipBackUtterance();
				previousToken = mw.wikispeech.storage.getLastToken( self.currentUtterance );
			}

			if ( previousToken ) {
				mw.wikispeech.highlighter.clearHighlighting();
				mw.wikispeech.highlighter.highlightUtterance( self.currentUtterance );
			}

			if ( previousToken ) {
				self.currentUtterance.audio.currentTime = previousToken.startTime / 1000;

				if ( self.paused ) {
					mw.wikispeech.highlighter.highlightToken( previousToken );
				} else {
					mw.wikispeech.highlighter.startTokenHighlighting( previousToken );
				}
			}
		}
	};

}

mw.wikispeech = mw.wikispeech || {};
mw.wikispeech.Player = Player;
mw.wikispeech.player = new Player();
