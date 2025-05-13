<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use LogicException;

/**
 * Transforms texts using regular expressions.
 *
 * Iterates available {@link FilterPart}s and execute {@link RegexFilterRule}s
 * according to some logical rules as defined by the implementation.
 *
 * If a {@link RegexFilterRule} is matching a {@link FilterPart::$text},
 * it will potentially split prefix and suffix text in new {@link FilterPart}s
 * that will be further processed.
 *
 * Using regular expressions like this is very limited and will often require
 * them to be executed in a particular order. It is almost always better to
 * create and parse an abstract syntax tree, but simple regular expressions are,
 * when applicable, easier and faster to work with as a developer.
 *
 * Consider the following text:
 * 'He turned 32 on dec 30, 1321'
 *
 * If you execute the rule for numbers before the date rule, you'll end up with:
 * 'He turned thirty two on dec thirty, one thousand three hundred and twenty one'.
 *
 * Instead, if you execute the rule for date before the rule for numbers, you'll end up with:
 * 'He turned 32 on december thirty, thirteen hundred and twenty one'.
 *
 * The more regular expression based rules you have, the more complex this will become.
 *
 * @since 0.1.10
 */
abstract class RegexFilter extends Filter {

	/**
	 * @since 0.1.10
	 * @return string|null text/xml SSML output, or null if no rules applied
	 */
	public function process(): ?string {
		$this->processRules();
		return parent::process();
	}

	/**
	 * @since 0.1.10
	 */
	abstract public function processRules(): void;

	/**
	 * @since 0.1.10
	 * @param RegexFilterRule $rule
	 * @throws LogicException If expression is invalid.
	 */
	public function processRule(
		RegexFilterRule $rule
	): void {
		$hasChanges = true;
		while ( $hasChanges ) {
			$hasChanges = false;
			foreach ( $this->getParts() as $partIndex => $part ) {
				if ( $part->getAppliedRule() !== null ) {
					// Don't attempt to apply rules to a part which is the result of a previously invoked rule.
					continue;
				}
				$matches = [];
				$preg_matched = preg_match(
					$rule->getExpression(),
					$part->getText(),
					$matches,
					PREG_OFFSET_CAPTURE
				);

				if ( $preg_matched === false ) {
					throw new LogicException(
						"Bad expression '{$rule->getExpression()}' on text '{$part->getText()}'."
					);
				} elseif ( $preg_matched !== 1 ) {
					// regular expression of rule does not match
					continue;
				}

				$alias = $rule->createAlias( $matches );
				if ( $alias === null ) {
					// The regular expression of the rule matched,
					// but due to logic of the rule it did not produce an alias.
					continue;
				}
				$hasChanges = true;

				// Find out if there is any text before or after the matching group.
				// If so, then cut this out and add as new parts that might be processed
				// by other rules.

				// Matches contains start offset that are at byte level,
				// therefore we have to use strlen rather than mb_strlen below.

				/** @var int $startOffset */
				$startOffset = $matches[$rule->getMainGroup()][1];
				/** @var int $endOffset */
				$endOffset = $startOffset + strlen( $matches[$rule->getMainGroup()][0] );
				if ( $startOffset > 0 ) {
					$prefixPart = new FilterPart(
						substr( $part->getText(), 0, $startOffset )
					);
					$this->insertPart( $partIndex, $prefixPart );
					$partIndex++;
				}
				if ( $endOffset < strlen( $part->getText() ) ) {
					$suffixPart = new FilterPart(
						substr( $part->getText(), $endOffset )
					);
					$this->insertPart( $partIndex + 1, $suffixPart );
				}

				// Update part to contain only transformed information
				$part->setText( $matches[$rule->getMainGroup()][0] );
				$part->setAppliedRule( $rule );
				$part->setAlias( $alias );
				break;
			}
		}
	}

}
