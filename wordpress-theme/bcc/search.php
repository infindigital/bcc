<?php
/**
 * Search results.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

/* translators: %s: search query. */
bcc_render_frame( bcc_archive_main( sprintf( esc_html__( 'Search results for: %s', 'bcc' ), '<span>' . esc_html( get_search_query() ) . '</span>' ) ) );
