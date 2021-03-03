var Previewer, $content, transcriptionField, languageField, api,
	$previewPlayer, previewer;

Previewer = require( './ext.wikispeech.transcriptionPreviewer.js' );
$content = $( '#mw-content-text' );
transcriptionField = OO.ui.TextInputWidget.static.infuse(
	$content.find( '.ext-wikispeech-transcription' )
);
languageField = OO.ui.TextInputWidget.static.infuse(
	$content.find( '.ext-wikispeech-language' )
);
api = new mw.Api();
$previewPlayer = $content.find( '.ext-wikispeech-preview-player' );
previewer = new Previewer( languageField, transcriptionField, api, $previewPlayer );
transcriptionField.$element.on(
	'change',
	previewer.synthesizePreview.bind( previewer )
);
