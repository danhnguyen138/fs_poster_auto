'use strict';

( function ( $ ) {
	let doc = $( document );

	doc.ready( function () {
		$( '.fsp-modal-footer > #fspModalAddButton' ).on( 'click', function () {
			let _this = $( this );
			let selectedMethod = String( $( '.fsp-modal-option.fsp-is-selected' ).data( 'step' ) );

			if ( selectedMethod === '1' )
			{
				let cookie_csrf_token = $( '#fspModalStep_1 #fspCookie_csrf_token' ).val().trim();
				let cookie_ds_user_id = $( '#fspModalStep_1 #fspCookie_ds_user_id' ).val().trim();
				let cookie_sessionid  = $( '#fspModalStep_1 #fspCookie_sessionid' ).val().trim();
				let proxy = $( '#fspProxy' ).val().trim();

				FSPoster.ajax( 'add_instagram_account_cookie_method', { cookie_csrf_token, cookie_ds_user_id, cookie_sessionid, proxy }, function () {
					accountAdded();
				} );
			}
			else if ( selectedMethod === '2' ) // cookie method
			{
				let username = $( '#fspModalStep_2 #fspUsername' ).val().trim();
				let password = $( '#fspModalStep_2 #fspPassword' ).val().trim();
				let proxy = $( '#fspProxy' ).val().trim();

				FSPoster.ajax( 'add_instagram_account', { username, password, proxy }, function ( response ) {
					if( typeof response.id !== 'undefined' ){
						accountAdded();
					}
					else{
						requireAction( response.options, proxy );
					}
				} );
			}
			else if ( selectedMethod === '3' ) // app method
			{
				let proxy = $( '#fspProxy' ).val().trim();
				let appID = $( '#fspModalStep_3 #fspModalAppSelector' ).val().trim();
				let openURL = `${ fspConfig.siteURL }/?instagram_app_redirect=${ appID }&proxy=${ proxy }`;

				window.open( openURL, 'fs-app', 'width=750, height=550' );
			}
		} );

		$('#fspModalUpdateCookiesButton').on('click', function () {
			let data = {
				account_id: ( $( '#account_to_update' ).val() || '' ).trim(),
				cookie_sessionid: ( $( '#fspCookie_sessionid' ).val() || '' ).trim(),
				cookie_csrf_token: ( $( '#fspCookie_csrf_token' ).val() || '' ).trim(),
				proxy: ( $( '#fspProxy' ).val() || '' ).trim()
			}

			FSPoster.ajax( 'update_instagram_account_cookie', data, function () {
				accountUpdated();
			} );
		});
	} );
} )( jQuery );

function requireAction ( options, proxy )
{
	if ( typeof jQuery !== 'undefined' )
	{
		$ = jQuery;
	}

	let title = options[ 'obfuscated_phone_number' ] === '3' ? '' : `<p class="fsp-modal-p">${ fsp__( 'Two factor authentication required! Activation code was sent to ' + FSPoster.htmlspecialchars( options[ 'obfuscated_phone_number' ] ) ) }.</p>`;
	$( '.fsp-modal-body' ).html( `
        ${title}
		<div class="fsp-modal-step">
			<div class="fsp-form-group">
				<label>${ fsp__( 'Activation code' ) }</label>
				<div class="fsp-form-input-has-icon">
					<i class="far fa-copy"></i>
					<input id="fspActivationCode" class="fsp-form-input" autocomplete="off" placeholder="${ fsp__( 'Enter the activation code' ) }">
				</div>
			</div>
		</div>` );

	$( '#fspModalAddButton' ).off( 'click' ).on( 'click', function () {
		let code = $( '#fspActivationCode' ).val().trim();

		FSPoster.ajax( 'do_instagram_challenge', {
			options,
			proxy,
			code
		}, function () {
			accountAdded();
		} );
	} );
}