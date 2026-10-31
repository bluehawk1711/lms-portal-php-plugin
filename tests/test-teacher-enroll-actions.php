<?php
/**
 * Standalone tests for the teacher enrolment actions and the course-outline
 * CSV importer.
 *
 * No WordPress bootstrap and no database are needed: WordPress functions are
 * shimmed below, `$wpdb` is replaced with an in-memory double, and the real
 * plugin functions are loaded on top. Flow control out of a redirect or a
 * wp_die() is implemented with exceptions, so every path can be asserted.
 *
 * Run: php tests/test-teacher-enroll-actions.php
 *
 * @package StudentPortal
 */

/* -------------------------------------------------------------------------
 * Shims: WordPress core surfaces the loaded plugin files touch.
 * ---------------------------------------------------------------------- */

define( 'ABSPATH', __DIR__ . '/wordpress/' ); // Guard clauses only; no file is read from it.
define( 'SP_PATH', dirname( __DIR__ ) . '/' );

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/** Redirect out of a handler under test by throwing instead of exiting. */
class SP_Test_Redirect extends Exception {}

/** wp_die() under test. */
class SP_Test_Die extends Exception {}

/** wp_send_json*() under test. */
class SP_Test_Json extends Exception {
	public $payload;
	public $success;

	public function __construct( $success, $payload ) {
		parent::__construct( 'json' );
		$this->success = $success;
		$this->payload = $payload;
	}
}

function add_action() {}
function add_filter() {}
function register_setting() {}

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return (string) $text; }
function esc_attr( $text ) { return (string) $text; }
function esc_url( $url ) { return (string) $url; }
function esc_url_raw( $url ) { return (string) $url; }
function esc_js( $text ) { return (string) $text; }
function esc_textarea( $text ) { return (string) $text; }
function wp_kses_post( $text ) { return (string) $text; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function get_option( $name, $default = false ) {
	return isset( $GLOBALS['sp_test_options'][ $name ] ) ? $GLOBALS['sp_test_options'][ $name ] : $default;
}

function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$pair = $args[0];
		$url  = isset( $args[1] ) ? (string) $args[1] : '';
	} else {
		$pair = array( $args[0] => $args[1] );
		$url  = isset( $args[2] ) ? (string) $args[2] : '';
	}

	$query = '';
	$base  = $url;

	if ( false !== strpos( $base, '?' ) ) {
		$parts  = explode( '?', $base, 2 );
		$base   = $parts[0];
		$query  = $parts[1];
	}

	parse_str( $query, $existing );
	$merged = array_merge( $existing, $pair );
	$qs     = array();

	foreach ( $merged as $key => $value ) {
		$qs[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
	}

	return $base . '?' . implode( '&', $qs );
}

function wp_safe_redirect( $url, $status = 302 ) {
	throw new SP_Test_Redirect( (string) $url );
}

function wp_die( $message = '', $title = '', $args = array() ) {
	throw new SP_Test_Die( is_string( $message ) ? $message : wp_json_encode( $message ) );
}

function wp_send_json_success( $data = null ) {
	throw new SP_Test_Json( true, $data );
}

function wp_send_json_error( $data = null, $status_code = null ) {
	throw new SP_Test_Json( false, $data );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function nocache_headers() {}

function admin_url( $path = '' ) {
	return 'https://portal.test/wp-admin/' . ltrim( (string) $path, '/' );
}

function get_query_var( $var, $default = '' ) {
	global $sp_test_query_vars;

	return isset( $sp_test_query_vars[ $var ] ) ? $sp_test_query_vars[ $var ] : $default;
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return 1;
}

function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test" />';
}

function current_user_can( $cap ) {
	global $sp_test_caps;

	return ! empty( $sp_test_caps[ $cap ] );
}

function user_can( $user, $cap ) {
	return current_user_can( $cap );
}

function get_current_user_id() {
	global $sp_test_current_user;

	return (int) $sp_test_current_user;
}

function is_user_logged_in() {
	return get_current_user_id() > 0;
}

class SP_Test_User {
	public $ID        = 0;
	public $user_email = '';
	private $caps;

	public function __construct( $id, $caps = array() ) {
		$this->ID   = (int) $id;
		$this->caps = $caps;
	}

	public function exists() {
		return $this->ID > 0;
	}

	public function has_cap( $cap ) {
		return ! empty( $this->caps[ $cap ] );
	}
}

function wp_get_current_user() {
	global $sp_test_current_user, $sp_test_caps;

	return new SP_Test_User( $sp_test_current_user, (array) $sp_test_caps );
}

/** Setting doubles: default everything to "on" so email paths are exercised. */
function sp_setting( $key, $default = null ) {
	global $sp_test_settings;

	return array_key_exists( $key, $sp_test_settings ) ? $sp_test_settings[ $key ] : $default;
}

/** The full settings array; `sp_normalized_grade_scale()` reads the scale from it. */
function sp_settings() {
	global $sp_test_settings;

	return is_array( $sp_test_settings ) ? $sp_test_settings : array();
}

/** Outgoing mail capture. */
function sp_send_mail( $to, $subject, $body, $args = array() ) {
	global $sp_test_mail;

	$sp_test_mail[] = array( 'to' => $to, 'subject' => $subject );

	return true;
}

function sp_email_shell( $subject, $body ) {
	return $body;
}

function sp_portal_url( $path = '' ) {
	return 'https://portal.test/' . ltrim( (string) $path, '/' );
}

/* -------------------------------------------------------------------------
 * In-memory $wpdb double. Query dispatch is substring-based and throws on
 * anything unrecognised, so a new query added to a tested path fails loudly
 * instead of silently returning null.
 * ---------------------------------------------------------------------- */

class SP_Fake_WPDB {
	public $prefix       = 'wp_';
	public $insert_id    = 0;
	public $last_error   = '';
	public $last_query   = '';
	public $tables       = array();
	public $emails_sent  = 0;

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i = 0;

		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$i, $args ) {
				$value = array_key_exists( $i, $args ) ? $args[ $i ] : null;
				++$i;

				if ( '%d' === $m[0] ) {
					return (string) (int) $value;
				}

				if ( '%f' === $m[0] ) {
					return (string) (float) $value;
				}

				return "'" . str_replace( "'", "''", (string) $value ) . "'";
			},
			$query
		);
	}

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function get_var( $sql ) {
		$rows = $this->run_select( $sql, 'all' );

		if ( ! $rows ) {
			return null;
		}

		$values = array_values( (array) $rows[0] );

		return $values ? $values[0] : null;
	}

	public function get_row( $sql, $output = OBJECT ) {
		$rows = $this->run_select( $sql, 'row' );

		if ( ! $rows ) {
			return null;
		}

		return $this->format_row( $rows[0], $output );
	}

	public function get_results( $sql, $output = OBJECT ) {
		$rows = $this->run_select( $sql, 'all' );

		return array_map(
			function ( $row ) use ( $output ) {
				return $this->format_row( $row, $output );
			},
			$rows
		);
	}

	public function get_col( $sql ) {
		$rows = $this->run_select( $sql, 'all' );
		$col  = array();

		foreach ( $rows as $row ) {
			$values = array_values( $row );
			$col[]  = $values ? $values[0] : null;
		}

		return $col;
	}

	public function query( $sql ) {
		$this->last_query = $sql;
		$matches          = array();

		if ( preg_match( '/^DELETE FROM (\S+) WHERE (.+)$/i', trim( $sql ), $matches ) ) {
			return $this->delete_where( $matches[1], $matches[2] );
		}

		throw new RuntimeException( 'Fake wpdb cannot run query: ' . $sql );
	}

	public function insert( $table, $data, $format = null ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->tables[ $table ] = array();
		}

		$max_id = 0;

		foreach ( (array) $this->tables[ $table ] as $existing ) {
			$max_id = max( $max_id, (int) $existing->id );
		}

		$row       = (array) $data;
		$row['id'] = isset( $row['id'] ) ? (int) $row['id'] : $max_id + 1;

		$this->tables[ $table ][ $row['id'] ] = (object) $row;
		$this->insert_id                       = (int) $row['id'];

		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}

		$changed = 0;

		foreach ( $this->tables[ $table ] as $id => $row ) {
			if ( $this->row_matches( $row, $where ) ) {
				foreach ( (array) $data as $key => $value ) {
					$row->$key = $value;
				}
				$this->tables[ $table ][ $id ] = $row;
				++$changed;
			}
		}

		return $changed;
	}

	public function delete( $table, $where, $format = null ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}

		$deleted = 0;

		foreach ( $this->tables[ $table ] as $id => $row ) {
			if ( $this->row_matches( $row, $where ) ) {
				unset( $this->tables[ $table ][ $id ] );
				++$deleted;
			}
		}

		return $deleted;
	}

	private function row_matches( $row, $where ) {
		foreach ( (array) $where as $key => $value ) {
			$actual = isset( $row->$key ) ? $row->$key : null;

			if ( null === $value ) {
				if ( null !== $actual && '' !== $actual && false !== $actual ) {
					return false;
				}
				continue;
			}

			if ( is_numeric( $value ) && is_numeric( $actual ) ) {
				if ( (int) $value !== (int) $actual ) {
					return false;
				}
				continue;
			}

			if ( (string) $value !== (string) $actual ) {
				return false;
			}
		}

		return true;
	}

	private function delete_where( $table, $condition ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}

		$deleted = 0;

		foreach ( $this->tables[ $table ] as $id => $row ) {
			if ( preg_match( '/^(\w+) = (\'.*\'|\d+)$/', trim( $condition ), $m ) ) {
				$expected = ( "'" === $m[2][0] ) ? substr( $m[2], 1, -1 ) : (int) $m[2];
				$actual   = isset( $row->{$m[1]} ) ? $row->{$m[1]} : null;

				$hit = ( is_int( $expected ) ) ? ( (int) $actual === $expected ) : ( (string) $actual === $expected );

				if ( $hit ) {
					unset( $this->tables[ $table ][ $id ] );
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	private function run_select( $sql, $mode ) {
		$this->last_query = $sql;

		// MAX(position) — the curriculum append query.
		if ( stripos( $sql, 'MAX( position )' ) !== false ) {
			preg_match( '/course_id = (\d+)/', $sql, $m );
			$course = isset( $m[1] ) ? (int) $m[1] : 0;
			$max    = 0;

			foreach ( $this->rows_of( 'wp_sp_curriculum' ) as $row ) {
				if ( (int) $row->course_id === $course && isset( $row->position ) ) {
					$max = max( $max, (int) $row->position );
				}
			}

			return array( array( 'max' => $max ) );
		}

		// The enrolment dialog's live search.
		if ( stripos( $sql, 'NOT EXISTS' ) !== false && stripos( $sql, 'student_profiles' ) !== false ) {
			preg_match( '/e\.class_id = (\d+)/', $sql, $cm );
			$class = isset( $cm[1] ) ? (int) $cm[1] : 0;
			preg_match( "/p\.full_name LIKE '([^']*)'/", $sql, $nm );
			$needle = isset( $nm[1] ) ? str_replace( "''", "'", $nm[1] ) : '';
			$needle = '%' . str_replace( '\%', '%', str_replace( '\_', '_', $needle ) ) . '%';
			// Undo the esc_like() escaping (we only ever emit \% and \_).
			$out = array();

			foreach ( $this->rows_of( 'wp_sp_student_profiles' ) as $profile ) {
				if ( 'active' !== ( isset( $profile->status ) ? $profile->status : '' ) ) {
					continue;
				}

				if ( ! $this->like_match( $needle, isset( $profile->full_name ) ? $profile->full_name : '' )
					&& ! $this->like_match( $needle, isset( $profile->email ) ? $profile->email : '' ) ) {
					continue;
				}

				$blocked = false;

				foreach ( $this->rows_of( 'wp_sp_enrollments' ) as $enrollment ) {
					if ( (int) $enrollment->profile_id === (int) $profile->id
						&& (int) $enrollment->class_id === $class
						&& in_array( $enrollment->status, array( 'active', 'completed' ), true ) ) {
						$blocked = true;
						break;
					}
				}

				if ( $blocked ) {
					continue;
				}

				$out[] = array(
					'id'         => (int) $profile->id,
					'full_name'  => (string) $profile->full_name,
					'email'      => (string) $profile->email,
				);
			}

			usort(
				$out,
				function ( $a, $b ) {
					return strcasecmp( $a['full_name'], $b['full_name'] );
				}
			);

			return array_slice( $out, 0, 8 );
		}

		// Teacher roster for a class (teachers INNER JOIN teacher_classes).
		if ( stripos( $sql, 'INNER JOIN wp_sp_teachers' ) !== false ) {
			preg_match( '/a\.class_id = (\d+)/', $sql, $cm );
			$class = isset( $cm[1] ) ? (int) $cm[1] : 0;
			$out   = array();

			foreach ( $this->rows_of( 'wp_sp_teacher_classes' ) as $link ) {
				if ( (int) $link->class_id !== $class ) {
					continue;
				}

				foreach ( $this->rows_of( 'wp_sp_teachers' ) as $teacher ) {
					if ( (int) $teacher->id === (int) $link->teacher_id ) {
						$row               = (array) $teacher;
						$row['is_primary'] = isset( $link->is_primary ) ? (int) $link->is_primary : 0;
						$out[]             = (object) $row;
					}
				}
			}

			usort(
				$out,
				function ( $a, $b ) {
					if ( (int) $a->is_primary !== (int) $b->is_primary ) {
						return (int) $b->is_primary - (int) $a->is_primary;
					}

					return strcasecmp( $a->full_name, $b->full_name );
				}
			);

			return $out;
		}

		$queries = $this->select_queries();

		foreach ( $queries as $needle => $table ) {
			if ( false !== stripos( $sql, $needle ) ) {
				$rows = $this->rows_of( $table );
				$out  = array();

				foreach ( $rows as $row ) {
					if ( $this->matches( $row, $sql ) ) {
						$out[] = $row;
					}
				}

				// COUNT(*) answers.
				if ( preg_match( '/COUNT\(\*\)/i', $sql ) ) {
					return array( array( 'count' => count( $out ) ) );
				}

				return $out;
			}
		}

		throw new RuntimeException( 'Fake wpdb cannot run query: ' . $sql );
	}

	private function like_match( $pattern, $subject ) {
		$regex = '/^' . str_replace( array( '%', '_' ), array( '.*', '.' ), preg_quote( str_replace( '\%', '%', str_replace( '\_', '_', $pattern ) ), '/' ) ) . '$/i';

		return (bool) preg_match( $regex, (string) $subject );
	}

	private function select_queries() {
		return array(
			'FROM wp_sp_teachers WHERE user_id =' => 'wp_sp_teachers',
			'FROM wp_sp_teachers WHERE id =' => 'wp_sp_teachers',
			'FROM wp_sp_teacher_classes WHERE teacher_id =' => 'wp_sp_teacher_classes',
			'FROM wp_sp_student_profiles WHERE id =' => 'wp_sp_student_profiles',
			'FROM wp_sp_student_profiles WHERE user_id =' => 'wp_sp_student_profiles',
			'FROM wp_sp_classes WHERE id =' => 'wp_sp_classes',
			'FROM wp_sp_courses WHERE id =' => 'wp_sp_courses',
			'FROM wp_sp_enrollments e' => 'wp_sp_enrollments',
			'FROM wp_sp_enrollments WHERE profile_id =' => 'wp_sp_enrollments',
			'FROM wp_sp_enrollments WHERE id =' => 'wp_sp_enrollments',
			'FROM wp_sp_enrollments WHERE enroll_id =' => 'wp_sp_enrollments',
			'FROM wp_sp_enrollments WHERE enroll_id LIKE' => 'wp_sp_enrollments',
			'FROM wp_sp_curriculum WHERE id =' => 'wp_sp_curriculum',
			'FROM wp_sp_curriculum WHERE course_id =' => 'wp_sp_curriculum',
		);
	}

	private function rows_of( $table ) {
		return isset( $this->tables[ $table ] ) ? array_values( $this->tables[ $table ] ) : array();
	}

	private function matches( $row, $sql ) {
		// IN ('a', 'b') status filters.
		if ( preg_match( "/status IN \(([^)]+)\)/i", $sql, $m ) ) {
			$allowed = array_map(
				function ( $v ) {
					return trim( $v, " '" );
				},
				explode( ',', $m[1] )
			);

			$status  = isset( $row->status ) ? (string) $row->status : '';

			if ( ! in_array( $status, $allowed, true ) ) {
				return false;
			}
		}

		// LIKE on enroll_id.
		if ( preg_match( "/enroll_id LIKE '([^']*)'/i", $sql, $m ) ) {
			if ( ! $this->like_match( str_replace( "''", "'", $m[1] ), isset( $row->enroll_id ) ? $row->enroll_id : '' ) ) {
				return false;
			}
		}

		// Equality predicates.
		if ( preg_match_all( '/(\w+(?:\.\w+)?) = (\'(?:[^\']|\'\')*\'|\d+|NULL)/i', $sql, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$column = $m[1];

				if ( false !== strpos( $column, '.' ) ) {
					$column = substr( $column, strpos( $column, '.' ) + 1 );
				}

				$raw = $m[2];

				if ( 'NULL' === strtoupper( $raw ) ) {
					$value = null;
				} elseif ( "'" === $raw[0] ) {
					$value = str_replace( "''", "'", substr( $raw, 1, -1 ) );
				} else {
					$value = (int) $raw;
				}

				$actual = isset( $row->$column ) ? $row->$column : null;

				if ( null === $value ) {
					if ( null !== $actual && '' !== $actual ) {
						return false;
					}
					continue;
				}

				if ( is_int( $value ) ) {
					if ( (int) $actual !== $value ) {
						return false;
					}
					continue;
				}

				if ( (string) $actual !== $value ) {
					return false;
				}
			}
		}

		return true;
	}

	private function format_row( $row, $output ) {
		if ( is_array( $row ) ) {
			$row = (object) $row;
		}

		return ( defined( 'ARRAY_A' ) && ARRAY_A === $output ) ? (array) $row : $row;
	}
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'ARRAY_N', 'ARRAY_N' );
}

/* -------------------------------------------------------------------------
 * Boot the real plugin code on top of the shims.
 * ---------------------------------------------------------------------- */

$sp_test_current_user = 0;
$sp_test_caps         = array();
$sp_test_settings     = array( 'notify_on_enrollment' => true );
$sp_test_query_vars   = array();
$sp_test_mail         = array();

$GLOBALS['wpdb'] = new SP_Fake_WPDB();

require SP_PATH . 'includes/db.php';
require SP_PATH . 'includes/repository.php';
require SP_PATH . 'includes/classes.php';
require SP_PATH . 'includes/activity.php';
require SP_PATH . 'includes/curriculum.php';
require SP_PATH . 'includes/import.php';
require SP_PATH . 'includes/admin-actions.php';
require SP_PATH . 'includes/teacher-routes.php';
require SP_PATH . 'includes/teacher-render.php';
require SP_PATH . 'includes/grading.php';
require SP_PATH . 'includes/certificates.php';

/* -------------------------------------------------------------------------
 * Helpers.
 * ---------------------------------------------------------------------- */

$sp_tests_run    = 0;
$sp_tests_failed = 0;

function sp_test_assert( $condition, $label ) {
	global $sp_tests_run, $sp_tests_failed;

	++$sp_tests_run;

	if ( $condition ) {
		echo "  ok  - {$label}\n";
	} else {
		++$sp_tests_failed;
		echo "  FAIL - {$label}\n";
	}
}

function sp_test_reset_db() {
	global $wpdb, $sp_test_current_user, $sp_test_caps, $sp_test_settings, $sp_test_mail, $sp_test_query_vars;

	$wpdb             = new SP_Fake_WPDB();
	$GLOBALS['wpdb']  = $wpdb;
	$sp_test_mail     = array();
	$sp_test_query_vars = array();
	$sp_test_settings = array( 'notify_on_enrollment' => true );
	$sp_test_caps     = array( 'manage_options' => true ); // Administrator by default.
	$sp_test_current_user = 99;

	// Class 5 exists; teacher row 7 (user 42) is assigned to it.
	$wpdb->insert( 'wp_sp_classes', array( 'id' => 5, 'title' => 'Web Fundamentals', 'code' => 'WEB-2025-A', 'status' => 'active' ) );
	$wpdb->insert( 'wp_sp_classes', array( 'id' => 6, 'title' => 'Other Class', 'code' => 'OTHER-1', 'status' => 'active' ) );
	$wpdb->insert( 'wp_sp_teachers', array( 'id' => 7, 'user_id' => 42, 'full_name' => 'Test Teacher', 'email' => 'teacher@example.com', 'status' => 'active' ) );
	$wpdb->insert( 'wp_sp_teacher_classes', array( 'id' => 1, 'teacher_id' => 7, 'class_id' => 5, 'is_primary' => 1 ) );
	$wpdb->insert( 'wp_sp_courses', array( 'id' => 3, 'name' => 'Web Course', 'code' => 'WEB-C', 'status' => 'active' ) );

	foreach ( array( 11, 12, 13 ) as $i => $profile_id ) {
		$wpdb->insert(
			'wp_sp_student_profiles',
			array(
				'id'         => $profile_id,
				'user_id'    => 100 + $profile_id,
				'full_name'  => 'Student ' . $profile_id,
				'email'      => "student{$profile_id}@example.com",
				'status'     => 'active',
			)
		);
	}
}

/** Rows of a fake table as a plain list (empty when the table was never touched). */
function sp_test_rows( $table ) {
	global $wpdb;

	return isset( $wpdb->tables[ $table ] ) ? array_values( (array) $wpdb->tables[ $table ] ) : array();
}

/** Sign in as the seeded teacher (row 7, WordPress user 42), not an admin. */
function sp_test_login_as_teacher() {
	global $sp_test_current_user, $sp_test_caps;

	$sp_test_current_user = 42;
	$sp_test_caps         = array();
}

/** Run a teacher action with POST data, returning the redirect URL. */
function sp_test_call_action( $callback, $post ) {
	global $_POST;

	$previous = $_POST;
	$_POST    = $post;

	try {
		$callback( 'https://portal.test/teacher/classes/5/?tab=roster' );
	} catch ( SP_Test_Redirect $redirect ) {
		$_POST = $previous;
		return $redirect->getMessage();
	}

	$_POST = $previous;

	return '';
}

/**
 * The teacher redirect's notice text (the redirect encodes it on the way out).
 */
function sp_test_notice_of( $url ) {
	$parts  = parse_url( $url );
	$notice = '';

	if ( isset( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		$notice = isset( $query['sp_notice'] ) ? $query['sp_notice'] : '';
	}

	return rawurldecode( $notice );
}

/**
 * The admin redirect's message text (sp_msg is double-encoded on the way out).
 */
function sp_test_admin_msg( $url ) {
	$parts = parse_url( $url );
	$msg   = '';

	if ( isset( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
		$msg = isset( $query['sp_msg'] ) ? $query['sp_msg'] : '';
	}

	return rawurldecode( $msg );
}

/* -------------------------------------------------------------------------
 * Tests: add_students
 * ---------------------------------------------------------------------- */

echo "add_students\n";

// 1. Admin enrolling two students into a class.
sp_test_reset_db();

$url = sp_test_call_action(
	'sp_teacher_action_add_students',
	array(
		'class_id'   => 5,
		'profile_id' => array( 11, 12 ),
	)
);

$enrollments = sp_test_rows( 'wp_sp_enrollments' );

sp_test_assert( 2 === count( $enrollments ), 'two students are enrolled' );
sp_test_assert( false !== strpos( sp_test_notice_of( $url ), '2 student' ), 'redirect reports two added' );
sp_test_assert( 'WEB-2025-A-001' === $enrollments[0]->enroll_id && 'WEB-2025-A-002' === $enrollments[1]->enroll_id, 'enrolment IDs are auto-generated from the class code' );
sp_test_assert( 'active' === $enrollments[0]->status, 'enrolment starts active' );

// 2. Re-adding an already-enrolled student is reported, not silently ignored.
sp_test_reset_db();
sp_test_call_action( 'sp_teacher_action_add_students', array( 'class_id' => 5, 'profile_id' => array( 11 ) ) );
$url   = sp_test_call_action( 'sp_teacher_action_add_students', array( 'class_id' => 5, 'profile_id' => array( 11 ) ) );
$notice = sp_test_notice_of( $url );

sp_test_assert( false !== strpos( $notice, 'No students were added' ), 'duplicate enrolment reports no students added' );
sp_test_assert( false !== strpos( $notice, 'already enrolled' ), 'duplicate enrolment names the reason' );
sp_test_assert( 1 === count( sp_test_rows( 'wp_sp_enrollments' ) ), 'no second row was written' );

// 3. Empty selection is rejected before touching the database.
sp_test_reset_db();
$url = sp_test_call_action( 'sp_teacher_action_add_students', array( 'class_id' => 5 ) );

sp_test_assert( false !== strpos( sp_test_notice_of( $url ), 'Tick at least one student' ), 'empty selection is rejected' );
sp_test_assert( empty( $wpdb->tables['wp_sp_enrollments'] ), 'no enrolment row was written' );

// 4. A teacher may only enrol into a class they are assigned to.
sp_test_reset_db();
sp_test_login_as_teacher();

$url = sp_test_call_action(
	'sp_teacher_action_add_students',
	array(
		'class_id'   => 6, // Not assigned.
		'profile_id' => array( 11 ),
	)
);

sp_test_assert( false !== strpos( sp_test_notice_of( $url ), 'not assigned' ), 'unassigned class is refused' );
sp_test_assert( empty( $wpdb->tables['wp_sp_enrollments'] ), 'nothing was written for the unassigned class' );

// 5. The same teacher succeeds on their own class, with a custom enrolment ID,
//    admission date and the notify tick.
sp_test_reset_db();
sp_test_login_as_teacher();

$url = sp_test_call_action(
	'sp_teacher_action_add_students',
	array(
		'class_id'        => 5,
		'profile_id'      => array( 11 ),
		'enroll_id'       => array( 11 => 'MANUAL-77' ),
		'admission_date'  => array( 11 => '2026-01-15' ),
		'notify'          => array( 11 => 1 ),
	)
);

$rows = sp_test_rows( 'wp_sp_enrollments' );

sp_test_assert( 1 === count( $rows ), 'assigned teacher enrols successfully' );
sp_test_assert( 'MANUAL-77' === $rows[0]->enroll_id, 'typed enrolment ID is stored verbatim' );
sp_test_assert( '2026-01-15' === $rows[0]->admission_date, 'admission date is stored' );
sp_test_assert( 1 === count( $sp_test_mail ), 'notify tick emails the student' );
sp_test_assert( 'student11@example.com' === $sp_test_mail[0]['to'], 'the email went to the right student' );

// 6. Notify left unticked: enrolment happens, no email is sent.
sp_test_reset_db();

sp_test_call_action(
	'sp_teacher_action_add_students',
	array(
		'class_id'   => 5,
		'profile_id' => array( 12 ),
	)
);

sp_test_assert( 1 === count( sp_test_rows( 'wp_sp_enrollments' ) ), 'student enrolled without notify' );
sp_test_assert( 0 === count( $sp_test_mail ), 'no email when notify is not ticked' );

/* -------------------------------------------------------------------------
 * Tests: remove_student
 * ---------------------------------------------------------------------- */

echo "remove_student\n";

// 7. An assigned teacher (not an admin) removes a student from their class.
sp_test_reset_db();
sp_test_login_as_teacher();
sp_test_call_action( 'sp_teacher_action_add_students', array( 'class_id' => 5, 'profile_id' => array( 11, 12 ) ) );

$added    = sp_test_rows( 'wp_sp_enrollments' );
$first_id = (int) $added[0]->id;

$url = sp_test_call_action(
	'sp_teacher_action_remove_student',
	array(
		'class_id'       => 5,
		'enrollment_id'  => $first_id,
	)
);

sp_test_assert( false !== strpos( sp_test_notice_of( $url ), 'removed' ), 'removal reports success' );
sp_test_assert( 1 === count( sp_test_rows( 'wp_sp_enrollments' ) ), 'one enrolment was removed' );
sp_test_assert( ! isset( $wpdb->tables['wp_sp_enrollments'][ $first_id ] ), 'the targeted row is gone' );

// 8. An enrolment from a different class cannot be removed through this class.
sp_test_reset_db();
$wpdb->insert(
	'wp_sp_enrollments',
	array( 'id' => 501, 'profile_id' => 11, 'class_id' => 6, 'enroll_id' => 'OTHER-1-001', 'status' => 'active' )
);

$url = sp_test_call_action(
	'sp_teacher_action_remove_student',
	array(
		'class_id'      => 5,
		'enrollment_id' => 501,
	)
);

sp_test_assert( false !== strpos( sp_test_notice_of( $url ), 'no longer exists' ), 'a foreign enrolment is not removable' );
sp_test_assert( isset( $wpdb->tables['wp_sp_enrollments'][501] ), 'the foreign enrolment row survives' );

// 9. A teacher assigned to another class cannot touch this class at all.
sp_test_reset_db();
sp_test_login_as_teacher();
$wpdb->insert( 'wp_sp_enrollments', array( 'id' => 601, 'profile_id' => 11, 'class_id' => 6, 'enroll_id' => 'x', 'status' => 'active' ) );

$url = sp_test_call_action(
	'sp_teacher_action_remove_student',
	array(
		'class_id'      => 6, // The teacher is assigned to class 5, not 6.
		'enrollment_id' => 601,
	)
);

sp_test_assert( false !== strpos( sp_test_notice_of( $url ), 'not assigned' ), 'unassigned teacher is refused on removal' );
sp_test_assert( isset( $wpdb->tables['wp_sp_enrollments'][601] ), 'nothing was deleted' );

/* -------------------------------------------------------------------------
 * Tests: enrolment dialog search endpoint
 * ---------------------------------------------------------------------- */

echo "enroll search\n";

// 10. Candidates exclude students already in the class, match the search term
//     and refuse callers who are not assigned to the class.
sp_test_reset_db();
sp_test_call_action( 'sp_teacher_action_add_students', array( 'class_id' => 5, 'profile_id' => array( 11 ) ) );

$_GET['sp_enroll_search'] = 'student';
$sp_test_query_vars       = array( 'sp_teacher_id' => 5 );
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

try {
	sp_maybe_serve_enroll_search();
	sp_test_assert( false, 'search responds with JSON' );
} catch ( SP_Test_Json $json ) {
	$ids = array_map(
		function ( $row ) {
			return (int) $row->id;
		},
		(array) $json->payload
	);

	sp_test_assert( $json->success, 'search responds with success' );
	sp_test_assert( in_array( 12, $ids, true ) && in_array( 13, $ids, true ), 'available students are returned' );
	sp_test_assert( ! in_array( 11, $ids, true ), 'the already-enrolled student is excluded' );
}

// Unassigned caller gets refused.
sp_test_reset_db();
sp_test_login_as_teacher();
$sp_test_query_vars = array( 'sp_teacher_id' => 6 );

try {
	sp_maybe_serve_enroll_search();
	sp_test_assert( false, 'search refuses unassigned callers' );
} catch ( SP_Test_Json $json ) {
	sp_test_assert( ! $json->success, 'unassigned caller is refused' );
}

unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
unset( $_GET['sp_enroll_search'] );

/* -------------------------------------------------------------------------
 * Tests: course-outline CSV import
 * ---------------------------------------------------------------------- */

echo "curriculum CSV import\n";

/**
 * Import CSV text against course 3, returning the captured redirect URL.
 */
function sp_test_import_csv( $csv ) {
	global $_POST;

	$file = tempnam( sys_get_temp_dir(), 'spcsv' );
	file_put_contents( $file, $csv );

	$_FILES['import_file'] = array(
		'name'     => 'outline.csv',
		'tmp_name' => $file,
		'error'    => 0,
	);

	$_POST['course_id'] = 3;

	try {
		sp_admin_action_import_curriculum();
	} catch ( SP_Test_Redirect $redirect ) {
		unset( $_FILES['import_file'], $_POST['course_id'] );
		unlink( $file );
		return $redirect->getMessage();
	}

	unset( $_FILES['import_file'], $_POST['course_id'] );
	unlink( $file );

	return '';
}

// 11. The shipped sample imports as headings + nested topics.
sp_test_reset_db();
$url = sp_test_import_csv( sp_sample_curriculum_csv() );

$curriculum = sp_test_rows( 'wp_sp_curriculum' );
$headings   = array_filter(
	$curriculum,
	function ( $row ) {
		return isset( $row->group_title ) && $row->group_title;
	}
);

sp_test_assert( false !== strpos( sp_test_admin_msg( $url ), '7 item(s) imported' ), 'sample import reports every row: ' . sp_test_admin_msg( $url ) );
sp_test_assert( 7 === count( $curriculum ), 'sample creates seven outline rows' );
sp_test_assert( 2 === count( $headings ), 'sample creates two headings' );

$titles = array();
foreach ( $curriculum as $row ) {
	$titles[ $row->title ] = $row;
}

$topic = isset( $titles['Computer Hardware & Central Processing Unit'] ) ? $titles['Computer Hardware & Central Processing Unit'] : null;
$heading = isset( $titles['Computer Fundamentals & Digital Systems'] ) ? $titles['Computer Fundamentals & Digital Systems'] : null;

sp_test_assert( $topic && $heading && (int) $topic->parent_id === (int) $heading->id, 'topic nests under its heading' );
sp_test_assert( $topic && '' === (string) $topic->group_title, 'a topic carries no group title' );

// 12. A header row separated by a leading blank line is still detected.
sp_test_reset_db();
sp_test_import_csv( "\n\ntitle,detail,type,parent\nSection Heading,Overview,heading,\nA topic,,topic,Section Heading\n" );

$curriculum = sp_test_rows( 'wp_sp_curriculum' );

sp_test_assert( 2 === count( $curriculum ), 'blank lines before the header are skipped' );
sp_test_assert( isset( $curriculum[0]->group_title ) && 'Section Heading' === $curriculum[0]->group_title, 'the header row was not imported as a topic' );

// 13. A UTF-8 BOM from Excel does not break header detection.
sp_test_reset_db();
sp_test_import_csv( "\xEF\xBB\xBFtitle,detail,type,parent\nTopic One,Detail one,topic,\n" );

$curriculum = sp_test_rows( 'wp_sp_curriculum' );

sp_test_assert( 1 === count( $curriculum ), 'BOM file imports one row' );
sp_test_assert( 'Topic One' === $curriculum[0]->title, 'header with BOM is skipped, not imported as a topic' );
sp_test_assert( 'Detail one' === $curriculum[0]->detail, 'detail column maps correctly' );

// 14. A header without a detail column never mistakes the type cell for detail.
sp_test_reset_db();
sp_test_import_csv( "title,type,parent\nSection One,heading,\nNested topic,topic,Section One\n" );

$curriculum = sp_test_rows( 'wp_sp_curriculum' );

sp_test_assert( 2 === count( $curriculum ), 'type/parent-only header imports two rows' );
sp_test_assert( isset( $curriculum[1] ) && ( null === $curriculum[1]->detail || '' === (string) $curriculum[1]->detail ), 'type column is not read as the detail text' );
sp_test_assert( isset( $curriculum[1]->parent_id ) && (int) $curriculum[1]->parent_id === (int) $curriculum[0]->id, 'parent resolves to the heading above' );

// 15. A `parent` value that does not exist yet creates that heading.
sp_test_reset_db();
sp_test_import_csv( "title,detail,type,parent\nSome topic,,topic,Made Up Heading\n" );

$curriculum = sp_test_rows( 'wp_sp_curriculum' );

sp_test_assert( 2 === count( $curriculum ), 'missing parent heading is created on the fly' );
$made = null;
$child = null;
foreach ( $curriculum as $row ) {
	if ( 'Made Up Heading' === $row->title ) {
		$made = $row;
	}
	if ( 'Some topic' === $row->title ) {
		$child = $row;
	}
}
sp_test_assert( $made && $child && (int) $child->parent_id === (int) $made->id, 'the topic nests under the created heading' );
sp_test_assert( $made && $made->group_title, 'the created row is a heading' );

/* -------------------------------------------------------------------------
 * Tests: lead teacher from the class edit form
 * ---------------------------------------------------------------------- */

echo "lead teacher\n";

// 16. The class form's teacher dropdown makes the pick the single lead.
sp_test_reset_db();
$wpdb->insert( 'wp_sp_teachers', array( 'id' => 8, 'user_id' => 43, 'full_name' => 'Second Teacher', 'email' => 'second@example.com', 'status' => 'active' ) );

$ok = sp_assign_class_lead_teacher( 5, 8 );

sp_test_assert( true === $ok, 'lead teacher assignment succeeds' );

$flags = array();
foreach ( sp_test_rows( 'wp_sp_teacher_classes' ) as $row ) {
	if ( (int) $row->class_id === 5 ) {
		$flags[ (int) $row->teacher_id ] = (int) $row->is_primary;
	}
}

sp_test_assert( isset( $flags[8] ) && 1 === $flags[8], 'the chosen teacher is assigned as lead' );
sp_test_assert( isset( $flags[7] ) && 0 === $flags[7], 'the previous lead is demoted, not removed' );

$lead = sp_get_class_lead_teacher( 5 );

sp_test_assert( $lead && 8 === (int) $lead->id, 'the lead helper reports the new lead' );

// 17. An unknown teacher is refused before anything is demoted.
$err = sp_assign_class_lead_teacher( 5, 99 );

sp_test_assert( is_wp_error( $err ), 'an unknown teacher is refused' );

$flags = array();
foreach ( sp_test_rows( 'wp_sp_teacher_classes' ) as $row ) {
	if ( (int) $row->class_id === 5 ) {
		$flags[ (int) $row->teacher_id ] = (int) $row->is_primary;
	}
}

sp_test_assert( isset( $flags[8] ) && 1 === $flags[8], 'the failed attempt leaves the lead in place' );

/* -------------------------------------------------------------------------
 * Certificate grade select + displayed grade.
 * ---------------------------------------------------------------------- */

sp_test_assert(
	isset( sp_certificate_field_types()['select'] ),
	'the field list offers a select type'
);

$sp_grade_default = null;
foreach ( sp_certificate_default_fields() as $sp_df ) {
	if ( 'grade' === ( isset( $sp_df['source'] ) ? $sp_df['source'] : '' ) ) {
		$sp_grade_default = $sp_df;
	}
}

sp_test_assert(
	$sp_grade_default && 'select' === $sp_grade_default['type'],
	'the default Grade field is a select'
);

// A stored Grade field from before the select type existed is normalised on read.
$GLOBALS['sp_test_options']['sp_certificate_fields'] = array(
	array( 'key' => 'field_5', 'label' => 'Grade', 'type' => 'text', 'source' => 'grade', 'required' => true ),
	array( 'key' => 'field_9', 'label' => 'Notes', 'type' => 'text', 'source' => '', 'required' => false ),
);

$sp_norm = sp_certificate_fields();
$sp_norm_types = array();
foreach ( $sp_norm as $sp_nf ) {
	$sp_norm_types[ $sp_nf['label'] ] = $sp_nf['type'];
}

sp_test_assert(
	isset( $sp_norm_types['Grade'] ) && 'select' === $sp_norm_types['Grade'],
	'a stored text Grade field becomes a select'
);
sp_test_assert(
	isset( $sp_norm_types['Notes'] ) && 'text' === $sp_norm_types['Notes'],
	'other text fields stay text fields'
);

unset( $GLOBALS['sp_test_options']['sp_certificate_fields'] );

$sp_grade_opts = sp_certificate_field_options( array( 'source' => 'grade' ) );

sp_test_assert(
	count( $sp_grade_opts ) >= 4 && in_array( 'A+', $sp_grade_opts, true ),
	'the Grade select offers the scale grades'
);
sp_test_assert(
	array() === sp_certificate_field_options( array( 'source' => 'percentage' ) ),
	'fields without a list still render as their own type'
);

// The student page shows what was actually issued, not just the result's grade.
$sp_cert_row = (object) array(
	'grade'  => 'A+',
	'values' => wp_json_encode( array( 'field_5' => 'B' ) ),
);

sp_test_assert(
	'B' === sp_certificate_display_grade( $sp_cert_row ),
	'a reissued grade wins over the grade column'
);

$sp_cert_row = (object) array( 'grade' => 'A', 'values' => '' );

sp_test_assert(
	'A' === sp_certificate_display_grade( $sp_cert_row ),
	'rows without stored values fall back to the grade column'
);

sp_test_assert(
	'' === sp_certificate_display_grade( null ),
	'a missing certificate has no grade'
);

/* -------------------------------------------------------------------------
 * Report.
 * ---------------------------------------------------------------------- */

echo "\n";

if ( $sp_tests_failed > 0 ) {
	echo "FAILED: {$sp_tests_failed} of {$sp_tests_run} assertions failed.\n";
	exit( 1 );
}

echo "PASSED: all {$sp_tests_run} assertions.\n";
exit( 0 );
