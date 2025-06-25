<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Job;
use MediaWiki\Title\Title;

/**
 * @since 0.1.7
 */
class FlushUtterancesByExpirationDateOnFileJob extends Job {

	/** @var UtteranceStore */
	private $utteranceStore;

	/**
	 * @since 0.1.13 add service $utteranceStore to constructor
	 * @since 0.1.8
	 * @param Title $title
	 * @param array|null $params Ignored
	 * @param UtteranceStore $utteranceStore
	 */
	public function __construct( $title, $params, $utteranceStore ) {
		parent::__construct( 'flushUtterancesByExpirationDateOnFile', $title, $params );
		$this->utteranceStore = $utteranceStore;
	}

	/**
	 * @since 0.1.7
	 * @return bool success
	 */
	public function run() {
		$this->utteranceStore->flushUtterancesByExpirationDateOnFile();
		return true;
	}

}
