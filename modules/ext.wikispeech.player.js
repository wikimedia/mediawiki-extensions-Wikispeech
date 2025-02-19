( function () {

	/**
	 * Play, stop and navigate in the recitation.
	 *
	 * @class ext.wikispeech.Player
	 * @constructor
	 */

	function Player() {
		let self;

		self = this;
		self.currentUtterance = null;

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
			if ( self.isPlaying() ) {
				self.stopUtterance( self.currentUtterance );
			}
			self.currentUtterance = null;
			mw.wikispeech.ui.setPlayStopIconToPlay();
			mw.wikispeech.ui.hideBufferingIcon();
			self.playingSelection = false;
		};

		/**
		 * Start playing the first utterance or selected text, if any.
		 */

		this.play = function () {
			mw.wikispeech.storage.utterancesLoaded.done( () => {
				if ( !mw.wikispeech.selectionPlayer.playSelectionIfValid() ) {
					self.playUtterance( mw.wikispeech.storage.utterances[ 0 ] );
				}
			} );
			mw.wikispeech.ui.setPlayStopIconToStop();
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
			self.prepareAndPlayUtterance( utterance );
			mw.wikispeech.ui.showBufferingIconIfAudioIsLoading(
				utterance.audio
			);
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
					if ( utterance === self.currentUtterance ) {
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
			let previousUtterance, rewindThreshold, time;

			rewindThreshold = mw.config.get(
				'wgWikispeechSkipBackRewindsThreshold'
			);
			time = self.currentUtterance.audio.currentTime;
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
				previousUtterance =
					mw.wikispeech.storage.getPreviousUtterance(
						self.currentUtterance
					);
				self.playUtterance( previousUtterance );
			}
		};

		/**
		 * Get the token being played.
		 *
		 * @return {Object} The token being played.
		 */

		this.getCurrentToken = function () {
			let tokens, currentTime, currentToken, tokensWithDuration,
				duration, lastTokenWithDuration;

			currentToken = null;
			tokens = self.currentUtterance.tokens;
			currentTime = self.currentUtterance.audio.currentTime * 1000;
			tokensWithDuration = tokens.filter( ( token ) => {
				duration = token.endTime - token.startTime;
				return duration > 0;
			} );
			lastTokenWithDuration =
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
			let nextToken;

			if ( self.isPlaying() ) {
				nextToken =
					mw.wikispeech.storage.getNextToken( self.getCurrentToken() );
				if ( !nextToken ) {
					self.skipAheadUtterance();
				} else {
					self.currentUtterance.audio.currentTime = nextToken.startTime / 1000;
					mw.wikispeech.highlighter.startTokenHighlighting(
						nextToken
					);
				}
			}
		};

		/**
		 * Skip to the previous token.
		 *
		 * If there are no preceding tokens, skip to the last token of
		 * the previous utterance.
		 */

		this.skipBackToken = function () {
			let previousToken;

			if ( self.isPlaying() ) {
				previousToken =
					mw.wikispeech.storage.getPreviousToken( self.getCurrentToken() );
				if ( !previousToken ) {
					self.skipBackUtterance();
					previousToken =
						mw.wikispeech.storage.getLastToken( self.currentUtterance );
				}
				self.currentUtterance.audio.currentTime = previousToken.startTime / 1000;
				mw.wikispeech.highlighter.startTokenHighlighting(
					previousToken
				);
			}
		};
	}

	mw.wikispeech = mw.wikispeech || {};
	mw.wikispeech.Player = Player;
	mw.wikispeech.player = new Player();
}() );
