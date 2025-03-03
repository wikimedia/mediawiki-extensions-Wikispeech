<?php

namespace MediaWiki\Wikispeech\Tests\Integration\Hooks;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\Wikispeech\Hooks\LexiconArticleEditHooks;
use MediaWikiIntegrationTestCase;
use SkinTemplate;

/**
 * @group Database
 * @covers MediaWiki\Wikispeech\Hooks\LexiconArticleEditHooks
 */
class LexiconArticleEditHooksTest extends MediaWikiIntegrationTestCase {

	/** @var LexiconArticleEditHooks */
	private $hooks;

	protected function setUp(): void {
		parent::setUp();

		$this->hooks = new LexiconArticleEditHooks();
	}

	public static function provideAddLink() {
		return [
			'valid' => [
				'title' => 'Pronunciation lexicon:Entry',
				'rights' => [ 'wikispeech-edit-lexicon-raw' ],
				'expected views' => [
					'text' => 'Edit raw',
					'href' => '/index.php?title=Pronunciation_lexicon:Entry&action=edit&raw=1'
				]
			],
			'not lexicon page' => [
				'title' => 'Main',
				'rights' => [ 'wikispeech-edit-lexicon-raw' ],
				'expected views' => null
			],
			'lacking rights' => [
				'title' => 'Pronunciation lexicon:Entry',
				'rights' => [],
				'expected views' => null
			]
		];
	}

	/**
	 * @dataProvider provideAddLink
	 */
	public function testOnSkinTemplateNavigation__Universal_addEditRawLink( $titleString, $rights, $expecedLinks ) {
		$skinTemplate = new SkinTemplate();
		$links = [ 'views' => [] ];
		$context = RequestContext::getMain();
		$title = Title::newFromText( $titleString );
		$context->setTitle( $title );
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, $rights );
		$context->setUser( $user );
		$skinTemplate->setContext( $context );

		$this->hooks->onSkinTemplateNavigation__Universal( $skinTemplate, $links );

		$this->assertEquals( $expecedLinks, $links['views']['editlexiconraw'] ?? null );
	}

	public static function provideCustomEditor() {
		return [
			'valid' => [
				'title' => 'Pronunciation lexicon:Language/entry',
				'request parameters' => [],
				'rights' => [ 'wikispeech-edit-lexicon-raw' ],
				'should use custom editor' => true
			],
			'not lexicon page' => [
				'title' => 'Main',
				'request parameters' => [],
				'rights' => [ 'wikispeech-edit-lexicon-raw' ],
				'should use custom editor' => false
			],
			'submitted' => [
				'title' => 'Pronunciation lexicon:Language/entry',
				'request parameters' => [ 'action' => 'submit' ],
				'rights' => [ 'wikispeech-edit-lexicon-raw' ],
				'should use custom editor' => false
			],
			'raw parameter set' => [
				'title' => 'Pronunciation lexicon:Language/entry',
				'request parameters' => [ 'raw' => 1 ],
				'rights' => [ 'wikispeech-edit-lexicon-raw' ],
				'should use custom editor' => false
			],
			'lacking rights' => [
				'title' => 'Pronunciation lexicon:Language/entry',
				'request parameters' => [ 'raw' => 1 ],
				'rights' => [],
				'should use custom editor' => true
			],
		];
	}

	/**
	 * @dataProvider provideCustomEditor
	 */
	public function testOnCustomEditor_useCustomEditor( $titleString, $requestData, $rights, $expectedUseCustom ) {
		$title = Title::newFromText( $titleString );
		$request = new FauxRequest( $requestData );
		$context = RequestContext::getMain();
		$context->setRequest( $request );
		$article = Article::newFromTitle( $title, $context );
		$user = $this->getTestUser()->getUser();
		$this->overrideUserPermissions( $user, $rights );

		$useCustom = !$this->hooks->onCustomEditor( $article, $user );

		$this->assertEquals( $expectedUseCustom, $useCustom );
	}
}
