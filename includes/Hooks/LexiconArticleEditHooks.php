<?php

namespace MediaWiki\Wikispeech\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Article;
use MediaWiki\Hook\CustomEditorHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use SkinTemplate;
use SpecialPage;
use User;

/**
 * Redirects edits of lexicon wiki pages to SpecialPage:EditLexicon
 *
 * @since 0.1.9
 */
class LexiconArticleEditHooks implements CustomEditorHook, SkinTemplateNavigation__UniversalHook {

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

		$request = $article->getContext()->getRequest();
		if ( $request->getText( "action" ) === "submit" ) {
			return true;
		}

		if (
			$request->getBool( "raw" )
			&& $user->isAllowed( 'wikispeech-edit-lexicon-raw' )
		) {
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
		return false;
	}

	/**
	 * Add a tab for editing an entry as text.
	 *
	 * @since 0.1.11
	 * @param SkinTemplate $skinTemplate The skin template on which
	 *  the UI is built.
	 * @param array &$links Navigation links.
	 */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		if ( !$skinTemplate->getTitle()->inNamespace( NS_PRONUNCIATION_LEXICON ) ) {
			return;
		}

		if ( !$skinTemplate->getUser()->isAllowed( 'wikispeech-edit-lexicon-raw' ) ) {
			return;
		}

		$title = $skinTemplate->getTitle();
		$url = $title->getLinkURL( [ 'action' => 'edit', 'raw' => 1 ] );
		$links['views']['editlexiconraw'] = [
			'text' => $skinTemplate->msg( 'wikispeech-edit-lexicon-raw' )->text(),
			'href' => $url
		];
	}
}
