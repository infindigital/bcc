<?php
/**
 * BCC Theme → Analytics: add, change or remove tracking without editing code.
 *
 * Supports Google Analytics 4, Google Tag Manager, Meta (Facebook) Pixel and Microsoft Clarity
 * by ID, plus free-form code for the <head>, after <body> and before </body> for any other service.
 * Leave a field empty to remove that tracker.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

const BCC_ANALYTICS_OPTION = 'bcc_analytics';

/**
 * Saved settings merged with defaults.
 */
function bcc_analytics_settings() {
	return wp_parse_args(
		get_option( BCC_ANALYTICS_OPTION, array() ),
		array(
			'ga4'           => '',
			'gtm'           => '',
			'meta_pixel'    => '',
			'clarity'       => '',
			'head_code'     => '',
			'body_code'     => '',
			'footer_code'   => '',
			'exclude_admin' => 1,
		)
	);
}

/**
 * The tracker fields: key => [label, placeholder, validation pattern, help].
 */
function bcc_analytics_fields() {
	return array(
		'ga4'        => array( __( 'Google Analytics 4 Measurement ID', 'bcc' ), 'G-XXXXXXXXXX', '/^G-[A-Z0-9]{4,20}$/', __( 'Google Analytics → Admin → Data streams → your web stream.', 'bcc' ) ),
		'gtm'        => array( __( 'Google Tag Manager Container ID', 'bcc' ), 'GTM-XXXXXXX', '/^GTM-[A-Z0-9]{4,12}$/', __( 'If you manage GA4 inside Tag Manager, fill in only this field, not both.', 'bcc' ) ),
		'meta_pixel' => array( __( 'Meta (Facebook) Pixel ID', 'bcc' ), '123456789012345', '/^[0-9]{6,20}$/', __( 'Meta Events Manager → Data sources → your pixel.', 'bcc' ) ),
		'clarity'    => array( __( 'Microsoft Clarity Project ID', 'bcc' ), 'abcd1234ef', '/^[a-z0-9]{6,20}$/', __( 'Clarity → Settings → Overview → Project ID.', 'bcc' ) ),
	);
}

/**
 * Whether tracking should be printed for this request.
 */
function bcc_analytics_active() {
	if ( is_admin() || is_preview() || is_customize_preview() ) {
		return false;
	}
	$s = bcc_analytics_settings();
	return ! ( $s['exclude_admin'] && current_user_can( 'manage_options' ) );
}

/* ---------------------------------------------------------------------------
 * Settings page
 * ------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'bcc-theme', __( 'Analytics', 'bcc' ), __( 'Analytics', 'bcc' ), 'manage_options', 'bcc-analytics', 'bcc_analytics_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'bcc_analytics',
			BCC_ANALYTICS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'bcc_analytics_sanitize',
				'default'           => array(),
			)
		);
	}
);

/**
 * Validate the submitted settings.
 *
 * @param array $input Submitted values.
 */
function bcc_analytics_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$old   = bcc_analytics_settings();
	$out   = array( 'exclude_admin' => empty( $input['exclude_admin'] ) ? 0 : 1 );

	foreach ( bcc_analytics_fields() as $key => $field ) {
		$value = isset( $input[ $key ] ) ? trim( sanitize_text_field( $input[ $key ] ) ) : '';
		if ( 'clarity' !== $key ) {
			$value = strtoupper( $value );
		}
		if ( '' !== $value && ! preg_match( $field[2], $value ) ) {
			/* translators: 1: field label, 2: example value. */
			add_settings_error( BCC_ANALYTICS_OPTION, $key, sprintf( __( '%1$s doesn’t look right (expected something like %2$s). The previous value was kept.', 'bcc' ), $field[0], $field[1] ) );
			$value = $old[ $key ];
		}
		$out[ $key ] = $value;
	}

	// Raw code can only be changed by users allowed to post unfiltered HTML (administrators).
	foreach ( array( 'head_code', 'body_code', 'footer_code' ) as $key ) {
		$out[ $key ] = current_user_can( 'unfiltered_html' ) && isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : $old[ $key ];
	}
	return $out;
}

/**
 * Render the settings page.
 */
function bcc_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s         = bcc_analytics_settings();
	$name      = BCC_ANALYTICS_OPTION;
	$can_code  = current_user_can( 'unfiltered_html' );
	$code_rows = array(
		'head_code'   => array( __( 'Code in &lt;head&gt;', 'bcc' ), __( 'For any other service (Search Console verification meta tag, Hotjar, LinkedIn Insight, etc.). Paste the full snippet including &lt;script&gt; tags.', 'bcc' ) ),
		'body_code'   => array( __( 'Code right after &lt;body&gt;', 'bcc' ), __( 'For snippets that must be at the start of the body (such as a &lt;noscript&gt; iframe).', 'bcc' ) ),
		'footer_code' => array( __( 'Code before &lt;/body&gt;', 'bcc' ), __( 'For chat widgets and other scripts that load at the end of the page.', 'bcc' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Analytics', 'bcc' ); ?></h1>
		<p><?php esc_html_e( 'Add or change tracking here. Leave a field empty to remove that tracker. Changes apply to every page of the site as soon as you save.', 'bcc' ); ?></p>
		<?php settings_errors( BCC_ANALYTICS_OPTION ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bcc_analytics' ); ?>
			<h2 class="title"><?php esc_html_e( 'Tracking IDs', 'bcc' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( bcc_analytics_fields() as $key => $field ) : ?>
					<tr>
						<th scope="row"><label for="bcc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="bcc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="<?php echo esc_attr( $field[1] ); ?>" />
							<?php if ( $s[ $key ] ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color:#00a32a" title="<?php esc_attr_e( 'Active', 'bcc' ); ?>"></span>
							<?php endif; ?>
							<p class="description"><?php echo esc_html( $field[3] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Your own visits', 'bcc' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name . '[exclude_admin]' ); ?>" value="1" <?php checked( $s['exclude_admin'] ); ?> />
							<?php esc_html_e( 'Don’t track logged-in administrators', 'bcc' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Other services (custom code)', 'bcc' ); ?></h2>
			<?php if ( ! $can_code ) : ?>
				<p><em><?php esc_html_e( 'Only administrators who can post unfiltered HTML can edit custom code.', 'bcc' ); ?></em></p>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $code_rows as $key => $row ) : ?>
					<tr>
						<th scope="row"><label for="bcc-<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $row[0] ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="5" id="bcc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" <?php disabled( ! $can_code ); ?>><?php echo esc_textarea( $s[ $key ] ); ?></textarea>
							<p class="description"><?php echo wp_kses_post( $row[1] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------------------------
 * Front-end output
 * ------------------------------------------------------------------------- */

add_action(
	'wp_head',
	function () {
		if ( ! bcc_analytics_active() ) {
			return;
		}
		$s = bcc_analytics_settings();

		if ( $s['gtm'] ) {
			$id = esc_js( $s['gtm'] );
			echo "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>\n";
		}

		if ( $s['ga4'] ) {
			$id = esc_attr( $s['ga4'] );
			echo "<!-- Google Analytics 4 -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n";
			echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js( $s['ga4'] ) . "');</script>\n";
		}

		if ( $s['meta_pixel'] ) {
			$id = esc_js( $s['meta_pixel'] );
			echo "<!-- Meta Pixel -->\n<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>\n";
			echo '<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=' . esc_attr( $s['meta_pixel'] ) . "&amp;ev=PageView&amp;noscript=1\" /></noscript>\n";
		}

		if ( $s['clarity'] ) {
			$id = esc_js( $s['clarity'] );
			echo "<!-- Microsoft Clarity -->\n<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{$id}');</script>\n";
		}

		if ( $s['head_code'] ) {
			echo $s['head_code'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-entered code (unfiltered_html).
		}
	},
	1
);

add_action(
	'wp_body_open',
	function () {
		if ( ! bcc_analytics_active() ) {
			return;
		}
		$s = bcc_analytics_settings();
		if ( $s['gtm'] ) {
			echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $s['gtm'] ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
		}
		if ( $s['body_code'] ) {
			echo $s['body_code'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-entered code (unfiltered_html).
		}
	}
);

add_action(
	'wp_footer',
	function () {
		if ( ! bcc_analytics_active() ) {
			return;
		}
		$s = bcc_analytics_settings();
		if ( $s['footer_code'] ) {
			echo $s['footer_code'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-entered code (unfiltered_html).
		}
	},
	100
);
