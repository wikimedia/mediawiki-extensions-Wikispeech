function HelpDialog( config ) {
	config = config || {};
	OO.ui.MessageDialog.call( this, config );
}

OO.inheritClass( HelpDialog, OO.ui.MessageDialog );

HelpDialog.static.name = 'helpDialog';
HelpDialog.static.actions = [ { action: 'close', label: mw.msg( 'wikispeech-close-selection-player' ), flags: 'safe' } ];

HelpDialog.prototype.initialize = function () {
	HelpDialog.super.prototype.initialize.apply( this, arguments );

	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	const paragraphs = mw.msg( 'wikispeech-help-message' ).split( /\n\s*\n/ );
	paragraphs.forEach( ( paragraph ) => {
		this.content.$element.append(
			$( '<p>' ).text( paragraph )
		);
	} );

	const $image = $( '<img>' )
		.attr( 'src', 'https://upload.wikimedia.org/wikipedia/commons/1/12/Wikispeech-player-documentation.png' )
		.attr( 'alt', 'Screenshot of the Wikispeech player' )
		.css( {
			display: 'block',
			maxWidth: '100%',
			height: 'auto',
			margin: '1em auto'
		} );

	this.content.$element.append( $image );
	this.$body.append( this.content.$element );

	const helpPage = mw.config.get( 'wgWikispeechHelpPage' );

	const helpUrl = /^https?:\/\//i.test( helpPage ) ?
		helpPage :
		mw.util.getUrl( helpPage );

	this.link = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	const $moreInfo = $( '<p>' )
		.text( 'More info can be found at the ' )
		.append(
			$( '<a>' )
				.attr( 'href', helpUrl )
				.attr( 'target', '_blank' )
				.text( 'Wikispeech help page' )
		);

	this.link.$element.append( $moreInfo );
	this.$body.append( this.link.$element );
};

HelpDialog.static.size = 'large';

HelpDialog.prototype.getBodyHeight = function () {
	return 570;
};

HelpDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'close' ) {
		return new OO.ui.Process( () => {
			this.close( {
				action: 'close'
			} );
		} );
	}

	return HelpDialog.super.prototype.getActionProcess.call( this, action );
};

const help = {};

help.openHelpDialog = function () {

	const openDialog = () => {
		const dialog = new HelpDialog();
		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	};
	openDialog();
};

module.exports = help;
