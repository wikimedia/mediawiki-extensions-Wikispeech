const Previewer = require( './ext.wikispeech.transcriptionPreviewer.js' );
// eslint-disable-next-line no-jquery/no-global-selector
const $content = $( '#mw-content-text' );
const $language = $content.find( '#ext-wikispeech-language' ).find( 'select, input' );
const $transcription = $content.find( '#ext-wikispeech-transcription input' );
const api = new mw.Api();
const $previewPlayer = $( '<audio>' ).insertAfter( $transcription );
const previewer = new Previewer( $language, $transcription, api, $previewPlayer );

// Toggles raw JSON view
const $rawJson = $content.find( '.toggle-raw' );
$rawJson.on( 'click', function () {
	const targetId = this.getAttribute( 'data-target' );
	const $target = $( '#' + targetId );
	$target.toggle();
} );

const previewButton = OO.ui.infuse( $content.find( '#ext-wikispeech-preview-button' ) );
previewButton.on(
	'click',
	() => {
		previewButton.setDisabled( true );
		previewer.play().then( () => {
			previewButton.setDisabled( false );
		},
		() => {
			previewButton.setDisabled( false );
		} );
	}
);
