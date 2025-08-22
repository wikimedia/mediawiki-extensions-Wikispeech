/**
 * Popup dialog for Wikispeech user options.
 *
 * Replaces the normal user options when running on
 * consumer wiki.
 *
 * @class ext.wikispeech.UserOptionsDialog
 */

const util = require( './ext.wikispeech.util.js' );

class UserOptionsDialog extends OO.ui.ProcessDialog {
	constructor( config ) {
		super( config );

		this.languageSelect = null;
		this.voiceSelect = null;
		this.speechRateInput = null;
	}

	initialize() {
		super.initialize();
		const panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		const content = new OO.ui.FieldsetLayout();
		const voiceFieldset = this.addVoiceFieldset();

		// Add input field for speech rate, shown in percent.
		this.speechRateInput = new OO.ui.NumberInputWidget( {
			min: 0,
			step: 25,
			value: mw.user.options.get( 'wikispeechSpeechRate' ) * 100
		} );
		const speechRateFieldset = new OO.ui.FieldsetLayout( {
			label: mw.msg( 'prefs-wikispeech-speech-rate-percent' )
		} );
		const speechRateField = new OO.ui.FieldLayout( this.speechRateInput );
		speechRateFieldset.addItems( [ speechRateField ] );

		// Add a notice about needing to reload the page before
		// preferences kick in.
		const notice = new OO.ui.MessageWidget( {
			type: 'notice',
			label: mw.msg( 'wikispeech-notice-prefs-apply-on-next-page-load' )
		} );
		const noticeFieldset = new OO.ui.FieldsetLayout();
		noticeFieldset.addItems( [ new OO.ui.FieldLayout( notice ) ] );

		content.addItems( [
			voiceFieldset,
			speechRateFieldset,
			noticeFieldset
		] );
		panel.$element.append( content.$element );
		this.$body.append( panel.$element );
	}

	/**
	 * Add fields for selecting voice.
	 *
	 * Adds two fields: language and voice. When a language is
	 * selected, the voice is populated by the available voices for
	 * that language. Language defaults to the language of the current
	 * page. Voices are labeled with language code and autonym.
	 *
	 * @return {OO.ui.FieldsetLayout}
	 */

	addVoiceFieldset() {
		const voices = mw.config.get( 'wgWikispeechVoices' );
		const languageItems = [];
		const languageCodes = Object.keys( voices );
		languageCodes.sort();
		languageCodes.forEach( ( language ) => {
			languageItems.push(
				new OO.ui.MenuOptionWidget( {
					data: language,
					label: language
				} )
			);
		} );
		// Add autonyms to labels. Do this separately to not break if
		// the request fails. If it does, we still have the language
		// codes as labels.
		new mw.Api().get( {
			action: 'query',
			format: 'json',
			formatversion: 2,
			meta: 'languageinfo',
			liprop: 'autonym',
			licode: languageCodes
		} ).done( ( response ) => {
			const info = response.query.languageinfo;
			Object.keys( info ).forEach( ( code ) => {
				languageItems.forEach( ( item ) => {
					if ( item.label === code ) {
						item.setLabel( code + ' - ' + info[ code ].autonym );
					}
				} );
			} );
			// Reselect the language to show the new label.
			this.languageSelect.getMenu().selectItemByData(
				mw.config.get( 'wgPageContentLanguage' )
			);
		} );
		this.languageSelect = new OO.ui.DropdownWidget( {
			menu: {
				items: languageItems
			}
		} );

		this.voiceSelect = new OO.ui.DropdownWidget();
		// Update the voice items when language is selected.
		this.languageSelect.getMenu().on( 'select', ( item ) => {
			const voiceItems = [
				new OO.ui.MenuOptionWidget( {
					data: '',
					label: mw.msg( 'default' )
				} )
			];
			const language = item.data;
			voices[ language ].forEach( ( voice ) => {
				voiceItems.push(
					new OO.ui.MenuOptionWidget( {
						data: voice,
						label: voice
					} )
				);
			} );
			this.voiceSelect.getMenu().clearItems();
			this.voiceSelect.getMenu().addItems( voiceItems );
			const currentVoice = util.getUserVoice( language );
			this.voiceSelect.getMenu().selectItemByData( currentVoice );
		} );
		// Select the language for the current page, since that is
		// probably the one the user is interested in.
		this.languageSelect.getMenu().selectItemByData(
			mw.config.get( 'wgPageContentLanguage' )
		);

		const fieldset = new OO.ui.FieldsetLayout(
			{ label: mw.msg( 'prefs-wikispeech-voice' ) }
		);
		const languageField = new OO.ui.FieldLayout(
			this.languageSelect,
			{ label: mw.msg( 'wikispeech-language' ) }
		);
		const voiceField = new OO.ui.FieldLayout(
			this.voiceSelect,
			{ label: mw.msg( 'prefs-wikispeech-voice' ) }
		);
		fieldset.addItems( [ languageField, voiceField ] );
		return fieldset;
	}

	/**
	 * Handle actions.
	 *
	 * Closes the dialog when "Save" is clicked.
	 *
	 * @param {Object} action
	 * @return {OO.ui.Process}
	 */

	getActionProcess( action ) {
		if ( action ) {
			return new OO.ui.Process( () => {
				this.close( { action: action } );
			} );
		}
		return UserOptionsDialog.super.prototype.getActionProcess.call( this, action );
	}

	/**
	 * Get the selected language and voice.
	 *
	 * @return {Object}
	 * @return {string} return.variable User option variable name.
	 * @return {string} return.voice Name of voice.
	 */

	getVoice() {
		const language = this.languageSelect.getMenu().findSelectedItem().data;
		const voiceVariable = util.getVoiceConfigVariable( language );
		const voice = this.voiceSelect.getMenu().findSelectedItem().data;
		return { variable: voiceVariable, voice: voice };
	}

	/**
	 * Get the selected speech rate.
	 *
	 * @return {number} Speech rate as a decimal number, i.e. 100% =
	 *  1.0.
	 */

	getSpeechRate() {
		return this.speechRateInput.value / 100;
	}
}

OO.inheritClass( UserOptionsDialog, OO.ui.ProcessDialog );
UserOptionsDialog.static.name = 'UserOptionsDialog';
UserOptionsDialog.static.title = mw.msg( 'preferences' );
UserOptionsDialog.static.actions = [
	{
		action: 'save',
		label: mw.msg( 'saveprefs' ),
		flags: [ 'primary', 'progressive' ]
	},
	{
		flags: [ 'safe', 'close' ]
	}
];

module.exports = UserOptionsDialog;
