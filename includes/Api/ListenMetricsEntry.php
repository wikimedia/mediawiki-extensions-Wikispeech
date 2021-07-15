<?php

namespace MediaWiki\Wikispeech\Api;

/**
 * @file
 * @ingroup API
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MWTimestamp;

/**
 * A journal entry of an utterance request.
 *
 * @since 0.1.10
 */
class ListenMetricsEntry {

	/**
	 * @var int|null
	 * @todo This is useless if we're only use the file journal.
	 */
	private $id = null;

	/** @var MWTimestamp|null */
	private $timestamp = null;

	/** @var int|null */
	private $segmentIndex = null;

	/** @var string|null */
	private $segmentHash = null;

	/** @var string|null */
	private $remoteWikiHash = null;

	/** @var string|null */
	private $consumerUrl = null;

	/** @var string|null */
	private $pageTitle = null;

	/** @var int|null */
	private $pageId = null;

	/** @var int|null */
	private $pageRevisionId = null;

	/** @var string|null */
	private $language = null;

	/** @var string|null */
	private $voice = null;

	/** @var int|null */
	private $microsecondsSpent = null;

	/** @var bool|null */
	private $utteranceSynthesized = null;

	/** @var int|null */
	private $millisecondsSpeechInUtterance = null;

	/** @var int|null */
	private $charactersInSegment = null;

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $id
	 */
	public function setId( ?int $id ): void {
		$this->id = $id;
	}

	/**
	 * @since 0.1.10
	 * @return MWTimestamp|null
	 */
	public function getTimestamp(): ?MWTimestamp {
		return $this->timestamp;
	}

	/**
	 * @since 0.1.10
	 * @param MWTimestamp|null $timestamp
	 */
	public function setTimestamp( ?MWTimestamp $timestamp ): void {
		$this->timestamp = $timestamp;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getSegmentIndex(): ?int {
		return $this->segmentIndex;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $segmentIndex
	 */
	public function setSegmentIndex( ?int $segmentIndex ): void {
		$this->segmentIndex = $segmentIndex;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getSegmentHash(): ?string {
		return $this->segmentHash;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $segmentHash
	 */
	public function setSegmentHash( ?string $segmentHash ): void {
		$this->segmentHash = $segmentHash;
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
	 * @return string|null
	 */
	public function getConsumerUrl(): ?string {
		return $this->consumerUrl;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $consumerUrl
	 */
	public function setConsumerUrl( ?string $consumerUrl ): void {
		$this->consumerUrl = $consumerUrl;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getPageTitle(): ?string {
		return $this->pageTitle;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $pageTitle
	 */
	public function setPageTitle( ?string $pageTitle ): void {
		$this->pageTitle = $pageTitle;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getPageId(): ?int {
		return $this->pageId;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $pageId
	 */
	public function setPageId( ?int $pageId ): void {
		$this->pageId = $pageId;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getPageRevisionId(): ?int {
		return $this->pageRevisionId;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $pageRevisionId
	 */
	public function setPageRevisionId( ?int $pageRevisionId ): void {
		$this->pageRevisionId = $pageRevisionId;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getLanguage(): ?string {
		return $this->language;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $language
	 */
	public function setLanguage( ?string $language ): void {
		$this->language = $language;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getVoice(): ?string {
		return $this->voice;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $voice
	 */
	public function setVoice( ?string $voice ): void {
		$this->voice = $voice;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getMicrosecondsSpent(): ?int {
		return $this->microsecondsSpent;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $microsecondsSpent
	 */
	public function setMicrosecondsSpent( ?int $microsecondsSpent ): void {
		$this->microsecondsSpent = $microsecondsSpent;
	}

	/**
	 * @since 0.1.10
	 * @return bool|null
	 */
	public function getUtteranceSynthesized(): ?bool {
		return $this->utteranceSynthesized;
	}

	/**
	 * @since 0.1.10
	 * @param bool|null $utteranceSynthesized
	 */
	public function setUtteranceSynthesized( ?bool $utteranceSynthesized ): void {
		$this->utteranceSynthesized = $utteranceSynthesized;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getMillisecondsSpeechInUtterance(): ?int {
		return $this->millisecondsSpeechInUtterance;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $millisecondsSpeechInUtterance
	 */
	public function setMillisecondsSpeechInUtterance( ?int $millisecondsSpeechInUtterance ): void {
		$this->millisecondsSpeechInUtterance = $millisecondsSpeechInUtterance;
	}

	/**
	 * @since 0.1.10
	 * @return int|null
	 */
	public function getCharactersInSegment(): ?int {
		return $this->charactersInSegment;
	}

	/**
	 * @since 0.1.10
	 * @param int|null $charactersInSegment
	 */
	public function setCharactersInSegment( ?int $charactersInSegment ): void {
		$this->charactersInSegment = $charactersInSegment;
	}

}
