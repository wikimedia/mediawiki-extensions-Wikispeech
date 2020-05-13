<?php

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

class Util {

	/**
	 * @since 0.1.5
	 * @param string|Title $title
	 * @param string $content
	 * @param int $namespace Default is NS_MAIN.
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
	}
}
