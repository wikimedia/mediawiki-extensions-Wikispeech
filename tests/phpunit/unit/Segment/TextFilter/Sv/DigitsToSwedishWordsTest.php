<?php

namespace MediaWiki\Wikispeech\Tests\Unit\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Wikispeech\Segment\TextFilter\Sv\DigitsToSwedishWords;
use MediaWikiUnitTestCase;

/**
 * @since 0.1.10
 * @covers \MediaWiki\Wikispeech\Segment\TextFilter\Sv\DigitsToSwedishWords
 */
class DigitsToSwedishWordsTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provide_testIntToOrdinal
	 */
	public function testIntToOrdinal( int $digits, string $words ) {
		$digits2words = new DigitsToSwedishWords();
		$this->assertSame( $words, $digits2words->intToOrdinal( $digits ) );
	}

	public function provide_testIntToOrdinal(): array {
		return [
			'1' => [ 1, 'första' ],
			'10' => [ 10, 'tionde' ],
			'20' => [ 20, 'tjugonde' ],
			'21' => [ 21, 'tjugoförsta' ],
			'22' => [ 22, 'tjugoandra' ],
			'23' => [ 23, 'tjugotredje' ],
			'24' => [ 24, 'tjugofjärde' ],
			'25' => [ 25, 'tjugofemte' ],
			'26' => [ 26, 'tjugosjätte' ],
			'27' => [ 27, 'tjugosjunde' ],
			'28' => [ 28, 'tjugoåttonde' ],
			'29' => [ 29, 'tjugonionde' ],
		];
	}

	/**
	 * @dataProvider provide_testIntToWord
	 */
	public function testIntToWords( int $digits, string $words ) {
		$digits2words = new DigitsToSwedishWords();
		$this->assertSame( $words, $digits2words->intToWords( $digits ) );
	}

	// phpcs:disable
	public function provide_testIntToWord(): array {
		return [
			'0' => [ 0, 'noll' ],
			'1' => [ 1, 'ett' ],
			'12' => [ 12, 'tolv' ],
			'123' => [ 123, 'ett hundra tjugotre' ],
			'1234' => [ 1234, 'ett tusen två hundra trettiofyra' ],
			'12345' => [ 12345, 'tolv tusen tre hundra fyrtiofem' ],
			'123456' => [ 123456, 'ett hundra tjugotre tusen fyra hundra femtiosex' ],
			'1234567' => [ 1234567, 'en miljon två hundra trettiofyra tusen fem hundra sextiosju' ],
			'12345678' => [ 12345678, 'tolv miljoner tre hundra fyrtiofem tusen sex hundra sjuttioåtta' ],
			'123456789' => [ 123456789, 'ett hundra tjugotre miljoner fyra hundra femtiosex tusen sju hundra åttionio' ],
			'1234567890' => [ 1234567890, 'en miljard två hundra trettiofyra miljoner fem hundra sextiosju tusen åtta hundra nittio' ],
			'12345678901' => [ 12345678901, 'tolv miljarder tre hundra fyrtiofem miljoner sex hundra sjuttioåtta tusen nio hundra ett' ],
			'123456789012' => [ 123456789012, 'ett hundra tjugotre miljarder fyra hundra femtiosex miljoner sju hundra åttionio tusen tolv' ],
			'1234567890123' => [ 1234567890123, 'en biljon två hundra trettiofyra miljarder fem hundra sextiosju miljoner åtta hundra nittio tusen ett hundra tjugotre' ],
			'12345678901234' => [ 12345678901234, 'tolv biljoner tre hundra fyrtiofem miljarder sex hundra sjuttioåtta miljoner nio hundra ett tusen två hundra trettiofyra' ],
			'123456789012345' => [ 123456789012345, 'ett hundra tjugotre biljoner fyra hundra femtiosex miljarder sju hundra åttionio miljoner tolv tusen tre hundra fyrtiofem' ],
			'1234567890123456' => [ 1234567890123456, 'en biljard två hundra trettiofyra biljoner fem hundra sextiosju miljarder åtta hundra nittio miljoner ett hundra tjugotre tusen fyra hundra femtiosex' ],
			'12345678901234567' => [ 12345678901234567, 'tolv biljarder tre hundra fyrtiofem biljoner sex hundra sjuttioåtta miljarder nio hundra en miljoner två hundra trettiofyra tusen fem hundra sextiosju' ],
			'123456789012345678' => [ 123456789012345678, 'ett hundra tjugotre biljarder fyra hundra femtiosex biljoner sju hundra åttionio miljarder tolv miljoner tre hundra fyrtiofem tusen sex hundra sjuttioåtta' ],
			'1234567890123456789' => [ 1234567890123456789, 'en triljon två hundra trettiofyra biljarder fem hundra sextiosju biljoner åtta hundra nittio miljarder ett hundra tjugotre miljoner fyra hundra femtiosex tusen sju hundra åttionio' ],
			'100' => [ 100, 'ett hundra' ],
			'1000' => [ 1000, 'ett tusen' ],
			'10000' => [ 10000, 'tio tusen' ],
			'100000' => [ 100000, 'ett hundra tusen' ],
			'1000000' => [ 1000000, 'en miljon' ],
			'2000000' => [ 2000000, 'två miljoner' ],
			'901' => [ 901, 'nio hundra ett' ],
			'901000000' => [ 901000000, 'nio hundra en miljoner' ],
		];
	}
	// phpcs:enable

	/**
	 * @dataProvider provide_testFloatToWords
	 */
	public function testFloatToWords( float $digits, string $words ) {
		$digits2words = new DigitsToSwedishWords();
		$this->assertSame( $words, $digits2words->floatToWords( $digits ) );
	}

	public function provide_testFloatToWords(): array {
		return [
			'3' => [ 3, 'tre' ],
			'3,0' => [ 3.0, 'tre' ],
			'3,1' => [ 3.1, 'tre komma ett' ],
			'3,14' => [ 3.14, 'tre komma fjorton' ],
			'3,1415' => [ 3.1415, 'tre komma ett fyra ett fem' ],
			'21,21' => [ 21.21, 'tjugoett komma tjugoett' ],
		];
	}

	/**
	 * @dataProvider provide_testStringFloatToWords
	 */
	public function testStringFloatToWords( int $integer, ?string $decimals, string $words ) {
		$digits2words = new DigitsToSwedishWords();
		$this->assertSame( $words, $digits2words->stringFloatToWords( $integer, $decimals ) );
	}

	public function provide_testStringFloatToWords(): array {
		return [
			'3' => [ 3, null, 'tre' ],
			'3,0' => [ 3, '0', 'tre komma noll' ],
			'3,00' => [ 3, '00', 'tre komma noll noll' ],
			'3,001' => [ 3, '001', 'tre komma noll noll ett' ],
			'3,1' => [ 3, '1', 'tre komma ett' ],
			'3,14' => [ 3, '14', 'tre komma fjorton' ],
			'3,1415' => [ 3, '1415', 'tre komma ett fyra ett fem' ],
		];
	}
}
