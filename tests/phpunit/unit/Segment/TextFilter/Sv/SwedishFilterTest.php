<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\Sv\SwedishFilter;
use MediaWikiUnitTestCase;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\Sv\SwedishFilter
 */
class SwedishFilterTest extends MediaWikiUnitTestCase {

	public function testProcessRule_yearNumbersAndDate() {
		$filter = new SwedishFilter(
			'Mot slutet av 1800-talet hade Strindberg skrivit mer än 90 stycken böcker och pjäser.' .
			' Den totala upplagan uppnådde under hands livstid 292 000,' .
			' men den 18 november 1894 eldade man av misstag upp 12.430 av dem.' .
			' Åren 1890–1894 söp han bort.' .
			' Strindberg var 173,21 centimeter lång och blev 63 år gammal.'
		);
		$this->assertSame(
			'<speak xml:lang="sv" version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" ' .
			'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
			'xsi:schemalocation="http://www.w3.org/2001/10/synthesis ' .
			'http://www.w3.org/TR/speech-synthesis/synthesis.xsd">Mot slutet av ' .
			'<sub alias="arton hundra">1800</sub>-talet hade Strindberg skrivit mer än ' .
			'<sub alias="nittio">90</sub> stycken böcker och pjäser. ' .
			'Den totala upplagan uppnådde under hands livstid ' .
			'<sub alias="två hundra nittiotvå tusen">292 000</sub>, men den ' .
			'<sub alias="artonde november arton hundra nittiofyra">18 november 1894</sub> ' .
			'eldade man av misstag upp ' .
			'<sub alias="tolv tusen fyra hundra trettio">12.430</sub> av dem. ' .
			'Åren ' .
			'<sub alias="arton hundra nittio till arton hundra nittiofyra">1890–1894</sub> söp han bort. ' .
			'Strindberg var ' .
			'<sub alias="ett hundra sjuttiotre komma tjugoett">173,21</sub> centimeter lång och blev ' .
			'<sub alias="sextiotre">63</sub> år gammal.</speak>',
			$filter->process() );
	}

}
