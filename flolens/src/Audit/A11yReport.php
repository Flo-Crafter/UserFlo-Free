<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Turns what a scan stored (the findings and the per-check "tested" counts in the page's metrics) into what the screens show: a status for
 * every check, the findings grouped by how sure they are, and the list of things only a person can judge.
 *
 * Statuses, in the words the screens use:
 *  - issue       Issue detected          (the check found a specific problem)
 *  - potential   Potential issue         (a pattern that may be a problem; cannot be confirmed from HTML)
 *  - manual      Manual review required  (cannot be decided automatically)
 *  - pass        Passed automated check  (something was tested and the condition was met; NOT a statement of WCAG conformance)
 *  - not_tested  Not tested              (the check could not be made, for example no colours were available)
 *  - none        Nothing to test         (the page has nothing of this kind)
 *  - excluded    Excluded by a rule      (found, but an exclusion rule hides it)
 */
final class A11yReport {

	/** True when the stored metrics already hold this version's accessibility data for the current edition (otherwise the page is read again once). */
	public static function current( $metrics_json, $full ) {
		$m = is_string( $metrics_json ) && '' !== $metrics_json ? json_decode( $metrics_json, true ) : $metrics_json;
		if ( ! is_array( $m ) || empty( $m['a11y'] ) || ! is_array( $m['a11y'] ) ) {
			return false;
		}
		return (int) $m['a11y']['v'] === A11yBasic::VERSION && ( ! $full || ! empty( $m['a11y']['full'] ) );
	}

	/**
	 * One row per check made on the page.
	 *
	 * @param array $stored  The metrics' "a11y" block ( v, full, checks: code => t, f, nt ).
	 * @param array $active  Active (not excluded) findings of the page, as Findings::for_pages() returns them.
	 * @return array[] code, title, wcag (list of sc/level), detect, status, tested, found.
	 */
	public static function checklist( $stored, array $active ) {
		$by = array();
		foreach ( $active as $f ) {
			$by[ $f['code'] ] = isset( $by[ $f['code'] ] ) ? $by[ $f['code'] ] + 1 : 1;
		}
		$rows = array();
		foreach ( is_array( $stored ) && isset( $stored['checks'] ) ? $stored['checks'] : array() as $code => $c ) {
			$cat = Catalog::get( $code );
			if ( ! $cat || empty( $cat['wcag'] ) ) {
				continue;
			}
			$t = isset( $c['t'] ) ? (int) $c['t'] : 0;
			$f = isset( $c['f'] ) ? (int) $c['f'] : 0;
			if ( $f > 0 ) {
				$status = isset( $by[ $code ] ) ? $cat['detect'] : 'excluded';
			} elseif ( ! empty( $c['nt'] ) ) {
				$status = 'not_tested';
			} else {
				$status = $t > 0 ? 'pass' : 'none';
			}
			$rows[] = array( 'code' => $code, 'title' => $cat['title'], 'wcag' => self::wcag( $cat['wcag'] ), 'detect' => $cat['detect'], 'status' => $status, 'tested' => $t, 'found' => $f );
		}
		return $rows;
	}

	/** array( '1.1.1' => 'A' ) to a list the screens can render. */
	public static function wcag( array $sc ) {
		$out = array();
		foreach ( $sc as $n => $level ) {
			$out[] = array( 'sc' => (string) $n, 'level' => (string) $level );
		}
		return $out;
	}

	/** What automated checks cannot decide, for the "Manual review required" list. */
	public static function manual() {
		$out = array();
		foreach ( A11yCatalog::manual_topics() as $sc => $t ) {
			$out[] = array( 'sc' => (string) $sc, 'level' => $t[0], 'title' => $t[1], 'what' => $t[2] );
		}
		return $out;
	}

	/**
	 * The site overview: how many findings of each accessibility check there are (exclusions applied), most certain first.
	 *
	 * @param array $by_code code => count of active findings.
	 */
	public static function overview( array $by_code ) {
		$order = array( 'issue' => 0, 'potential' => 1, 'manual' => 2 );
		$rows  = array();
		foreach ( A11yCatalog::meta() as $code => $m ) {
			if ( empty( $by_code[ $code ] ) ) {
				continue;
			}
			$cat    = Catalog::get( $code );
			$rows[] = array( 'code' => $code, 'title' => $cat['title'], 'wcag' => self::wcag( $m[0] ), 'detect' => $m[1], 'count' => (int) $by_code[ $code ] );
		}
		usort( $rows, static function ( $a, $b ) use ( $order ) {
			return array( $order[ $a['detect'] ], -$a['count'] ) <=> array( $order[ $b['detect'] ], -$b['count'] );
		} );
		return $rows;
	}
}
