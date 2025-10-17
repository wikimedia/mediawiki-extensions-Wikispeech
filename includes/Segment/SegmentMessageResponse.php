<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @since 0.1.14
 */
class SegmentMessageResponse extends SegmentResponse {

	/** @var string */
	private $messageKey;

	/** @var string */
	private $language;

	/**
	 * @since 0.1.14
	 * @param string $messageKey
	 */
	public function setMessageKey( string $messageKey ): void {
		$this->messageKey = $messageKey;
	}

	/**
	 * @since 0.1.14
	 * @return string $messageKey
	 */
	public function getMessageKey(): string {
		return $this->messageKey;
	}

	/**
	 * @since 0.1.14
	 * @param string $language
	 */
	public function setLanguage( string $language ): void {
		$this->language = $language;
	}

	/**
	 * @since 0.1.14
	 * @return string $language
	 */
	public function getLanguage(): string {
		return $this->language;
	}
}
