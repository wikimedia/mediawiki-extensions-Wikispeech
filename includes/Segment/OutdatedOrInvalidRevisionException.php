<?php

namespace MediaWiki\Wikispeech\Segment;

/**
 * @file
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 */

use RuntimeException;

/**
 * In case of an invoker requests to segment an outdated or invalid revision.
 * Transformed to an apierror-i18n ApiUsageException in the invoking API implementation.
 *
 * @since 0.1.10
 */
class OutdatedOrInvalidRevisionException extends RuntimeException {

}
