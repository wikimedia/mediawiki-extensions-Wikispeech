<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\RegexFilter;

/**
 * @since 0.1.10
 */
class SwedishFilter extends RegexFilter {

	/**
	 * @since 0.1.10
	 */
	public function processRules(): void {
		// Internal order is important! An abstract syntax tree would be way nicer than regex...
		$this->processRule( new DateRule() );
		$this->processRule( new YearRangeRule() );
		$this->processRule( new YearRule() );
		$this->processRule( new NumberRule() );
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getSsmlLang(): string {
		return 'sv';
	}

}
