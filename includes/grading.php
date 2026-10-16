<?php
/**
 * Result calculation: percentage, grade and pass/fail.
 *
 * The Certificate Manager exposes a list of grades (A+/A/B/C) but no
 * thresholds, so the thresholds live here as portal settings and can be
 * changed without touching code.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grades recognised by the portal when nothing is configured.
 *
 * @return array
 */
function sp_default_grade_scale() {
	return array(
		array(
			'min'   => 90,
			'grade' => 'A+',
		),
		array(
			'min'   => 75,
			'grade' => 'A',
		),
		array(
			'min'   => 60,
			'grade' => 'B',
		),
		array(
			'min'   => 45,
			'grade' => 'C',
		),
	);
}

/**
 * Normalise the stored grade scale into a descending list of [min, grade] pairs.
 *
 * @return array
 */
function sp_normalized_grade_scale() {
	$scale = sp_settings();
	$raw   = isset( $scale['grade_scale'] ) ? $scale['grade_scale'] : array();

	if ( ! is_array( $raw ) || ! $raw ) {
		$raw = sp_default_grade_scale();
	}

	$clean = array();

	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['min'], $row['grade'] ) ) {
			continue;
		}

		$min   = (float) $row['min'];
		$grade = trim( (string) $row['grade'] );

		if ( '' === $grade ) {
			continue;
		}

		$clean[] = array(
			'min'   => $min,
			'grade' => $grade,
		);
	}

	if ( ! $clean ) {
		$clean = sp_default_grade_scale();
	}

	usort(
		$clean,
		function ( $a, $b ) {
			if ( $a['min'] === $b['min'] ) {
				return 0;
			}

			return ( $a['min'] < $b['min'] ) ? 1 : -1;
		}
	);

	return $clean;
}

/**
 * Map a percentage to a grade using the configured scale.
 *
 * @param float $percentage Percentage 0-100.
 * @return string Grade, or '' when the percentage is below every threshold.
 */
function sp_grade_for_percentage( $percentage ) {
	$percentage = (float) $percentage;

	foreach ( sp_normalized_grade_scale() as $row ) {
		if ( $percentage + PHP_FLOAT_EPSILON >= $row['min'] ) {
			return $row['grade'];
		}
	}

	return '';
}

/**
 * Validate and compute a result from four marks.
 *
 * @param array $args theory_max, theory_obtained, practical_max, practical_obtained.
 * @return array|WP_Error {
 *     @type float  $percentage
 *     @type string $grade
 *     @type string $result 'pass' or 'fail'
 * }
 */
function sp_calculate_result( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'theory_max'        => 0,
			'theory_obtained'   => 0,
			'practical_max'     => 0,
			'practical_obtained' => 0,
		)
	);

	$theory_max        = (float) $args['theory_max'];
	$theory_obtained   = (float) $args['theory_obtained'];
	$practical_max     = (float) $args['practical_max'];
	$practical_obtained = (float) $args['practical_obtained'];

	if ( $theory_max < 0 || $practical_max < 0 ) {
		return new WP_Error( 'sp_negative_max', 'Maximum marks cannot be negative.' );
	}

	// Both maxima are compulsory: without them the percentage would be wrong.
	if ( $theory_max <= 0 ) {
		return new WP_Error( 'sp_theory_max_required', 'Theory maximum marks are required.' );
	}

	if ( $practical_max <= 0 ) {
		return new WP_Error( 'sp_practical_max_required', 'Practical maximum marks are required.' );
	}

	if ( $theory_obtained < 0 || $practical_obtained < 0 ) {
		return new WP_Error( 'sp_negative_obtained', 'Obtained marks cannot be negative.' );
	}

	if ( $theory_max > 0 && $theory_obtained > $theory_max ) {
		return new WP_Error(
			'sp_theory_over',
			sprintf( 'Theory obtained (%s) cannot exceed the maximum (%s).', $theory_obtained, $theory_max )
		);
	}

	if ( $practical_max > 0 && $practical_obtained > $practical_max ) {
		return new WP_Error(
			'sp_practical_over',
			sprintf( 'Practical obtained (%s) cannot exceed the maximum (%s).', $practical_obtained, $practical_max )
		);
	}

	$total_max        = $theory_max + $practical_max;
	$total_obtained   = $theory_obtained + $practical_obtained;
	$percentage       = $total_max > 0 ? round( ( $total_obtained / $total_max ) * 100, 2 ) : 0.0;
	$percentage       = min( 100.0, max( 0.0, $percentage ) );

	$settings = sp_settings();
	$pass_mark = isset( $settings['pass_mark'] ) ? (float) $settings['pass_mark'] : 35.0;

	// Rounding can push a mark a hair under the threshold (e.g. 34.99 stored as 35).
	$comparison = round( $percentage, 2 ) + 0.005;

	return array(
		'percentage' => $percentage,
		'grade'      => sp_grade_for_percentage( $percentage ),
		'result'     => ( $comparison + PHP_FLOAT_EPSILON >= $pass_mark ) ? 'pass' : 'fail',
	);
}

/**
 * Human label for a stored result status.
 *
 * @param string $result Stored status.
 * @return string
 */
function sp_result_label( $result ) {
	switch ( $result ) {
		case 'pass':
			return 'Pass';
		case 'fail':
			return 'Fail';
		default:
			return 'Pending';
	}
}