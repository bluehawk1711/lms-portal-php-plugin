<?php
/**
 * Shared date & time input component (G3).
 *
 * Enqueues the self-contained sp-datetime pair on the admin screens and
 * teacher routes that render date/time inputs. Progressive enhancement only:
 * without JavaScript the plain native inputs keep working exactly as before.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the date & time component stylesheet + script.
 */
function sp_datetime_enqueue() {
	wp_enqueue_style(
		'student-portal-datetime',
		SP_URL . 'assets/css/sp-datetime.css',
		array(),
		SP_VERSION
	);

	wp_enqueue_script(
		'student-portal-datetime',
		SP_URL . 'assets/js/sp-datetime.js',
		array(),
		SP_VERSION,
		true
	);
}