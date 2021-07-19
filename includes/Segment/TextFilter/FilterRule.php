<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

/**
 * A rule that transforms a text to something else with the same meaning, e.g:
 * '1' => 'one',
 * '1 jan 2000' => 'january first, two thousand'.
 *
 * Each {@link FilterPart} that has been transformed with an alias
 * is associated to the rule that caused the transformation.
 * How this is used depends on the implementation of
 * {@link \MediaWiki\Wikispeech\Segment\TextFilter\Filter}
 * used to invoke the rule.
 *
 * In the case of {@link RegexFilter} any {@link FilterPart} with
 * a rule set to {@link FilterPart::$rule} will not be further processed.
 *
 * A probable future implementation of {@link Filter} will be using a lexer/parser
 * that produce an abstract syntax tree. Here rules will represent leafs in the tree.
 *
 * @since 0.1.10
 */
interface FilterRule {

}
