<?php
/**
 * wikispeech SpecialPage for Wikispeech extension
 *
 * @file
 * @ingroup Extensions
 */
class SpecialWikispeech extends SpecialPage {
	public function __construct() {
		parent::__construct( 'wikispeech' );
	}

	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'special-wikispeech-title' ) );
		$out->addHelpLink( 'How to become a MediaWiki hacker' );
		$out->addWikiMsg( 'special-wikispeech-intro' );
	}

	/**
	 * @see SpecialPage::getGroupName
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'other';
	}
}
