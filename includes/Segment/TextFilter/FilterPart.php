<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * A snippet of text (could be the complete text) that may or may not have been transformed
 * to a new text alias using a {@link FilterRule}.
 *
 * In the end, this represents an SSML <sub/> node.
 *
 * @since 0.1.10
 */
class FilterPart {

	/** @var string */
	private $text;

	/** @var string|null */
	private $alias;

	/** @var FilterRule|null */
	private $appliedRule;

	/**
	 * @since 0.1.10
	 * @param string $text
	 * @param string|null $alias
	 * @param FilterRule|null $rule
	 */
	public function __construct(
		string $text,
		?string $alias = null,
		?FilterRule $rule = null
	) {
		$this->text = $text;
		$this->alias = $alias;
		$this->appliedRule = $rule;
	}

	/**
	 * @since 0.1.10
	 * @return string
	 */
	public function getText(): string {
		return $this->text;
	}

	/**
	 * @since 0.1.10
	 * @param string $text
	 */
	public function setText( string $text ): void {
		$this->text = $text;
	}

	/**
	 * @since 0.1.10
	 * @return string|null
	 */
	public function getAlias(): ?string {
		return $this->alias;
	}

	/**
	 * @since 0.1.10
	 * @param string|null $alias
	 */
	public function setAlias( ?string $alias ): void {
		$this->alias = $alias;
	}

	/**
	 * @since 0.1.10
	 * @return FilterRule|null
	 */
	public function getAppliedRule(): ?FilterRule {
		return $this->appliedRule;
	}

	/**
	 * @since 0.1.10
	 * @param FilterRule|null $appliedRule
	 */
	public function setAppliedRule( ?FilterRule $appliedRule ): void {
		$this->appliedRule = $appliedRule;
	}

}
