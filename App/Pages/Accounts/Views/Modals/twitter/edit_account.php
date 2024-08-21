<?php

namespace FSPoster\App\Pages\Accounts\Views;

use FSPoster\App\Providers\Pages;

defined( 'MODAL' ) or exit;
?>

<div class="fsp-modal-header">
    <div class="fsp-modal-title">
        <div class="fsp-modal-title-icon">
            <i class="fab fa-twitter"></i>
        </div>
        <div class="fsp-modal-title-text">
            <?php echo fsp__( 'Update cookie' ); ?>
        </div>
    </div>
    <div class="fsp-modal-close" data-modal-close="true">
        <i class="fas fa-times"></i>
    </div>
</div>
<div class="fsp-modal-body">
    <p class="fsp-modal-p fsp-is-jb">
        <?php echo fsp__( 'Enter the new cookie value' ); ?>
        <a href="https://www.fs-poster.com/documentation/fs-poster-schedule-share-wordpress-posts-to-twitter-automatically" target="_blank" class="fsp-tooltip" data-title="<?php echo fsp__( 'How to?' ); ?>">
            <i class="far fa-question-circle"></i>
        </a>
    </p>
    <input id="account_to_update" type="hidden" value="<?php echo $fsp_params; ?>">
    <div class="fsp-modal-step">
        <div class="fsp-form-group">
            <label><?php echo fsp__( 'The cookie' ); ?> auth_token</label>
            <div class="fsp-form-input-has-icon">
                <i class="far fa-copy"></i>
                <input id="fspCookie_auth_token" autocomplete="off" class="fsp-form-input" placeholder="<?php echo fsp__( 'Enter the cookie' ); ?> auth_token">
            </div>
        </div>
    </div>
    <div class="fsp-form-checkbox-group">
        <input id="fspUseProxy" type="checkbox" class="fsp-form-checkbox">
        <label for="fspUseProxy">
            <?php echo fsp__( 'Use a proxy' ); ?>
        </label>
        <span class="fsp-tooltip" data-title="<?php echo fsp__( 'Optional field. Supported proxy formats: https://127.0.0.1:8888 or https://user:pass@127.0.0.1:8888' ); ?>"><i class="far fa-question-circle"></i></span>
    </div>
    <div id="fspProxyContainer" class="fsp-form-group fsp-hide fsp-proxy-container">
        <div class="fsp-form-input-has-icon">
            <i class="fas fa-globe"></i>
            <input id="fspProxy" autocomplete="off" class="fsp-form-input fsp-proxy" placeholder="<?php echo fsp__( 'Enter a proxy address' ); ?>">
        </div>
    </div>
</div>
<div class="fsp-modal-footer">
    <button class="fsp-button fsp-is-gray" data-modal-close="true"><?php echo fsp__( 'Cancel' ); ?></button>
    <button id="fspModalUpdateCookiesButton" class="fsp-button"><?php echo fsp__( 'UPDATE' ); ?></button>
</div>

<script>
    jQuery( document ).ready( function () {
        FSPoster.load_script( '<?php echo Pages::asset( 'Accounts', 'js/fsp-accounts-twitter.js' ); ?>' );
    } );
</script>
