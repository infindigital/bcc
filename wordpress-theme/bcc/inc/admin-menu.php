<?php
/**
 * wp-admin → "BCC Theme" menu: one place for the theme's settings.
 * Sub-pages are added by inc/contact.php, inc/analytics.php, inc/setup.php and inc/assets.php.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			__( 'BCC Theme', 'bcc' ),
			__( 'BCC Theme', 'bcc' ),
			'manage_options',
			'bcc-theme',
			'bcc_theme_overview_page',
			'dashicons-admin-customizer',
			61
		);
		add_submenu_page( 'bcc-theme', __( 'Overview', 'bcc' ), __( 'Overview', 'bcc' ), 'manage_options', 'bcc-theme', 'bcc_theme_overview_page' );
	},
	9
);

/**
 * Overview page with links to every setting.
 */
function bcc_theme_overview_page() {
	$contact   = bcc_contact_settings();
	$analytics = bcc_analytics_settings();
	$trackers  = array_filter( array( $analytics['ga4'], $analytics['gtm'], $analytics['meta_pixel'], $analytics['clarity'] ) );
	$items     = array(
		array( 'bcc-contact', __( 'Contact Form', 'bcc' ), sprintf( /* translators: %s: email addresses. */ __( 'Messages are sent to: %s', 'bcc' ), implode( ', ', bcc_contact_recipients() ) ) . ( $contact['smtp_enabled'] ? ' ' . __( '(via SMTP)', 'bcc' ) : '' ) ),
		array( 'bcc-analytics', __( 'Analytics', 'bcc' ), $trackers ? sprintf( /* translators: %s: tracker ids. */ __( 'Active: %s', 'bcc' ), implode( ', ', $trackers ) ) : __( 'No tracking added yet. Add your Google Analytics ID here.', 'bcc' ) ),
		array( 'bcc-content', __( 'Sample Posts', 'bcc' ), __( 'Your own posts are shown everywhere automatically. Optionally import the eight original BCC posts.', 'bcc' ) ),
		array( 'bcc-assets', __( 'Assets', 'bcc' ), bcc_assets_bundled() ? __( 'All styles, fonts and images are bundled with the theme.', 'bcc' ) : __( 'Check where styles and images are loaded from.', 'bcc' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'BCC Theme', 'bcc' ); ?></h1>
		<p><?php esc_html_e( 'Blog grids, single posts, archives and the sidebar show this site’s own posts. Use the pages below to set up the contact form, analytics and content.', 'bcc' ); ?></p>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td style="width:180px"><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item[0] ) ); ?>"><?php echo esc_html( $item[1] ); ?></a></strong></td>
					<td><?php echo esc_html( $item[2] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
