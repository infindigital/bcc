<?php
/**
 * View rendering: the views/*.html files are the live site's markup with {{TOKENS}}.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a view exists.
 *
 * @param string $name View name without extension.
 */
function bcc_view_exists( $name ) {
	return is_readable( BCC_THEME_DIR . '/views/' . $name . '.html' );
}

/**
 * Base URL of the bundled original assets (wp-content/…, wp-includes/…).
 */
function bcc_assets_url() {
	static $url = null;
	if ( null === $url ) {
		if ( bcc_assets_bundled() ) {
			$url = trailingslashit( get_template_directory_uri() ) . 'assets/';
		} elseif ( bcc_mirror_complete() ) {
			// Copied into this site's uploads folder (see inc/assets.php).
			$loc = bcc_mirror_location();
			$url = $loc['url'];
		} else {
			// Assets not bundled: load the same files from the original site.
			$url = trailingslashit( BCC_ASSETS_ORIGIN );
		}
		$url = apply_filters( 'bcc_assets_url', $url );
	}
	return $url;
}

/**
 * Whether the original assets are bundled in the theme's assets/ folder.
 */
function bcc_assets_bundled() {
	return is_readable( BCC_THEME_DIR . '/assets/wp-content/plugins/elementor/assets/css/frontend.min.css' );
}

/**
 * Capture the output of a callback.
 *
 * @param callable $callback Callback that echoes.
 */
function bcc_capture( $callback ) {
	ob_start();
	call_user_func( $callback );
	return ob_get_clean();
}

/**
 * Category links in the live site's ElementsKit card format.
 *
 * @param int|WP_Post $post Post.
 */
function bcc_card_categories( $post ) {
	$links = array();
	foreach ( get_the_category( $post ) as $cat ) {
		$links[] = '<a href="' . esc_url( get_category_link( $cat ) ) . '" rel="category tag">' . esc_html( $cat->name ) . '</a>';
	}
	return $links ? '<span>' . implode( ', ', $links ) . '</span>' : '';
}

/**
 * Render one post card from a card view.
 *
 * @param string  $template Card markup.
 * @param WP_Post $post     Post.
 */
function bcc_card( $template, $post ) {
	$image = get_the_post_thumbnail_url( $post, 'full' );
	if ( ! $image ) {
		$image = bcc_assets_url() . 'wp-content/uploads/2026/01/BCC-Logo-2.png';
	}
	$title = get_the_title( $post );
	return strtr(
		$template,
		array(
			'{{URL}}'        => esc_url( get_permalink( $post ) ),
			'{{IMG}}'        => esc_url( $image ),
			'{{TITLE_ATTR}}' => esc_attr( wp_strip_all_tags( $title ) ),
			'{{TITLE}}'      => wp_kses_post( $title ),
			'{{CATS}}'       => bcc_card_categories( $post ),
			'{{EXCERPT}}'    => esc_html( wp_trim_words( get_the_excerpt( $post ), 15, '…' ) ),
		)
	);
}

/**
 * Render a post grid token: {{GRID:image:N}}, {{GRID:related:N}} or {{GRID:main}}.
 *
 * @param string $type  Grid type.
 * @param int    $count Number of posts.
 */
function bcc_grid( $type, $count ) {
	if ( 'related' === $type ) {
		$posts    = get_posts(
			array(
				'numberposts'  => $count ? $count : 2,
				'post__not_in' => array( get_the_ID() ),
			)
		);
		$template = file_get_contents( BCC_THEME_DIR . '/views/card-block.html' );
	} elseif ( 'main' === $type ) {
		global $wp_query;
		$posts    = $wp_query->posts;
		$template = file_get_contents( BCC_THEME_DIR . '/views/card-image.html' );
	} else {
		$posts    = get_posts( array( 'numberposts' => $count ? $count : 6 ) );
		$template = file_get_contents( BCC_THEME_DIR . '/views/card-image.html' );
	}

	$out = '';
	foreach ( $posts as $post ) {
		$out .= bcc_card( $template, $post );
	}

	if ( 'main' === $type ) {
		$links = paginate_links( array( 'type' => 'plain' ) );
		if ( $links ) {
			$out .= '<div class="ekit-col-12 bcc-pagination">' . $links . '</div>';
		}
	}
	return $out;
}

/**
 * Render a view and echo it.
 *
 * @param string $name View name.
 * @param array  $vars Extra {{TOKEN}} => html replacements (already escaped).
 */
function bcc_render( $name, $vars = array() ) {
	$html = file_get_contents( BCC_THEME_DIR . '/views/' . $name . '.html' );

	$html = preg_replace_callback(
		'/\{\{GRID:([a-z]+)(?::(\d+))?\}\}/',
		function ( $m ) {
			return bcc_grid( $m[1], isset( $m[2] ) ? (int) $m[2] : 0 );
		},
		$html
	);

	$html = preg_replace_callback(
		'/\{\{BODY_CLASS:([^}]*)\}\}/',
		function ( $m ) {
			$classes = array_merge( preg_split( '/\s+/', trim( $m[1] ) ), get_body_class() );
			return esc_attr( implode( ' ', array_unique( array_filter( $classes ) ) ) );
		},
		$html,
		1
	);

	$assets = bcc_assets_url();
	$home   = trailingslashit( home_url( '/' ) );

	// Files not bundled in the theme (such as the large brochure PDF) link to the original site.
	if ( bcc_assets_bundled() ) {
		$html = preg_replace_callback(
			'#\{\{ASSETS\}\}(wp-(?:content|includes)/[^\'"\s)?\#]+)#',
			function ( $m ) {
				return is_readable( BCC_THEME_DIR . '/assets/' . $m[1] ) ? $m[0] : trailingslashit( BCC_ASSETS_ORIGIN ) . $m[1];
			},
			$html
		);
	}

	$html   = strtr(
		$html,
		array(
			'{{ASSETS}}'      => $assets,
			'{{ASSETS_JSON}}' => str_replace( '/', '\/', $assets ),
			'{{HOME}}'        => $home,
			'{{HOME_JSON}}'   => str_replace( '/', '\/', $home ),
			'{{NONCE}}'       => wp_create_nonce( 'wp_rest' ),
			'{{FORM_NONCE}}'  => wp_create_nonce( 'bcc_contact' ),
		)
	);

	// The contact form posts to the theme's own endpoint instead of MetForm.
	$html = str_replace(
		array( $home . 'wp-json/metform/v1/entries/insert/119', $home . 'wp-json/metform/v1/forms/views/' ),
		array( rest_url( 'bcc/v1/contact' ), rest_url( 'bcc/v1/form-views/' ) ),
		$html
	);

	// Content tokens last, so post content is never scanned for tokens.
	$vars['{{WP_HEAD}}']      = bcc_capture( 'wp_head' );
	$vars['{{WP_BODY_OPEN}}'] = bcc_capture( 'wp_body_open' );
	$vars['{{WP_FOOTER}}']    = bcc_capture( 'wp_footer' );

	echo strtr( $html, $vars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme views.
}

/**
 * Render the shared frame (site header and footer) around Hello Elementor style main content.
 *
 * @param string $main Markup for <main>.
 */
function bcc_render_frame( $main ) {
	bcc_render( 'frame', array( '{{MAIN}}' => $main ) );
}

/**
 * Hello Elementor style archive list (used by category, tag, date, author and search pages).
 *
 * @param string $title Archive title (HTML).
 */
function bcc_archive_main( $title ) {
	$out  = '<main id="content" class="site-main">';
	$out .= '<div class="page-header"><h1 class="entry-title">' . $title . '</h1></div>';
	$out .= '<div class="page-content">';
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			$url  = esc_url( get_permalink() );
			$out .= '<article class="post">';
			$out .= '<h2 class="entry-title"><a href="' . $url . '">' . wp_kses_post( get_the_title() ) . '</a></h2>';
			if ( has_post_thumbnail() ) {
				$out .= '<a href="' . $url . '">' . get_the_post_thumbnail( null, 'large' ) . '</a>';
			}
			$out .= '<p>' . wp_kses_post( get_the_excerpt() ) . '</p>';
			$out .= '</article>';
		}
	} else {
		$out .= '<p>' . esc_html__( 'It seems we can’t find what you’re looking for.', 'bcc' ) . '</p>';
	}
	$out .= '</div>';
	$out .= bcc_capture(
		function () {
			the_posts_pagination(
				array(
					'prev_text' => '&larr; ' . esc_html__( 'Previous', 'bcc' ),
					'next_text' => esc_html__( 'Next', 'bcc' ) . ' &rarr;',
				)
			);
		}
	);
	$out .= '</main>';
	return $out;
}
