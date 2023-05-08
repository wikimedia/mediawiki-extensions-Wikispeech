<?php

// Allow setting language per page
$wgPageLanguageUseDB = true;
$wgGroupPermissions['user']['pagelang'] = true;

// Connect a Speechoid instance
$wgWikispeechSpeechoidUrl = 'https://speechoid-dev.wmcloud.org/';
$wgWikispeechSpeechoidHaproxyQueueUrl = 'https://speechoid-dev.wmcloud.org/';

// Used to map between symbolsets
$wgWikispeechSymbolSetUrl = 'https://speechoid-dev-symbolset.wmcloud.org/';

// Only allow logged in users to listen, to check permissions are respected
$wgGroupPermissions['*']['wikispeech-listen'] = false;
$wgGroupPermissions['user']['wikispeech-listen'] = true;

// You can enable Wikispeech for additional namespaces by adding their
// numbers to this array. By default it's only enabled in the
// Main namespace.
$wgWikispeechNamespaces = [
	NS_USER
];
