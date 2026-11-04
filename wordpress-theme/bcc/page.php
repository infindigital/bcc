<?php
/**
 * Pages. About Us, Our Team, History and Contact Us use the live site's designs;
 * any other page uses the site header and footer around its own content.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

$bcc_slug = get_post_field( 'post_name', get_queried_object_id() );

if ( $bcc_slug && bcc_view_exists( 'page-' . $bcc_slug ) ) {
	bcc_render( 'page-' . $bcc_slug );
	return;
}

the_post();
$bcc_main  = '<main id="content" class="site-main ' . esc_attr( implode( ' ', get_post_class() ) ) . '">';
$bcc_main .= '<div class="page-header"><h1 class="entry-title">' . wp_kses_post( get_the_title() ) . '</h1></div>';
$bcc_main .= '<div class="page-content">' . apply_filters( 'the_content', get_the_content() ) . '</div>';
$bcc_main .= '</main>';
bcc_render_frame( $bcc_main );
