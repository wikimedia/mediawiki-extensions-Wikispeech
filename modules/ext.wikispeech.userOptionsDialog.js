( function () {

	/**
	 * Popup dialog for Wikispeech user options.
	 *
	 * Replaces the normal user options when running on
	 * consumer wiki.
	 *
	 * @class ext.wikispeech.UserOptionsDialog
	 */

	let self;
	const util = require( './ext.wikispeech.util.js' );

	function UserOptionsDialog( config ) {
		UserOptionsDialog.super.call( this, config );
		self = this;
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

	UserOptionsDialog.prototype.initialize = function () {
		UserOptionsDialog.super.prototype.initialize.apply( self );
		const panel = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		const content = new OO.ui.FieldsetLayout();
		const voiceFieldset = self.addVoiceFieldset();

		// Add input field for speech rate, shown in percent.
		self.speechRateInput = new OO.ui.NumberInputWidget( {
			min: 0,
			step: 25,
			value: mw.user.options.get( 'wikispeechSpeechRate' ) * 100
		} );
		const speechRateFieldset = new OO.ui.FieldsetLayout( {
			label: mw.msg( 'prefs-wikispeech-speech-rate-percent' )
		} );
		const speechRateField = new OO.ui.FieldLayout( self.speechRateInput );
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
		self.$body.append( panel.$element );
	};

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

	UserOptionsDialog.prototype.addVoiceFieldset = function () {
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
			self.languageSelect.getMenu().selectItemByData(
				mw.config.get( 'wgPageContentLanguage' )
			);
		} );
		self.languageSelect = new OO.ui.DropdownWidget( {
			menu: {
				items: languageItems
			}
		} );

		self.voiceSelect = new OO.ui.DropdownWidget();
		// Update the voice items when language is selected.
		self.languageSelect.getMenu().on( 'select', ( item ) => {
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
			self.voiceSelect.getMenu().clearItems();
			self.voiceSelect.getMenu().addItems( voiceItems );
			const currentVoice = util.getUserVoice( language );
			self.voiceSelect.getMenu().selectItemByData( currentVoice );
		} );
		// Select the language for the current page, since that is
		// probably the one the user is interested in.
		self.languageSelect.getMenu().selectItemByData(
			mw.config.get( 'wgPageContentLanguage' )
		);

		const fieldset = new OO.ui.FieldsetLayout(
			{ label: mw.msg( 'prefs-wikispeech-voice' ) }
		);
		const languageField = new OO.ui.FieldLayout(
			self.languageSelect,
			{ label: mw.msg( 'wikispeech-language' ) }
		);
		const voiceField = new OO.ui.FieldLayout(
			self.voiceSelect,
			{ label: mw.msg( 'prefs-wikispeech-voice' ) }
		);
		fieldset.addItems( [ languageField, voiceField ] );
		return fieldset;
	};

	/**
	 * Handle actions.
	 *
	 * Closes the dialog when "Save" is clicked.
	 *
	 * @param {Object} action
	 * @return {OO.ui.Process}
	 */

	UserOptionsDialog.prototype.getActionProcess = function ( action ) {
		if ( action ) {
			return new OO.ui.Process( () => {
				self.close( { action: action } );
			} );
		}
		return UserOptionsDialog.super.prototype.getActionProcess.call( self, action );
	};

	/**
	 * Get the selected language and voice.
	 *
	 * @return {Object}
	 * @return {string} return.variable User option variable name.
	 * @return {string} return.voice Name of voice.
	 */

	UserOptionsDialog.prototype.getVoice = function () {
		const language = self.languageSelect.getMenu().findSelectedItem().data;
		const voiceVariable = util.getVoiceConfigVariable( language );
		const voice = self.voiceSelect.getMenu().findSelectedItem().data;
		return { variable: voiceVariable, voice: voice };
	};

	/**
	 * Get the selected speech rate.
	 *
	 * @return {number} Speech rate as a decimal number, i.e. 100% =
	 *  1.0.
	 */

	UserOptionsDialog.prototype.getSpeechRate = function () {
		return self.speechRateInput.value / 100;
	};

	module.exports = UserOptionsDialog;
}() );
