<?php

namespace MediaWiki\Wikispeech\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Article;
use MediaWiki\Hook\CustomEditorHook;
use SpecialPage;
use User;

/**
 * Redirects edits of lexicon wiki pages to SpecialPage:EditLexicon
 *
 * @since 0.1.9
 */
class LexiconArticleEditHook implements CustomEditorHook {

	/**
	 * This hook is called when invoking the page editor.
	 *
	 * @since 0.1.9
	 * @param Article $article Article being edited
	 * @param User $user User performing the edit
	 * @return bool|void True or no return value to allow the normal editor to be used.
	 *   False if implementing a custom editor, e.g. for a special namespace, etc.
	 */
	public function onCustomEditor(
		$article,
		$user
	) {
		if ( !$article->getTitle()->inNamespace( NS_PRONUNCIATION_LEXICON ) ) {
			return true;
		}
		$tuple = explode( '/', $article->getTitle()->getText(), 2 );
		$language = mb_strtolower( $tuple[0] );
		$key = $tuple[1];

		$editLexicon = SpecialPage::getTitleFor( 'EditLexicon' );
		$article->getContext()->getOutput()->redirect(
			$editLexicon->getFullURL( [
				'word' => $key,
				'language' => $language,
			] )
		);
		return true;
	}
}
