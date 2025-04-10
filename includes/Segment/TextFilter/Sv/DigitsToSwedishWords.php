<?php

namespace MediaWiki\Wikispeech\Segment\TextFilter\Sv;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Wikispeech\Segment\TextFilter\AbstractDigitsToWords;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Translates digits to words in Swedish.
 * Supports gender of the number 1, i.e. 'en' or 'ett',
 * e.g. the difference between 901 as "niohundra ett" and "niohundra en".
 * It is however up to the developer to know and select the grammatical context.
 *
 * @see DigitsToSwedishWords
 * @since 0.1.10
 */
class DigitsToSwedishWords extends AbstractDigitsToWords {

	/** @var LoggerInterface */
	private $logger;

	/** @var string[] ordinal text value of values less than 20: first, second... nineteenth. */
	private const SUB_DECA_ORDINALS = [
		'PLACEHOLDER FOR INDEX ZERO',
		'första', 'andra', 'tredje', 'fjärde', 'femte', 'sjätte', 'sjunde', 'åttonde', 'nionde',
		'tionde', 'elfte', 'tolfte', 'trettonde', 'fjortonde',
		'femtonde', 'sextonde', 'sjuttonde', 'artonde', 'nittonde'
	];

	/** @var string[] text values values less than 20: zero, one, two... nineteen. */
	private const SUB_DECAS = [
		'noll',
		'ONE PLACEHOLDER', 'två', 'tre', 'fyra', 'fem', 'sex', 'sju', 'åtta', 'nio',
		'tio', 'elva', 'tolv', 'tretton', 'fjorton', 'femton', 'sexton', 'sjutton', 'arton', 'nitton'
	];

	/** @var string[] text value of decas: zero, ten, twenty... ninety. */
	private const DECAS = [
		'PLACEHOLDER FOR INDEX ZERO',
		'tio', 'tjugo', 'trettio', 'fyrtio', 'femtio', 'sextio', 'sjuttio', 'åttio', 'nittio'
	];

	/**
	 * @var array[] <integer value, text value, definiteness, plural suffix>
	 *
	 * Definiteness is the singular prefix for the definite noun.
	 * I.e. there are two species for 'one' in Swedish: 'en' and 'ett'.
	 */
	private const MAGNITUDES = [
		[ 10, 'tio', null, null ],
		[ 100, 'hundra', 'ett', '' ],
		[ 1000, 'tusen', 'ett', '' ],
		[ 1000000, 'miljon', 'en', 'er' ],
		[ 1000000000, 'miljard', 'en', 'er' ],
		[ 1000000000000, 'biljon', 'en', 'er' ],
		[ 1000000000000000, 'biljard', 'en', 'er' ],
		[ 1000000000000000000, 'triljon', 'en', 'er' ],
		[ 1000000000000000000000, 'triljard', 'en', 'er' ],
	];

	/**
	 * @var string There are two genders for 'one' in Swedish: 'en' and 'ett'.
	 * Setting this value to 'en' cause an output such as 'nio hundra en' as in a sum,
	 * while 'ett' cause an output such as 'nio hundra ett' as in the year.
	 */
	private $one;

	/**
	 * @since 0.1.10
	 * @param string $one There are two species for 'one' in Swedish: 'en' and 'ett'.
	 */
	public function __construct( string $one = 'ett' ) {
		$this->one = $one;
		$this->logger = LoggerFactory::getInstance( 'Wikispeech' );
	}

	/**
	 * Translate integer to ordinal text value, e.g. 1 -> 'första', 2 -> 'andra'.
	 *
	 * @since 0.1.10
	 * @param int $input
	 * @return string|null Null if input number is not supported
	 */
	public function intToOrdinal( int $input ): ?string {
		if ( $input < 1 || $input > 99 ) {
			// @todo implement support
			$this->logger->debug( __METHOD__ .
				': Input must be greater than 1  and less than 99 but was {input}', [
					'input' => $input,
				] );
			return null;
		}
		if ( $input < 20 ) {
			return self::SUB_DECA_ORDINALS[ $input ];
		}
		$floor = intval( floor( $input / 10 ) );
		$word = self::DECAS[ $floor ];
		$leftovers = $input % 10;
		if ( $leftovers === 0 ) {
			$word .= 'nde';
		} else {
			$word .= self::SUB_DECA_ORDINALS[ $leftovers ];
		}
		return $word;
	}

	/**
	 * Translate integer to text value, e.g. 1 -> 'ett', 13 -> 'tretton'.
	 *
	 * @since 0.1.10
	 * @param int $input
	 * @return string|null Null if input value is not supported.
	 */
	public function intToWords( int $input ): ?string {
		$words = $this->buildWords( $input );
		if ( $words === null ) {
			// @todo log?
		}
		return $words;
	}

	/**
	 * Translate floating point to text value, e.g. the floating point ( 3.14 ) -> 'tre komma ett fyra'.
	 *
	 * @since 0.1.10
	 * @param int $integer Integer part of the floating value
	 * @param string|null $decimals Decimals part of the floating value as string value
	 * @return string|null Null if input number is not supported
	 */
	public function stringFloatToWords(
		int $integer,
		?string $decimals = null
	): ?string {
		// @todo assert decimals are all numbers?
		if ( $decimals === null ) {
			return $this->intToWords( $integer );
		}
		$integerWords = $this->intToWords( $integer );
		if ( $integerWords === null ) {
			// @todo log?
			return null;
		}
		$numberOfDecimals = strlen( $decimals );
		if ( $numberOfDecimals < 3 && $decimals[0] !== '0' ) {
			$decimalWords = $this->intToWords( intval( $decimals ) );
		} else {
			$decimalWords = '';
			for ( $decimalIndex = 0; $decimalIndex < $numberOfDecimals; $decimalIndex++ ) {
				$decimalWord = $this->intToWords( intval( $decimals[$decimalIndex] ) );
				if ( $decimalWord === null ) {
					// @todo log?
					return null;
				}
				if ( $decimalWords !== '' ) {
					$decimalWords .= ' ';
				}
				$decimalWords .= $decimalWord;
			}
		}
		return $integerWords . ' komma ' . $decimalWords;
	}

	/**
	 * @since 0.1.10
	 * @param int $inputNumber
	 * @param array|null $invokingMagnitude
	 * @return string
	 */
	private function getSubDeca(
		int $inputNumber,
		?array $invokingMagnitude
	): string {
		if ( $invokingMagnitude !== null && $inputNumber === 1 ) {
			return $invokingMagnitude[2];
		} elseif ( $inputNumber === 1 ) {
			return $this->one;
		} else {
			return self::SUB_DECAS[ $inputNumber ];
		}
	}

	/**
	 * @since 0.1.10
	 * @param array $wordsBuilder
	 * @return string
	 */
	private function assembleWords( array $wordsBuilder ): string {
		return implode( ' ', $wordsBuilder );
	}

	/**
	 * @since 0.1.10
	 * @param int $inputNumber
	 * @param array $wordsBuilder
	 * @param array|null $invokingMagnitude
	 * @return string|null
	 */
	private function buildWords(
		int $inputNumber,
		array $wordsBuilder = [],
		?array $invokingMagnitude = null
	): ?string {
		if ( $inputNumber === 0 ) {
			if ( count( $wordsBuilder ) === 0 ) {
				return self::SUB_DECAS[0];
			} else {
				return $this->assembleWords( $wordsBuilder );
			}
		}
		$leftovers = null;
		if ( $inputNumber < 20 ) {
			$wordsBuilder[] = $this->getSubDeca( $inputNumber, $invokingMagnitude );
			return $this->assembleWords( $wordsBuilder );
		} elseif ( $inputNumber < 100 ) {
			$word = self::DECAS[ intval( floor( $inputNumber / 10 ) ) ];
			$leftovers = $inputNumber % 10;
			if ( $leftovers > 0 ) {
				$word .= $this->getSubDeca( $leftovers, $invokingMagnitude );
				$wordsBuilder[] = $word;
				return $this->assembleWords( $wordsBuilder );
			}
			$wordsBuilder[] = $word;
		} else {
			$found = false;
			$magnitudesCount = count( self::MAGNITUDES );
			for ( $i = 2; $i < $magnitudesCount; $i++ ) {
				$magnitude = self::MAGNITUDES[ $i ];
				if ( $inputNumber < $magnitude[0] ) {
					$previousMagnitude = self::MAGNITUDES[ $i - 1 ];
					$floor = intval( floor( $inputNumber / $previousMagnitude[0] ) );
					$word = $this->buildWords( $floor, [], $previousMagnitude );
					if ( $word === null ) {
						// Don't log here, this is a recursive action that will flood the log.
						// Perhaps log if invokingMagnitude is null.
						return null;
					}
					$word .= ' ';
					$word .= $previousMagnitude[1];
					if ( $floor > 1 ) {
						$word .= $previousMagnitude[3];
					}
					$wordsBuilder[] = $word;
					$leftovers = $inputNumber % $previousMagnitude[0];
					$found = true;
					break;
				}
			}
			if ( !$found ) {
				$this->logger->debug( __METHOD__ .
					': Input number is too large to be handled: {inputNumber}', [
						'inputNumber' => $inputNumber,
					] );
				return null;
			}
		}

		if ( $leftovers === null ) {
			throw new RuntimeException( 'Bad code, this should never occur!' );
		}
		if ( $leftovers === 0 ) {
			return $this->assembleWords( $wordsBuilder );
		}
		return $this->buildWords( $leftovers, $wordsBuilder, $invokingMagnitude );
	}

}
