<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MWTimestamp;

/**
 * @since 0.1.10
 */
class Utterance {

	/** @var int */
	private $utteranceId;

	/** @var string|null Hash from parts of URL to the wiki containing page segment of this utterance. */
	private $remoteWikiHash;

	/** @var int Mediawiki page ID. */
	private $pageId;

	/** @var string ISO 639 */
	private $language;

	/** @var string Name of synthesis voice. */
	private $voice;

	/** @var string Hash of segment representing utterance. */
	private $segmentHash;

	/** @var MWTimestamp */
	private $dateStored;

	/** @var string Base64 encoded audio file */
	private $audio;

	/** @var string JSON containing tokens etc describing the audio. */
	private $synthesisMetadata;

	/**
	 * @since 0.1.10
	 * @param int $utteranceId
	 * @param string|null $remoteWikiHash
	 * @param int $pageId
	 * @param string $language
	 * @param string $voice
	 * @param string $segmentHash
	 * @param MWTimestamp $dateStored
	 */
	public function __construct(
		int $utteranceId,
		?string $remoteWikiHash,
		int $pageId,
		string $language,
		string $voice,
		string $segmentHash,
		MWTimestamp $dateStored
	) {
		$this->utteranceId = $utteranceId;
		$this->remoteWikiHash = $remoteWikiHash;
		$this->pageId = $pageId;
		$this->language = $language;
		$this->voice = $voice;
		$this->segmentHash = $segmentHash;
		$this->dateStored = $dateStored;
	}

	/**
	 * @since 0.1.10
	 * @return int
	 */
	public function getUtteranceId(): int {
		return $this->utteranceId;
	}

	/**
	 * @since 0.1.10
	 * @param int $utteranceId
	 */
	public function setUtteranceId( int $utteranceId ): void {
		$this->utteranceId = $utteranceId;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getAudio(): ?string {
		return $this->audio;
	}

	/**
	 * @since 0.1.10
	 * @param string $audio
	 */
	public function setAudio( string $audio ): void {
		$this->audio = $audio;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getSynthesisMetadata(): ?string {
		return $this->synthesisMetadata;
	}

	/**
	 * @since 0.1.10
	 * @param string $synthesisMetadata
	 */
	public function setSynthesisMetadata( string $synthesisMetadata ): void {
		$this->synthesisMetadata = $synthesisMetadata;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getRemoteWikiHash(): ?string {
		return $this->remoteWikiHash;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $remoteWikiHash
	 */
	public function setRemoteWikiHash( ?string $remoteWikiHash ): void {
		$this->remoteWikiHash = $remoteWikiHash;
	}

	/**
	 * @since 0.1.10
	 * @return int
	 */
	public function getPageId(): int {
		return $this->pageId;
	}

	/**
	 * @since 0.1.10
	 * @param int $pageId
	 */
	public function setPageId( int $pageId ): void {
		$this->pageId = $pageId;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getLanguage(): string {
		return $this->language;
	}

	/**
	 * @since 0.1.10
	 * @param string $language
	 */
	public function setLanguage( string $language ): void {
		$this->language = $language;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getVoice(): string {
		return $this->voice;
	}

	/**
	 * @since 0.1.10
	 * @param string $voice
	 */
	public function setVoice( string $voice ): void {
		$this->voice = $voice;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getSegmentHash(): string {
		return $this->segmentHash;
	}

	/**
	 * @since 0.1.10
	 * @param string $segmentHash
	 */
	public function setSegmentHash( string $segmentHash ): void {
		$this->segmentHash = $segmentHash;
	}

	/**
	 * @since 0.1.10
	 * @return MWTimestamp
	 */
	public function getDateStored(): MWTimestamp {
		return $this->dateStored;
	}

	/**
	 * @since 0.1.10
	 * @param MWTimestamp $dateStored
	 */
	public function setDateStored( MWTimestamp $dateStored ): void {
		$this->dateStored = $dateStored;
	}

}
