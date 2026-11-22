<?php
/**
 * Asset mirror: copies the original site's stylesheets, scripts, icon fonts and images into
 * wp-content/uploads/bcc-theme-assets/ so everything is served from this site.
 *
 * Icon fonts can't be loaded from another domain (browsers block cross-origin fonts unless that
 * server sends CORS headers), so serving the files locally is what makes the icons appear.
 *
 * The download runs in small batches: automatically in the background after activation (WP-Cron),
 * and from BCC Theme → Assets with a "Download now" button.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

const BCC_MIRROR_OPTION = 'bcc_asset_mirror';
const BCC_MIRROR_DIR    = 'bcc-theme-assets';

/**
 * Local mirror directory and URL.
 */
function bcc_mirror_location() {
	$up = wp_upload_dir( null, false );
	return array(
		'dir' => trailingslashit( $up['basedir'] ) . BCC_MIRROR_DIR . '/',
		'url' => trailingslashit( set_url_scheme( $up['baseurl'] ) ) . BCC_MIRROR_DIR . '/',
	);
}

/**
 * Mirror state.
 */
function bcc_mirror_state() {
	return wp_parse_args(
		get_option( BCC_MIRROR_OPTION, array() ),
		array(
			'version'  => '',
			'queue'    => array(),
			'seen'     => array(),
			'done'     => 0,
			'failed'   => array(),
			'complete' => false,
			'started'  => false,
		)
	);
}

/**
 * Save mirror state (not autoloaded: the queue can be large).
 *
 * @param array $state State.
 */
function bcc_mirror_save( $state ) {
	update_option( BCC_MIRROR_OPTION, $state, false );
}

/**
 * Whether the mirror finished for this theme version.
 */
function bcc_mirror_complete() {
	$s = bcc_mirror_state();
	return $s['complete'] && BCC_THEME_VERSION === $s['version'];
}

/**
 * Only plain asset paths below wp-content/ or wp-includes/ with known extensions are allowed.
 *
 * @param string $path Relative path.
 */
function bcc_mirror_valid_path( $path ) {
	return (bool) preg_match( '#^wp-(content|includes)/[A-Za-z0-9._/@%+-]+\.(css|js|json|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|ico|pdf)$#i', $path )
		&& false === strpos( $path, '..' );
}

/**
 * Start (or restart) the download queue: stylesheets first (they lead to the icon fonts), then scripts, then images.
 */
function bcc_mirror_reset() {
	$list  = file( BCC_THEME_DIR . '/inc/assets-list.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$list  = array_values( array_filter( array_map( 'trim', (array) $list ), 'bcc_mirror_valid_path' ) );
	$rank  = function ( $p ) {
		$ext = strtolower( pathinfo( $p, PATHINFO_EXTENSION ) );
		return 'css' === $ext ? 0 : ( 'js' === $ext ? 1 : 2 );
	};
	usort(
		$list,
		function ( $a, $b ) use ( $rank ) {
			return $rank( $a ) - $rank( $b );
		}
	);
	bcc_mirror_save(
		array(
			'version'  => BCC_THEME_VERSION,
			'queue'    => $list,
			'seen'     => array_fill_keys( $list, 1 ),
			'done'     => 0,
			'failed'   => array(),
			'complete' => false,
			'started'  => true,
		)
	);
}

/**
 * Download the next files from the queue.
 *
 * @param int $max_seconds Time budget.
 */
function bcc_mirror_step( $max_seconds = 20 ) {
	$state = bcc_mirror_state();
	if ( ! $state['started'] || BCC_THEME_VERSION !== $state['version'] ) {
		bcc_mirror_reset();
		$state = bcc_mirror_state();
	}
	$loc   = bcc_mirror_location();
	$start = microtime( true );

	while ( $state['queue'] && microtime( true ) - $start < $max_seconds ) {
		$rel  = array_shift( $state['queue'] );
		$dest = $loc['dir'] . $rel;

		if ( ! file_exists( $dest ) || ! filesize( $dest ) ) {
			$local = BCC_THEME_DIR . '/assets/' . $rel;
			if ( is_readable( $local ) ) {
				$body = file_get_contents( $local );
			} else {
				$res  = wp_remote_get( trailingslashit( BCC_ASSETS_ORIGIN ) . str_replace( '%2F', '/', rawurlencode( $rel ) ), array( 'timeout' => 30 ) );
				$body = ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) ? wp_remote_retrieve_body( $res ) : false;
			}
			if ( false === $body || '' === $body ) {
				$state['failed'][] = $rel;
				continue;
			}
			wp_mkdir_p( dirname( $dest ) );
			file_put_contents( $dest, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		++$state['done'];

		// Follow url(...) in stylesheets: icon fonts, background images.
		if ( 'css' === strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ) ) {
			$css = file_get_contents( $dest );
			if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $m ) ) {
				foreach ( $m[1] as $ref ) {
					if ( 0 === strpos( $ref, 'data:' ) || 0 === strpos( $ref, '#' ) ) {
						continue;
					}
					$ref = preg_replace( '/[?#].*$/', '', $ref );
					if ( preg_match( '#^(https?:)?//#i', $ref ) ) {
						$origin = preg_replace( '#^https?:#i', '', untrailingslashit( BCC_ASSETS_ORIGIN ) );
						$ref    = preg_replace( '#^(https?:)?' . preg_quote( $origin, '#' ) . '#i', '', $ref, 1, $n );
						if ( ! $n ) {
							continue;
						}
						$target = ltrim( $ref, '/' );
					} elseif ( 0 === strpos( $ref, '/' ) ) {
						$target = ltrim( $ref, '/' );
					} else {
						$target = bcc_mirror_resolve( dirname( $rel ), $ref );
					}
					$target = rawurldecode( $target );
					if ( bcc_mirror_valid_path( $target ) && empty( $state['seen'][ $target ] ) ) {
						$state['seen'][ $target ] = 1;
						array_unshift( $state['queue'], $target ); // Fonts right after their stylesheet.
					}
				}
			}
		}
	}

	if ( ! $state['queue'] ) {
		$state['complete'] = true;
	}
	bcc_mirror_save( $state );
	return $state;
}

/**
 * Resolve a relative URL path against a directory.
 *
 * @param string $dir Base directory.
 * @param string $ref Relative reference.
 */
function bcc_mirror_resolve( $dir, $ref ) {
	$parts = explode( '/', $dir . '/' . $ref );
	$out   = array();
	foreach ( $parts as $p ) {
		if ( '' === $p || '.' === $p ) {
			continue;
		}
		if ( '..' === $p ) {
			array_pop( $out );
			continue;
		}
		$out[] = $p;
	}
	return implode( '/', $out );
}

/* ---------------------------------------------------------------------------
 * Background download (WP-Cron)
 * ------------------------------------------------------------------------- */

add_action(
	'bcc_mirror_cron',
	function () {
		$state = bcc_mirror_step( 25 );
		if ( ! $state['complete'] ) {
			wp_schedule_single_event( time() + 5, 'bcc_mirror_cron' );
		}
	}
);

/**
 * Queue the background download (on activation and after theme updates).
 */
function bcc_mirror_schedule() {
	if ( bcc_assets_bundled() || bcc_mirror_complete() ) {
		return;
	}
	if ( ! wp_next_scheduled( 'bcc_mirror_cron' ) ) {
		wp_schedule_single_event( time(), 'bcc_mirror_cron' );
	}
}
add_action( 'after_switch_theme', 'bcc_mirror_schedule' );
add_action(
	'admin_init',
	function () {
		if ( current_user_can( 'switch_themes' ) ) {
			bcc_mirror_schedule();
		}
	}
);

/* ---------------------------------------------------------------------------
 * Appearance → BCC Assets
 * ------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'bcc-theme', __( 'Assets', 'bcc' ), __( 'Assets', 'bcc' ), 'switch_themes', 'bcc-assets', 'bcc_assets_page' );
	}
);

add_action(
	'wp_ajax_bcc_mirror_step',
	function () {
		check_ajax_referer( 'bcc_mirror' );
		if ( ! current_user_can( 'switch_themes' ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! empty( $_POST['restart'] ) ) {
			bcc_mirror_reset();
		}
		$s = bcc_mirror_step( 15 );
		wp_send_json_success(
			array(
				'done'     => $s['done'],
				'left'     => count( $s['queue'] ),
				'failed'   => count( $s['failed'] ),
				'complete' => $s['complete'],
			)
		);
	}
);

/**
 * Settings page with progress and a "Download now" button.
 */
function bcc_assets_page() {
	$s      = bcc_mirror_state();
	$loc    = bcc_mirror_location();
	$source = bcc_assets_bundled() ? __( 'the theme folder (bundled)', 'bcc' ) : ( bcc_mirror_complete() ? $loc['url'] : BCC_ASSETS_ORIGIN );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'BCC Assets', 'bcc' ); ?></h1>
		<p><?php esc_html_e( 'The theme uses the original site’s stylesheets, scripts, icon fonts and images. They are copied once into this site’s uploads folder so they are served from your own domain (icon fonts only work that way).', 'bcc' ); ?></p>
		<p><strong><?php esc_html_e( 'Currently loading assets from:', 'bcc' ); ?></strong> <code><?php echo esc_html( $source ); ?></code></p>
		<p id="bcc-progress">
			<?php
			if ( bcc_assets_bundled() ) {
				esc_html_e( 'Assets are bundled with the theme; nothing to download.', 'bcc' );
			} elseif ( bcc_mirror_complete() ) {
				/* translators: 1: files, 2: failed files. */
				printf( esc_html__( 'Done: %1$d files copied, %2$d not available on the source site.', 'bcc' ), (int) $s['done'], count( $s['failed'] ) );
			} else {
				/* translators: 1: files done, 2: files left. */
				printf( esc_html__( 'In progress: %1$d files copied, %2$d left.', 'bcc' ), (int) $s['done'], count( $s['queue'] ) );
			}
			?>
		</p>
		<?php if ( ! bcc_assets_bundled() ) : ?>
			<p>
				<button type="button" class="button button-primary" id="bcc-run"><?php esc_html_e( 'Download now', 'bcc' ); ?></button>
				<button type="button" class="button" id="bcc-restart"><?php esc_html_e( 'Download everything again', 'bcc' ); ?></button>
			</p>
			<?php if ( $s['failed'] ) : ?>
				<details><summary><?php esc_html_e( 'Files not available on the source site (usually optional)', 'bcc' ); ?></summary><pre><?php echo esc_html( implode( "\n", $s['failed'] ) ); ?></pre></details>
			<?php endif; ?>
			<script>
			(function () {
				var out = document.getElementById('bcc-progress');
				function run(restart) {
					document.getElementById('bcc-run').disabled = document.getElementById('bcc-restart').disabled = true;
					var body = new FormData();
					body.append('action', 'bcc_mirror_step');
					body.append('_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'bcc_mirror' ) ); ?>);
					if (restart) body.append('restart', '1');
					fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
						.then(function (r) { return r.json(); })
						.then(function (r) {
							if (!r.success) { out.textContent = 'Error, please reload and try again.'; return; }
							var d = r.data;
							out.textContent = d.complete
								? 'Done: ' + d.done + ' files copied, ' + d.failed + ' not available. Reload your site to see the icons.'
								: 'Downloading… ' + d.done + ' files copied, ' + d.left + ' left.';
							if (!d.complete) run(false); else location.reload();
						})
						.catch(function () { out.textContent = 'Connection error, click Download now to continue.'; document.getElementById('bcc-run').disabled = false; });
				}
				document.getElementById('bcc-run').addEventListener('click', function () { run(false); });
				document.getElementById('bcc-restart').addEventListener('click', function () { run(true); });
			})();
			</script>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Admin notice until the copy is finished.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'switch_themes' ) || bcc_assets_bundled() || bcc_mirror_complete() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'bcc-assets' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>BCC theme:</strong> ' . esc_html__( 'the site’s icons, styles and images are still being copied to your server. Until it finishes, icons may be missing.', 'bcc' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bcc-assets' ) ) . '">' . esc_html__( 'Finish now', 'bcc' ) . '</a></p></div>';
	}
);
