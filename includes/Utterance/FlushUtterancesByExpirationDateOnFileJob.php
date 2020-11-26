<?php

namespace MediaWiki\Wikispeech\Utterance;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Job;
use Title;

/**
 * @since 0.1.7
 */
class FlushUtterancesByExpirationDateOnFileJob extends Job {

	/**
	 * @since 0.1.8
	 * @param Title $title
	 * @param array|null $params Ignored
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'flushUtterancesByExpirationDateOnFile', $title, $params );
	}

	/**
	 * @since 0.1.7
	 * @return bool success
	 */
	public function run() {
		$utteranceStore = new UtteranceStore();
		$utteranceStore->flushUtterancesByExpirationDateOnFile();
		return true;
	}

}
