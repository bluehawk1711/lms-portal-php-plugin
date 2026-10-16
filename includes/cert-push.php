<?php
/**
 * The single adapter that writes to the Certificate Manager.
 *
 * Before this file existed the portal only ever copied a `grade` into
 * `wp_cert_students` and expected a row to be there already, created by hand or
 * by the cert-import screen. From phase 2 the portal is the source of truth: it
 * owns the student's name, the class title the certificate should show, the
 * enrolment id and the grade, and pushes all of it here before asking the
 * vendor to render the PDF.
 *
 * Rules this file keeps:
 *   - every vendor write goes through here, is idempotent (re-running an
 *     issuance rewrites the same row rather than creating a second one) and is
 *     written to `activity_log`;
 *   - nothing in here touches the vendor schema -- no ALTERs, no new tables;
 *   - if Certificate Manager is missing or breaks, issuance fails with a clear
 *     error while the portal itself keeps working.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure a vendor course row exists for a portal course, returning its id.
 *
 * The vendor renders the certificate id as `SWJOD/{code}/{year}/{random}` and
 * looks the code up through `wp_cert_students.course_id`, so the portal's course
 * code can only reach a certificate if `wp_cert_courses` carries a row for it.
 * That table is therefore mirrored here -- and only here -- rather than edited
 * anywhere else.
 *
 * Ids are kept aligned with `sp_courses` whenever that id is free, so the two
 * tables stay interchangeable; when it is taken by a different course a fresh id
 * is used instead, because a wrong code on a certificate is worse than a
 * non-matching id.
 *
 * @param object|null $course Row from sp_courses, or null when the class has none.
 * @return int Vendor course id, or 0 when there is nothing to mirror.
 */
function sp_cert_mirror_course( $course ) {
	global $wpdb;

	if ( ! $course || ! sp_certificate_manager_active() ) {
		return 0;
	}

	$vendor = sp_cert_table( 'courses' );
	$name   = trim( (string) $course->name );
	$code   = isset( $course->code ) ? trim( (string) $course->code ) : '';
	$id     = (int) $course->id;

	if ( '' === $name ) {
		return 0;
	}

	// A row already carrying this code is the right one, wherever it lives.
	if ( '' !== $code ) {
		$by_code = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$vendor} WHERE code = %s ORDER BY id ASC LIMIT 1", $code )
		);

		if ( $by_code ) {
			return (int) $by_code->id;
		}
	}

	$by_id = $wpdb->get_row( $wpdb->prepare( "SELECT id, code FROM {$vendor} WHERE id = %d", $id ) );

	if ( $by_id && (string) $by_id->code === $code ) {
		return (int) $by_id->id;
	}

	// Prefer the portal id so the two tables stay interchangeable.
	if ( ! $by_id ) {
		$inserted = $wpdb->insert(
			$vendor,
			array(
				'id'   => $id,
				'name' => $name,
				'code' => '' !== $code ? $code : null,
			),
			array( '%d', '%s', '%s' )
		);

		if ( $inserted && (int) $wpdb->insert_id ) {
			$vendor_id = (int) $wpdb->insert_id;

			sp_log_activity( 'course.mirrored', 'course', $vendor_id, $name . ( $code ? " ({$code})" : '' ) );

			return $vendor_id;
		}
	}

	// The id was taken: take whatever the vendor's own counter hands out.
	$inserted = $wpdb->insert(
		$vendor,
		array(
			'name' => $name,
			'code' => '' !== $code ? $code : null,
		),
		array( '%s', '%s' )
	);

	if ( ! $inserted || ! (int) $wpdb->insert_id ) {
		return 0;
	}

	$vendor_id = (int) $wpdb->insert_id;

	sp_log_activity( 'course.mirrored', 'course', $vendor_id, $name . ( $code ? " ({$code})" : '' ) );

	return $vendor_id;
}

/**
 * The certificate year stamped onto a class's certificates.
 *
 * A class that has finished gets the year it finished in, so a cohort that sat
 * its exam in December and was issued in January is not dated wrongly. An
 * unfinished or undated class falls back to the current year.
 *
 * @param object $class sp_classes row.
 * @return string
 */
function sp_cert_year_for_class( $class ) {
	if ( ! empty( $class->end_date ) ) {
		$timestamp = strtotime( $class->end_date );

		if ( $timestamp && $timestamp <= time() ) {
			return wp_date( 'Y', $timestamp );
		}
	}

	return current_time( 'Y' );
}

/**
 * The completion date stamped onto a class's certificates.
 *
 * @param object $class sp_classes row.
 * @return string Y-m-d.
 */
function sp_cert_completion_for_class( $class ) {
	if ( ! empty( $class->end_date ) ) {
		$timestamp = strtotime( $class->end_date );

		if ( $timestamp && $timestamp <= time() ) {
			return wp_date( 'Y-m-d', $timestamp );
		}
	}

	return current_time( 'Y-m-d' );
}
