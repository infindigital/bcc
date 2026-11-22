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

// Sidebar: latest posts and categories of this site.
$bcc_item  = file_get_contents( BCC_THEME_DIR . '/views/item-post-list.html' );
$bcc_posts = '';
foreach ( get_posts( array( 'numberposts' => 5 ) ) as $bcc_p ) {
	$bcc_cats   = get_the_category( $bcc_p->ID );
	$bcc_thumb  = get_the_post_thumbnail( $bcc_p, 'thumbnail', array( 'loading' => 'lazy' ) );
	$bcc_posts .= strtr(
		$bcc_item,
		array(
			'{{URL}}'   => esc_url( get_permalink( $bcc_p ) ),
			'{{THUMB}}' => $bcc_thumb ? $bcc_thumb : '<img src="' . esc_url( bcc_assets_url() . 'wp-content/uploads/2026/01/BCC-Logo-2.png' ) . '" alt="" width="150" height="150" style="object-fit:contain;background:#fff" />',
			'{{TITLE}}' => wp_kses_post( get_the_title( $bcc_p ) ),
			'{{DATE}}'  => esc_html( get_the_date( 'j M Y', $bcc_p ) ),
			'{{CAT}}'   => $bcc_cats ? esc_html( $bcc_cats[0]->name ) : '',
		)
	);
}
$bcc_item = file_get_contents( BCC_THEME_DIR . '/views/item-category.html' );
$bcc_catl = '';
foreach ( get_categories( array( 'hide_empty' => true ) ) as $bcc_c ) {
	$bcc_catl .= strtr(
		$bcc_item,
		array(
			'{{URL}}'  => esc_url( get_category_link( $bcc_c ) ),
			'{{NAME}}' => esc_html( $bcc_c->name ),
		)
	);
}

bcc_render(
	'single',
	array(
		'{{POST_TERMS}}'     => implode( ', ', $bcc_terms ),
		'{{POST_TITLE}}'     => wp_kses_post( get_the_title() ),
		'{{POST_THUMBNAIL}}' => get_the_post_thumbnail( null, 'full' ),
		'{{POST_CONTENT}}'   => apply_filters( 'the_content', get_the_content() ),
		'{{SIDEBAR_POSTS}}'  => $bcc_posts,
		'{{SIDEBAR_CATS}}'   => $bcc_catl,
	)
);
