<?php
/**
 * Category, tag, date and author archives.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

bcc_render_frame( bcc_archive_main( wp_kses_post( get_the_archive_title() ) ) );
