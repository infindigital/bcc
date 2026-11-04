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
	wp_mail(
		apply_filters( 'bcc_contact_recipient', get_option( 'admin_email' ) ),
		'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject,
		$body,
		$headers
	);

	return array(
		'status'        => 1,
		'store_entries' => 1,
		'error'         => array(),
		'data'          => array(
			'message'     => __( 'Thank you! Your message has been sent. We will get back to you soon.', 'bcc' ),
			'hide_form'   => '',
			'redirect_to' => '',
			'form_data'   => $fields,
		),
	);
}
