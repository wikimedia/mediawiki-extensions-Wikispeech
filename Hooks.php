<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

require_once __DIR__ . '/includes/Cleaner.php';
require_once __DIR__ . '/includes/Segmenter.php';
require_once __DIR__ . '/includes/HtmlGenerator.php';

class WikispeechHooks {

	/**
	 * Conditionally register the unit testing module for the ext.wikispeech
	 * module only if that module is loaded
	 *
	 * @param array $testModules The array of registered test modules
	 * @param ResourceLoader $resourceLoader The reference to the resource
	 *	loader
	 * @return true
	 */

	public static function onResourceLoaderTestModules(
		array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		$testModules['qunit']['ext.wikispeech.tests'] = [
			'scripts' => [
				'tests/qunit/ext.wikispeech.test.js'
			],
			'dependencies' => [
				'ext.wikispeech'
			],
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikispeech',
		];
		return true;
	}

	/**
	 * Hook for ParserAfterTidy.
	 *
	 * Adds Wikispeech elements to the HTML, if the page is in the main
	 * namespace.
	 *
	 * @param $parser Parser object. Can be used to manually parse a portion
	 *	of wiki text from the $text.
	 * @param $text Represents the text for page.
	 */

	public static function onParserAfterTidy( &$parser, &$text ) {
		if ( $parser->getTitle()->getNamespace() == NS_MAIN && $text != "" ) {
			wfDebugLog(
				'Wikispeech',
				'HTML from onParserAfterTidy(): ' . $text
			);
			$cleanedText = Cleaner::cleanHtml( $text );
			wfDebugLog( 'Wikispeech', 'Cleaned text: ' . $cleanedText );
			$utterances = Segmenter::segmentSentences( $cleanedText );
			wfDebugLog(
				'Wikispeech',
				'Utterances: ' . var_export( $utterances, true )
			);
			$utterancesHtml =
				HtmlGenerator::generateUtterancesHtml( $utterances );
			wfDebugLog(
				'Wikispeech',
				'Adding utterances HTML: ' . $utterancesHtml
			);
			$text .= $utterancesHtml;
		}
	}

	/**
	 * Hook for BeforePageDisplay.
	 *
	 * Enables JavaScript.
	 *
	 * @param OutputPage $out The OutputPage object.
	 * @param Skin $skin Skin object that will be used to generate the page,
	 *	added in 1.13.
	 */

	public static function onBeforePageDisplay(
		OutputPage &$out,
		Skin &$skin
	) {
		$out->addModules( [ 'ext.wikispeech' ] );
	}
}
