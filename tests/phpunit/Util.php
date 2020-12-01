<?php

namespace MediaWiki\Wikispeech\Tests;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use ContentHandler;
use Title;
use WikiPage;

class Util {

	/**
	 * Create a new page.
	 *
	 * @since 0.1.5
	 * @param string|Title $title
	 * @param string $content
	 * @param int $namespace Default is NS_MAIN.
	 * @return WikiPage
	 */
	public static function addPage(
		$title,
		$content,
		$namespace = NS_MAIN
	) {
		if ( is_string( $title ) ) {
			$title = Title::newFromText( $title, $namespace );
		}
		$page = WikiPage::factory( $title );
		$page->doEditContent(
			ContentHandler::makeContent(
				$content,
				$title,
				CONTENT_MODEL_WIKITEXT
			),
			''
		);
		return $page;
	}

	/**
	 * Edit an existing page.
	 *
	 * @since 0.1.5
	 * @param WikiPage $page
	 * @param string $content
	 */
	public static function editPage(
		$page,
		$content
	) {
		$page->doEditContent(
			ContentHandler::makeContent(
				$content,
				$page->getTitle(),
				CONTENT_MODEL_WIKITEXT
			),
			''
		);
	}
}
