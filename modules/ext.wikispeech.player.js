( function () {

	/**
	 * Play, stop and navigate in the recitation.
	 *
	 * @class ext.wikispeech.Player
	 * @constructor
	 */

	function Player() {
		var self, currentUtterance;

		self = this;
		currentUtterance = null;

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
			return currentUtterance !== null;
		};

		/**
		 * Stop playing the utterance currently playing.
		 */

		this.stop = function () {
			if ( self.isPlaying() ) {
				self.stopUtterance( currentUtterance );
			}
			currentUtterance = null;
			mw.wikispeech.ui.setPlayStopIconToPlay();
			mw.wikispeech.ui.hideBufferingIcon();
			self.playingSelection = false;
		};

		/**
		 * Start playing the first utterance or selected text, if any.
		 */

		this.play = function () {
			if ( !mw.wikispeech.selectionPlayer.playSelectionIfValid() ) {
				self.playUtterance( mw.wikispeech.storage.utterances[ 0 ] );
			}
			mw.wikispeech.ui.setPlayStopIconToStop();
		};

		/**
		 * Play the audio for an utterance.
		 *
		 * This also stops any currently playing utterance.
		 *
		 * @param {Object} utterance The utterance to play the audio
		 *  for.
		 */

		this.playUtterance = function ( utterance ) {
			if ( self.isPlaying() ) {
				self.stopUtterance( currentUtterance );
			}
			currentUtterance = utterance;
			if ( !self.playingSelection ) {
				mw.wikispeech.highlighter.highlightUtterance( utterance );
			}
			utterance.audio.play();
			mw.wikispeech.ui.showBufferingIconIfAudioIsLoading(
				utterance.audio
			);
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
			var nextUtterance =
				mw.wikispeech.storage.getNextUtterance( currentUtterance );
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
			var previousUtterance, rewindThreshold, time;

			rewindThreshold = mw.config.get(
				'wgWikispeechSkipBackRewindsThreshold'
			);
			time = currentUtterance.audio.currentTime;
			if (
				time > rewindThreshold ||
					currentUtterance === mw.wikispeech.storage.utterances[ 0 ]
			) {
				// Restart the current utterance if it's the first one
				// or if it has played for longer than the skip back
				// threshold. The threshold is based on position in
				// the audio, rather than time played. This means it
				// scales with speech rate.
				currentUtterance.audio.currentTime = 0;
			} else {
				previousUtterance =
					mw.wikispeech.storage.getPreviousUtterance(
						currentUtterance
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
			var tokens, currentTime, currentToken, tokensWithDuration,
				duration, lastTokenWithDuration;

			currentToken = null;
			tokens = currentUtterance.tokens;
			currentTime = currentUtterance.audio.currentTime * 1000;
			tokensWithDuration = tokens.filter( function ( token ) {
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
				// TODO: Array.prototype.find is not supported in IE11
				// eslint-disable-next-line no-restricted-syntax
				currentToken = tokensWithDuration.find( function ( token ) {
					return token.startTime <= currentTime &&
						token.endTime > currentTime;
				} );
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
			var nextToken;

			if ( self.isPlaying() ) {
				nextToken =
					mw.wikispeech.storage.getNextToken( self.getCurrentToken() );
				if ( !nextToken ) {
					self.skipAheadUtterance();
				} else {
					currentUtterance.audio.currentTime = nextToken.startTime / 1000;
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
			var previousToken;

			if ( self.isPlaying() ) {
				previousToken =
					mw.wikispeech.storage.getPreviousToken( self.getCurrentToken() );
				if ( !previousToken ) {
					self.skipBackUtterance();
					previousToken =
						mw.wikispeech.storage.getLastToken( currentUtterance );
				}
				currentUtterance.audio.currentTime = previousToken.startTime / 1000;
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
