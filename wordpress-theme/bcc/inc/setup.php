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
	if ( ! $relative ) {
		return 0;
	}
	$file = BCC_THEME_DIR . '/assets/' . $relative;
	if ( is_readable( $file ) ) {
		$bits = file_get_contents( $file );
	} else {
		// Not bundled: fetch the image from the original site.
		$response = wp_remote_get( trailingslashit( BCC_ASSETS_ORIGIN ) . $relative, array( 'timeout' => 30 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return 0;
		}
		$bits = wp_remote_retrieve_body( $response );
	}
	$upload = wp_upload_bits( basename( $file ), null, $bits );
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
 * Whether the site already has posts of its own (ignoring WordPress's "Hello world!" sample).
 */
function bcc_site_has_own_posts() {
	$ids = get_posts(
		array(
			'post_type'   => 'post',
			'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'numberposts' => 5,
			'fields'      => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		if ( 'hello-world' !== get_post_field( 'post_name', $id ) ) {
			return true;
		}
	}
	return false;
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

	// Sample posts only on a fresh site: a site with its own posts keeps showing just those.
	if ( apply_filters( 'bcc_import_demo_posts', ! bcc_site_has_own_posts() ) ) {
		bcc_import_demo_posts();
	}
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'bcc_activate' );

/* ---------------------------------------------------------------------------
 * BCC Theme → Sample Posts
 * ------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'bcc-theme', __( 'Sample Posts', 'bcc' ), __( 'Sample Posts', 'bcc' ), 'manage_options', 'bcc-content', 'bcc_content_page' );
	}
);

add_action(
	'admin_post_bcc_import_samples',
	function () {
		check_admin_referer( 'bcc_import_samples' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bcc' ) );
		}
		bcc_import_demo_posts();
		wp_safe_redirect( admin_url( 'admin.php?page=bcc-content&imported=1' ) );
		exit;
	}
);

/**
 * Sample posts page.
 */
function bcc_content_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$data    = json_decode( file_get_contents( BCC_THEME_DIR . '/inc/demo-posts.json' ), true );
	$missing = array();
	foreach ( (array) $data as $item ) {
		if ( ! get_page_by_path( $item['slug'], OBJECT, 'post' ) ) {
			$missing[] = $item['title'];
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Sample Posts', 'bcc' ); ?></h1>
		<?php if ( isset( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Sample posts imported.', 'bcc' ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'The theme always shows this site’s own posts: the home page “Insights & Article” grid, the Blog page, single posts, “You may also like”, the Latest News and Categories sidebar, and the category pages. Add or edit them in Posts as usual.', 'bcc' ); ?></p>
		<p><?php esc_html_e( 'When the theme is activated on a site without posts, it adds the eight original BCC posts so the pages are not empty. Sites that already have posts are left unchanged.', 'bcc' ); ?></p>
		<?php if ( $missing ) : ?>
			<p><?php echo esc_html( sprintf( /* translators: %d: number of posts. */ _n( '%d sample post is not on this site:', '%d sample posts are not on this site:', count( $missing ), 'bcc' ), count( $missing ) ) ); ?></p>
			<ul style="list-style:disc;margin-left:2em"><?php foreach ( $missing as $title ) { echo '<li>' . esc_html( $title ) . '</li>'; } ?></ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bcc_import_samples" />
				<?php wp_nonce_field( 'bcc_import_samples' ); ?>
				<?php submit_button( __( 'Import these sample posts', 'bcc' ), 'secondary' ); ?>
			</form>
		<?php else : ?>
			<p><strong><?php esc_html_e( 'All sample posts are on this site. You can edit or delete them in Posts like any other post.', 'bcc' ); ?></strong></p>
		<?php endif; ?>
	</div>
	<?php
}
