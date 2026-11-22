<?php
/**
 * Contact form: the original MetForm form posts here. Messages are stored as
 * "Contact Messages" in the admin and emailed to the site admin.
 *
 * @package BCC
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'bcc_message',
			array(
				'labels'          => array(
					'name'          => __( 'Contact Messages', 'bcc' ),
					'singular_name' => __( 'Contact Message', 'bcc' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bcc/v1',
			'/contact',
			array(
				'methods'             => 'POST',
				'callback'            => 'bcc_handle_contact',
				'permission_callback' => '__return_true',
			)
		);
		// MetForm's front end pings a "form views" counter; answer it so nothing errors.
		register_rest_route(
			'bcc/v1',
			'/form-views(?:/(?P<id>\d+))?',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => function () {
					return array( 'status' => 1 );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Store and email a contact form submission. Responds in MetForm's format.
 *
 * @param WP_REST_Request $request Request.
 */
function bcc_handle_contact( WP_REST_Request $request ) {
	$labels = array(
		'mf-your-name'     => 'Name',
		'mf-email-address' => 'Email',
		'mf-phone'         => 'Phone',
		'mf-subject'       => 'Subject',
		'mf-message'       => 'Message',
	);
	$fields = array();
	foreach ( $request->get_params() as $key => $value ) {
		if ( 0 !== strpos( (string) $key, 'mf-' ) || is_array( $value ) ) {
			continue;
		}
		$fields[ $key ] = 'mf-message' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	$fail = function ( $message ) {
		return array(
			'status'        => 0,
			'store_entries' => 1,
			'error'         => array( $message ),
			'data'          => array( 'message' => '' ),
		);
	};

	if ( empty( array_filter( $fields ) ) ) {
		return $fail( __( 'Please fill in the form.', 'bcc' ) );
	}
	$email = isset( $fields['mf-email-address'] ) ? $fields['mf-email-address'] : '';
	if ( $email && ! is_email( $email ) ) {
		return $fail( __( 'Please enter a valid Email address', 'bcc' ) );
	}

	// Simple flood protection: one message per IP every 30 seconds.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'bcc_contact_' . md5( $ip );
	if ( get_transient( $key ) ) {
		return $fail( __( 'Please wait a moment before sending another message.', 'bcc' ) );
	}
	set_transient( $key, 1, 30 );

	$lines = array();
	foreach ( $fields as $key => $value ) {
		$label   = isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( array( 'mf-', '-' ), array( '', ' ' ), $key ) );
		$lines[] = $label . ': ' . $value;
	}
	$body    = implode( "\n", $lines );
	$subject = ! empty( $fields['mf-subject'] ) ? $fields['mf-subject'] : __( 'New contact message', 'bcc' );
	$name    = ! empty( $fields['mf-your-name'] ) ? $fields['mf-your-name'] : $email;

	wp_insert_post(
		array(
			'post_type'    => 'bcc_message',
			'post_status'  => 'private',
			'post_title'   => wp_strip_all_tags( $name . ' — ' . $subject ),
			'post_content' => esc_html( $body ),
		)
	);

	$headers = array();
	if ( $email ) {
		$headers[] = 'Reply-To: ' . ( $name ? $name . ' ' : '' ) . '<' . $email . '>';
	}
	$settings = bcc_contact_settings();
	$prefix   = '' !== $settings['subject_prefix'] ? $settings['subject_prefix'] : '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ']';
	$sent     = wp_mail(
		apply_filters( 'bcc_contact_recipient', bcc_contact_recipients() ),
		trim( $prefix . ' ' . $subject ),
		$body . "\n\n--\n" . sprintf( /* translators: %s: site URL. */ __( 'Sent from the contact form on %s', 'bcc' ), home_url( '/' ) ),
		$headers
	);
	if ( ! $sent ) {
		update_option( 'bcc_contact_last_error', current_time( 'mysql' ), false );
	}

	return array(
		'status'        => 1,
		'store_entries' => 1,
		'error'         => array(),
		'data'          => array(
			'message'     => bcc_contact_settings()['success_message'],
			'hide_form'   => '',
			'redirect_to' => '',
			'form_data'   => $fields,
		),
	);
}

/* ---------------------------------------------------------------------------
 * Settings: BCC Theme → Contact Form
 * ------------------------------------------------------------------------- */

const BCC_CONTACT_OPTION = 'bcc_contact';

/**
 * Contact form settings merged with defaults.
 */
function bcc_contact_settings() {
	return wp_parse_args(
		get_option( BCC_CONTACT_OPTION, array() ),
		array(
			'recipients'      => '',
			'subject_prefix'  => '',
			'success_message' => __( 'Thank you! Your message has been sent. We will get back to you soon.', 'bcc' ),
			'from_email'      => '',
			'from_name'       => '',
			'smtp_enabled'    => 0,
			'smtp_host'       => '',
			'smtp_port'       => 587,
			'smtp_secure'     => 'tls',
			'smtp_user'       => '',
			'smtp_pass'       => '',
		)
	);
}

/**
 * Email addresses that receive contact form messages (falls back to the site admin email).
 */
function bcc_contact_recipients() {
	$list = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', (string) bcc_contact_settings()['recipients'] ) ), 'is_email' );
	return $list ? array_values( $list ) : array( get_option( 'admin_email' ) );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'bcc-theme', __( 'Contact Form', 'bcc' ), __( 'Contact Form', 'bcc' ), 'manage_options', 'bcc-contact', 'bcc_contact_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'bcc_contact',
			BCC_CONTACT_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'bcc_contact_sanitize',
				'default'           => array(),
			)
		);
	}
);

/**
 * Validate contact settings.
 *
 * @param array $in Submitted values.
 */
function bcc_contact_sanitize( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$old = bcc_contact_settings();
	$out = array();

	$emails = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', isset( $in['recipients'] ) ? (string) $in['recipients'] : '' ) ) );
	$bad    = array_filter( $emails, function ( $e ) { return ! is_email( $e ); } );
	if ( $bad ) {
		/* translators: %s: invalid addresses. */
		add_settings_error( BCC_CONTACT_OPTION, 'recipients', sprintf( __( 'These addresses are not valid and were removed: %s', 'bcc' ), implode( ', ', $bad ) ) );
	}
	$out['recipients']      = implode( ', ', array_map( 'sanitize_email', array_diff( $emails, $bad ) ) );
	$out['subject_prefix']  = isset( $in['subject_prefix'] ) ? sanitize_text_field( $in['subject_prefix'] ) : '';
	$out['success_message'] = isset( $in['success_message'] ) && '' !== trim( $in['success_message'] ) ? sanitize_text_field( $in['success_message'] ) : $old['success_message'];
	$out['from_email']      = isset( $in['from_email'] ) && is_email( trim( $in['from_email'] ) ) ? sanitize_email( $in['from_email'] ) : '';
	$out['from_name']       = isset( $in['from_name'] ) ? sanitize_text_field( $in['from_name'] ) : '';
	$out['smtp_enabled']    = empty( $in['smtp_enabled'] ) ? 0 : 1;
	$out['smtp_host']       = isset( $in['smtp_host'] ) ? sanitize_text_field( $in['smtp_host'] ) : '';
	$out['smtp_port']       = isset( $in['smtp_port'] ) ? max( 1, min( 65535, absint( $in['smtp_port'] ) ) ) : 587;
	$out['smtp_secure']     = isset( $in['smtp_secure'] ) && in_array( $in['smtp_secure'], array( 'tls', 'ssl', 'none' ), true ) ? $in['smtp_secure'] : 'tls';
	$out['smtp_user']       = isset( $in['smtp_user'] ) ? sanitize_text_field( $in['smtp_user'] ) : '';
	// The password field is never shown back; an empty field keeps the saved password.
	$out['smtp_pass']       = isset( $in['smtp_pass'] ) && '' !== $in['smtp_pass'] ? (string) $in['smtp_pass'] : $old['smtp_pass'];
	if ( ! empty( $in['smtp_pass_clear'] ) ) {
		$out['smtp_pass'] = '';
	}
	if ( $out['smtp_enabled'] && ! $out['smtp_host'] ) {
		add_settings_error( BCC_CONTACT_OPTION, 'smtp_host', __( 'SMTP is enabled but no SMTP server is set, so it stays off until you add one.', 'bcc' ) );
		$out['smtp_enabled'] = 0;
	}
	return $out;
}

// Sender name/address and SMTP delivery for every email the site sends.
add_filter(
	'wp_mail_from',
	function ( $from ) {
		$s = bcc_contact_settings();
		return $s['from_email'] ? $s['from_email'] : ( $s['smtp_enabled'] && is_email( $s['smtp_user'] ) ? $s['smtp_user'] : $from );
	}
);
add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		$s = bcc_contact_settings();
		return $s['from_name'] ? $s['from_name'] : $name;
	}
);
add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$s = bcc_contact_settings();
		if ( ! $s['smtp_enabled'] || ! $s['smtp_host'] ) {
			return;
		}
		$mailer->isSMTP();
		$mailer->Host     = $s['smtp_host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->Port     = (int) $s['smtp_port']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->SMTPAuth = '' !== $s['smtp_user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		if ( $mailer->SMTPAuth ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$mailer->Username = $s['smtp_user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$mailer->Password = $s['smtp_pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		}
		$mailer->SMTPSecure  = 'none' === $s['smtp_secure'] ? '' : $s['smtp_secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->SMTPAutoTLS = 'none' !== $s['smtp_secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	}
);

// "Send test email" button.
add_action(
	'admin_post_bcc_contact_test',
	function () {
		check_admin_referer( 'bcc_contact_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bcc' ) );
		}
		$error = '';
		$catch = function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );
		$ok = wp_mail( bcc_contact_recipients(), __( 'Test email from your website', 'bcc' ), __( 'If you can read this, contact form messages from your website will reach this inbox.', 'bcc' ) );
		remove_action( 'wp_mail_failed', $catch );
		set_transient( 'bcc_contact_test_result', $ok ? 'ok' : ( $error ? $error : 'failed' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=bcc-contact' ) );
		exit;
	}
);

/**
 * Contact settings page.
 */
function bcc_contact_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = bcc_contact_settings();
	$name = BCC_CONTACT_OPTION;
	$test = get_transient( 'bcc_contact_test_result' );
	delete_transient( 'bcc_contact_test_result' );
	$field = function ( $key, $label, $type = 'text', $placeholder = '', $help = '' ) use ( $s, $name ) {
		$value = 'smtp_pass' === $key ? '' : $s[ $key ];
		echo '<tr><th scope="row"><label for="bcc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="bcc-' . esc_attr( $key ) . '" name="' . esc_attr( $name . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" />';
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contact Form', 'bcc' ); ?></h1>
		<?php settings_errors( BCC_CONTACT_OPTION ); ?>
		<?php if ( 'ok' === $test ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: %s: emails. */ __( 'Test email sent to %s. Check the inbox (and the spam folder).', 'bcc' ), implode( ', ', bcc_contact_recipients() ) ) ); ?></p></div>
		<?php elseif ( $test ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( sprintf( /* translators: %s: error. */ __( 'The test email could not be sent: %s. Turn on SMTP below with your email provider’s settings.', 'bcc' ), $test ) ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Messages from the Contact Us page are emailed to the addresses below and also saved under “Contact Messages” in the admin menu, so nothing is lost if an email fails.', 'bcc' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'bcc_contact' ); ?>
			<h2 class="title"><?php esc_html_e( 'Where messages go', 'bcc' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bcc-recipients"><?php esc_html_e( 'Send messages to', 'bcc' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="2" id="bcc-recipients" name="<?php echo esc_attr( $name . '[recipients]' ); ?>" placeholder="info@example.com, office@example.com"><?php echo esc_textarea( $s['recipients'] ); ?></textarea>
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: admin email. */ __( 'One or more email addresses, separated by commas. Leave empty to use the site admin email (%s).', 'bcc' ), get_option( 'admin_email' ) ) ); ?></p>
					</td>
				</tr>
				<?php
				$field( 'subject_prefix', __( 'Email subject prefix', 'bcc' ), 'text', '[' . get_bloginfo( 'name' ) . ']', __( 'Added before the subject the visitor typed.', 'bcc' ) );
				$field( 'success_message', __( 'Message shown after sending', 'bcc' ), 'text' );
				$field( 'from_email', __( 'Send from address', 'bcc' ), 'email', 'noreply@yourdomain.com', __( 'Optional. Use an address on your own domain so emails are not marked as spam. Replies go to the visitor’s email automatically.', 'bcc' ) );
				$field( 'from_name', __( 'Send from name', 'bcc' ), 'text', get_bloginfo( 'name' ) );
				?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Email delivery (SMTP, optional)', 'bcc' ); ?></h2>
			<p><?php esc_html_e( 'If test emails do not arrive, send through your email provider instead of the web server. Use the SMTP details from your host or email service (for example Hostinger Email, Google Workspace or Zoho).', 'bcc' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Use SMTP', 'bcc' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name . '[smtp_enabled]' ); ?>" value="1" <?php checked( $s['smtp_enabled'] ); ?> /> <?php esc_html_e( 'Send all site emails through the SMTP server below', 'bcc' ); ?></label></td>
				</tr>
				<?php
				$field( 'smtp_host', __( 'SMTP server', 'bcc' ), 'text', 'smtp.hostinger.com' );
				$field( 'smtp_port', __( 'Port', 'bcc' ), 'number', '587', __( '587 with TLS, or 465 with SSL.', 'bcc' ) );
				?>
				<tr>
					<th scope="row"><label for="bcc-smtp_secure"><?php esc_html_e( 'Encryption', 'bcc' ); ?></label></th>
					<td>
						<select id="bcc-smtp_secure" name="<?php echo esc_attr( $name . '[smtp_secure]' ); ?>">
							<option value="tls" <?php selected( $s['smtp_secure'], 'tls' ); ?>>TLS</option>
							<option value="ssl" <?php selected( $s['smtp_secure'], 'ssl' ); ?>>SSL</option>
							<option value="none" <?php selected( $s['smtp_secure'], 'none' ); ?>><?php esc_html_e( 'None', 'bcc' ); ?></option>
						</select>
					</td>
				</tr>
				<?php
				$field( 'smtp_user', __( 'Username', 'bcc' ), 'text', 'you@yourdomain.com', __( 'Usually your full email address.', 'bcc' ) );
				$field( 'smtp_pass', __( 'Password', 'bcc' ), 'password', $s['smtp_pass'] ? __( '(saved, leave empty to keep)', 'bcc' ) : '', __( 'Stored in this site’s database. Use an app password if your provider offers one.', 'bcc' ) );
				?>
				<?php if ( $s['smtp_pass'] ) : ?>
				<tr><th scope="row"></th><td><label><input type="checkbox" name="<?php echo esc_attr( $name . '[smtp_pass_clear]' ); ?>" value="1" /> <?php esc_html_e( 'Remove the saved password', 'bcc' ); ?></label></td></tr>
				<?php endif; ?>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2 class="title"><?php esc_html_e( 'Check it works', 'bcc' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bcc_contact_test" />
			<?php wp_nonce_field( 'bcc_contact_test' ); ?>
			<p><?php echo esc_html( sprintf( /* translators: %s: emails. */ __( 'Save your changes first, then send a test email to %s.', 'bcc' ), implode( ', ', bcc_contact_recipients() ) ) ); ?></p>
			<?php submit_button( __( 'Send test email', 'bcc' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
