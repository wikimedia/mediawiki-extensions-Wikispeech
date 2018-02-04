<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0+
 */

class WikispeechHooks {

	/**
	 * Conditionally register the unit testing module for the ext.wikispeech
	 * module only if that module is loaded.
	 *
	 * @param array &$testModules The array of registered test modules
	 * @param ResourceLoader &$resourceLoader The reference to the resource
	 *  loader
	 * @return true
	 */
	public static function onResourceLoaderTestModules(
		array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		$testModules['qunit']['ext.wikispeech.test'] = [
			'scripts' => [
				'tests/qunit/ext.wikispeech.test.js',
				'tests/qunit/ext.wikispeech.highlighter.test.js'
			],
			'dependencies' => [
				// Despite what it says at
				// https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules,
				// adding 'ext.wikispeech.highlighter' isn't needed
				// and in fact breaks the testing.
				'ext.wikispeech'
			],
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'Wikispeech'
		];
		return true;
	}

	/**
	 * Hook for ParserAfterTidy.
	 *
	 * Adds Wikispeech elements to the HTML, if the page is in the main
	 * namespace.
	 *
	 * @param Parser &$parser Can be used to manually parse a portion of wiki
	 *  text from the $text.
	 * @param string &$text Represents the text for page.
	 */
	public static function onParserAfterTidy( &$parser, &$text ) {
		if ( self::isValidNamespace( $parser->getTitle()->getNamespace() ) &&
			 $text != ""
		) {
			wfDebugLog(
				'Wikispeech',
				'HTML from onParserAfterTidy(): ' . $text
			);
			global $wgWikispeechRemoveTags;
			global $wgWikispeechSegmentBreakingTags;
			$cleaner = new Cleaner(
				$wgWikispeechRemoveTags,
				$wgWikispeechSegmentBreakingTags
			);
			$cleanedContent = $cleaner->cleanHtml( $text );
			wfDebugLog(
				'Wikispeech',
				'Cleaned text: ' . var_export( $cleanedContent, true )
			);
			$segmenter = new Segmenter();
			$utterances = $segmenter->segmentSentences( $cleanedContent );
			wfDebugLog(
				'Wikispeech',
				'Utterances: ' . var_export( $utterances, true )
			);
			$utterancesHtml =
				HtmlGenerator::createUtterancesHtml( $utterances );
			wfDebugLog(
				'Wikispeech',
				'Adding utterances HTML: ' . $utterancesHtml
			);
			$text .= $utterancesHtml;
		}
	}

	/**
	 * Test if a namespace is valid for wikispeech.
	 *
	 * @param int $namespace The namespace id to test.
	 * @return bool true if the namespace id matches one defined in
	 *  $wgWikispeechNamespaces, else false.
	 */
	private static function isValidNamespace( $namespace ) {
		global $wgWikispeechNamespaces;
		foreach ( $wgWikispeechNamespaces as $namespaceId ) {
			if ( defined( $namespaceId ) &&
				$namespace == constant( $namespaceId )
			) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Hook for BeforePageDisplay.
	 *
	 * Enables JavaScript.
	 *
	 * @param OutputPage &$out The OutputPage object.
	 * @param Skin &$skin Skin object that will be used to generate the page,
	 *  added in 1.13.
	 */
	public static function onBeforePageDisplay(
		OutputPage &$out,
		Skin &$skin
	) {
		$out->addModules( [
			'ext.wikispeech',
			'ext.wikispeech.highlighter'
		] );
	}

	/**
	 * Conditionally register static configuration variables for the
	 * ext.wikispeech module only if that module is loaded.
	 *
	 * @param array &$vars The array of static configuration variables.
	 * @return true
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgWikispeechServerUrl;
		$vars['wgWikispeechServerUrl'] = $wgWikispeechServerUrl;
		global $wgWikispeechKeyboardShortcuts;
		$vars['wgWikispeechKeyboardShortcuts'] =
			$wgWikispeechKeyboardShortcuts;
		global $wgWikispeechSkipBackRewindsThreshold;
		$vars['wgWikispeechSkipBackRewindsThreshold'] =
			$wgWikispeechSkipBackRewindsThreshold;
		global $wgWikispeechHelpPage;
		$vars['wgWikispeechHelpPage'] =
			$wgWikispeechHelpPage;
		global $wgWikispeechFeedbackPage;
		$vars['wgWikispeechFeedbackPage'] =
			$wgWikispeechFeedbackPage;
		global $wgWikispeechNamespaces;
		$vars['wgWikispeechNamespaces'] =
			$wgWikispeechNamespaces;
		global $wgWikispeechContentSelector;
		$vars['wgWikispeechContentSelector'] =
			$wgWikispeechContentSelector;
		return true;
	}
}
