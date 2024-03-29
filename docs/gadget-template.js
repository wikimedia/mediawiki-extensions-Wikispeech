// Copy the code below to a gadget or to common.js / global.js. Set
// `producerUrl` to the script path of the wiki that runs
// Wikispeech (producer). You can get the correct URL by running the following
// Javascript snippet in the developer console on the producer wiki:
//
// window.location.origin + mw.config.get( 'wgScriptPath' );

var producerUrl, parametersString, moduleUrl;

// Set this to the script path on the producer wiki. Usually ends with
// "/w".
producerUrl = 'https://.../w';

mw.wikispeech = mw.wikispeech || {};
mw.wikispeech.producerUrl = producerUrl;
parametersString = $.param( {
	lang: mw.config.get( 'wgUserLanguage' ),
	skin: mw.config.get( 'skin' ),
	raw: 1,
	safemode: 1,
	modules: 'ext.wikispeech.gadget'
} );
moduleUrl = producerUrl + '/load.php?' + parametersString;
mw.loader.load( moduleUrl );
