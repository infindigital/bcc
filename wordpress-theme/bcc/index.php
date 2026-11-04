<?php
/**
 * Fallback template.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

if ( is_home() ) {
	bcc_render( 'blog' );
	return;
}
bcc_render_frame( bcc_archive_main( wp_kses_post( get_the_archive_title() ) ) );
