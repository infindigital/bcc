<?php
/**
 * Not found.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

$bcc_main  = '<main id="content" class="site-main">';
$bcc_main .= '<div class="page-header"><h1 class="entry-title">' . esc_html__( 'The page can’t be found.', 'bcc' ) . '</h1></div>';
$bcc_main .= '<div class="page-content"><p>' . esc_html__( 'It looks like nothing was found at this location.', 'bcc' ) . '</p>';
$bcc_main .= '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the home page', 'bcc' ) . '</a></p></div>';
$bcc_main .= '</main>';
bcc_render_frame( $bcc_main );
