<?php
/**
 * Theme activation: pages, reading settings, categories and the original posts.
 *
 * Nothing existing is overwritten: pages, categories and posts are only created when missing,
 * and reading settings are only set when no static front page / posts page is configured.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get or create a page by slug.
 *
 * @param string $slug  Slug.
 * @param string $title Title.
 */
function bcc_ensure_page( $slug, $title ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		return (int) $page->ID;
	}
	return (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => $title,
		)
	);
}

/**
 * Copy a bundled image into the media library.
 *
 * @param string $relative Path below assets/, e.g. wp-content/uploads/2026/01/x.jpg.
 * @param int    $parent   Post the attachment belongs to.
 */
function bcc_import_image( $relative, $parent ) {
	$file = BCC_THEME_DIR . '/assets/' . $relative;
	if ( ! $relative || ! is_readable( $file ) ) {
		return 0;
	}
	$upload = wp_upload_bits( basename( $file ), null, file_get_contents( $file ) );
	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}
	$type = wp_check_filetype( $upload['file'] );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'],
			'post_title'     => sanitize_title( pathinfo( $file, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$parent
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	return (int) $id;
}

/**
 * Create the original posts that are missing.
 */
function bcc_import_demo_posts() {
	$data = json_decode( file_get_contents( BCC_THEME_DIR . '/inc/demo-posts.json' ), true );
	if ( ! is_array( $data ) ) {
		return;
	}
	$assets = bcc_assets_url();
	$home   = trailingslashit( home_url( '/' ) );
	$date   = strtotime( '2026-01-10 10:00:00' );

	foreach ( $data as $i => $item ) {
		$post_date = gmdate( 'Y-m-d H:i:s', $date + $i * DAY_IN_SECONDS );
		if ( get_page_by_path( $item['slug'], OBJECT, 'post' ) ) {
			continue;
		}
		$cat = get_category_by_slug( $item['category']['slug'] );
		if ( ! $cat ) {
			$created = wp_insert_term( $item['category']['name'], 'category', array( 'slug' => $item['category']['slug'] ) );
			$cat_id  = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
		} else {
			$cat_id = (int) $cat->term_id;
		}
		$content = strtr( $item['content'], array( '{{ASSETS}}' => $assets, '{{HOME}}' => $home ) );
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_name'     => $item['slug'],
					'post_title'    => $item['title'],
					'post_content'  => $content,
					'post_date'     => $post_date,
					'post_category' => $cat_id ? array( $cat_id ) : array(),
				)
			)
		);
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			$thumb = bcc_import_image( $item['thumbnail'], $post_id );
			if ( $thumb ) {
				set_post_thumbnail( $post_id, $thumb );
			}
		}
	}
}

/**
 * Activation routine.
 */
function bcc_activate() {
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	$front = bcc_ensure_page( 'home', 'Home' );
	foreach ( array(
		'about-us'   => 'About Us',
		'our-team'   => 'Our Team',
		'history'    => 'History',
		'contact-us' => 'Contact Us',
	) as $slug => $title ) {
		bcc_ensure_page( $slug, $title );
	}
	$blog = bcc_ensure_page( 'blog', 'Blog' );

	if ( 'page' !== get_option( 'show_on_front' ) || ! get_option( 'page_on_front' ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );
	}
	if ( ! get_option( 'page_for_posts' ) ) {
		update_option( 'page_for_posts', $blog );
	}
	if ( 'BCC' !== get_option( 'blogname' ) && in_array( get_option( 'blogname' ), array( '', 'My WordPress Website', 'My Blog' ), true ) ) {
		update_option( 'blogname', 'BCC' );
		update_option( 'blogdescription', 'Sarva Janangada Shantiya Tota' );
	}

	if ( apply_filters( 'bcc_import_demo_posts', true ) ) {
		bcc_import_demo_posts();
	}
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'bcc_activate' );

/**
 * Warn when the bundled assets have not been downloaded into the theme.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'switch_themes' ) || is_readable( BCC_THEME_DIR . '/assets/wp-content/plugins/elementor/assets/css/frontend.min.css' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>BCC theme:</strong> the theme&rsquo;s assets folder is empty, so pages will show without styles or images. Install the packaged <code>bcc-theme.zip</code>, or run <code>python3 scripts/fetch_assets.py --dest wordpress-theme/bcc/assets</code> before zipping the theme.</p></div>';
	}
);
