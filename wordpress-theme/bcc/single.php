<?php
/**
 * Single post: the live site's single-post design.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

the_post();

$bcc_terms = array();
foreach ( get_the_category() as $bcc_cat ) {
	$bcc_terms[] = '<a href="' . esc_url( get_category_link( $bcc_cat ) ) . '" rel="tag">' . esc_html( $bcc_cat->name ) . '</a>';
}

bcc_render(
	'single',
	array(
		'{{POST_TERMS}}'     => implode( ', ', $bcc_terms ),
		'{{POST_TITLE}}'     => wp_kses_post( get_the_title() ),
		'{{POST_THUMBNAIL}}' => get_the_post_thumbnail( null, 'full' ),
		'{{POST_CONTENT}}'   => apply_filters( 'the_content', get_the_content() ),
	)
);
