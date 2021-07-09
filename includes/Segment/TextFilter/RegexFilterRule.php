<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * @since 0.1.10
 */
abstract class RegexFilterRule implements FilterRule {

	/** @var string Regular expression */
	private $expression;

	/** @var int|string Group in $expression matching rule */
	private $mainGroup;

	/**
	 * @since 0.1.10
	 * @param string $expression
	 * @param int|string $mainGroup
	 */
	public function __construct(
		string $expression,
		$mainGroup
	) {
		$this->expression = $expression;
		$this->mainGroup = $mainGroup;
	}

	/**
	 * Translates matched text according to the rules of the implementation.
	 *
	 * @todo If this returned an array of {@link FilterPart} instead of the alias string value,
	 *  then the response could represent multiple tokens. In some cases this would make for
	 *  better highlighting. E.g. 2002-2020 would then become three distinct tokens:
	 *  [ "two thousand two", "to", "two thousand twenty" ],
	 *  rather than the single composite token
	 *  "two thousand two to two thousand twenty".
	 *  It would however require a bit of refactoring.
	 *
	 * @since 0.1.10
	 * @param array $matches Matching groups produced by preg_match
	 * @return string|null Null if matches does not contain text that follow rules of this method.
	 */
	abstract public function createAlias( array $matches ): ?string;

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getExpression(): string {
		return $this->expression;
	}

	/**
	 * @since 0.1.10
	 * @return int|string
	 */
	public function getMainGroup() {
		return $this->mainGroup;
	}

}
