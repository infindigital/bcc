<?php
/**
 * BCC theme bootstrap.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

define( 'BCC_THEME_VERSION', '1.0.0' );
define( 'BCC_THEME_DIR', get_template_directory() );
if ( ! defined( 'BCC_ASSETS_ORIGIN' ) ) {
	define( 'BCC_ASSETS_ORIGIN', 'https://bccbengaluru.in/' );
}

require BCC_THEME_DIR . '/inc/render.php';
require BCC_THEME_DIR . '/inc/setup.php';
require BCC_THEME_DIR . '/inc/contact.php';

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ) );
	}
);

// The original pages carry their own stylesheet; load the small theme stylesheet (pagination) too.
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'bcc-theme', get_stylesheet_uri(), array(), BCC_THEME_VERSION );
	}
);

// Match the live site's document titles: "Page – BCC" and "BCC – Sarva Janangada Shantiya Tota".
add_filter(
	'document_title_separator',
	function () {
		return '&#8211;';
	}
);
