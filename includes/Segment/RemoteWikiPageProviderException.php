<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use MWException;

/**
 * In case of unable to communicate with the remote wiki.
 * Transformed to an apierror-i18n ApiUsageException in the invoking API implementation.
 *
 * @since 0.1.10
 */
class RemoteWikiPageProviderException extends MWException {

}
