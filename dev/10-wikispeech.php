<?php

// Allow setting language per page
$wgPageLanguageUseDB = true;
$wgGroupPermissions['user']['pagelang'] = true;

// Connect a Speechoid instance
$wgWikispeechServerUrl = "https://wikispeech-tts-dev.wmflabs.org/";

// Only allow logged in users to listen, to check permissions are respected
$wgGroupPermissions['*']['wikispeech-listen'] = false;
$wgGroupPermissions['user']['wikispeech-listen'] = true;
