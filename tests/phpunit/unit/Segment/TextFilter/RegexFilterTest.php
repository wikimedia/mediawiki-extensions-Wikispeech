<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\RegexFilter;
use MediaWiki\Wikispeech\Segment\TextFilter\RegexFilterRule;
use MediaWikiUnitTestCase;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\RegexFilter
 */
class RegexFilterTest extends MediaWikiUnitTestCase {

	private function filterFactory(
		string $text
	): RegexFilter {
		return new class( $text ) extends RegexFilter {
			public function processRules(): void {
				$this->processRule(
					new class(
						'/(^|\W)(and)(\W|$)/', 2
					) extends RegexFilterRule {
						public function createAlias( array $matches ): ?string {
							return 'alias';
						}
					}
				);
			}

			public function getSsmlLang(): string {
				return "foo";
			}
		};
	}

	public function testFilter_noRulesApplied_processReturnNull() {
		$filter = $this->filterFactory( 'what not really' );
		$this->assertCount( 1, $filter->getParts() );
		$this->assertNull( $filter->getParts()[0]->getAlias() );
		$this->assertNull( $filter->getParts()[0]->getAppliedRule() );
		$this->assertNull( $filter->process() );
	}

	public function testProcessRule_prefixAndSuffix() {
		$filter = $this->filterFactory( 'this is the prefix and this is the suffix' );
		$this->assertCount( 1, $filter->getParts() );
		$filter->processRules();
		$this->assertCount( 3, $filter->getParts() );
		$this->assertSame( 'this is the prefix ', $filter->getParts()[0]->getText() );
		$this->assertNull( $filter->getParts()[0]->getAlias() );
		$this->assertSame( 'and', $filter->getParts()[1]->getText() );
		$this->assertNotNull( $filter->getParts()[1]->getAlias() );
		$this->assertSame( ' this is the suffix', $filter->getParts()[2]->getText() );
		$this->assertNull( $filter->getParts()[2]->getAlias() );
	}

	public function testProcessRule_prefix() {
		$filter = $this->filterFactory( 'this is the prefix and' );
		$this->assertCount( 1, $filter->getParts() );
		$filter->processRules();
		$this->assertCount( 2, $filter->getParts() );
		$this->assertSame( 'this is the prefix ', $filter->getParts()[0]->getText() );
		$this->assertNull( $filter->getParts()[0]->getAlias() );
		$this->assertSame( 'and', $filter->getParts()[1]->getText() );
		$this->assertNotNull( $filter->getParts()[1]->getAlias() );
	}

	public function testProcessRule_suffix() {
		$filter = $this->filterFactory( 'and this is the suffix' );
		$this->assertCount( 1, $filter->getParts() );
		$filter->processRules();
		$this->assertCount( 2, $filter->getParts() );
		$this->assertSame( 'and', $filter->getParts()[0]->getText() );
		$this->assertNotNull( $filter->getParts()[0]->getAlias() );
		$this->assertSame( ' this is the suffix', $filter->getParts()[1]->getText() );
		$this->assertNull( $filter->getParts()[1]->getAlias() );
	}

	public function testProcessRule_onlyMatch() {
		$filter = $this->filterFactory( 'and' );
		$this->assertCount( 1, $filter->getParts() );
		$filter->processRules();
		$this->assertCount( 1, $filter->getParts() );
		$this->assertSame( 'and', $filter->getParts()[0]->getText() );
		$this->assertNotNull( $filter->getParts()[0]->getAlias() );
	}

	public function testProcessRule_noMatch() {
		$filter = $this->filterFactory( 'och' );
		$this->assertCount( 1, $filter->getParts() );
		$filter->processRules();
		$this->assertCount( 1, $filter->getParts() );
		$this->assertSame( 'och', $filter->getParts()[0]->getText() );
		$this->assertNull( $filter->getParts()[0]->getAlias() );
	}

}
