<?php
namespace UserFlo\Exclusions;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The single entry point the rest of FloLens asks "is this excluded?". It combines the older settings (excluded paths, roles and selectors in
 * Settings → Privacy, which keep working exactly as before) with the rules stored in the Exclusions screen.
 *
 *  - stop:  asked while tracking (page skipped, element not collected, interaction dropped before it is stored).
 *  - stats: applied in SQL by Exclusions\Sql; this class only describes it.
 *  - audit: asked by the website audit through suppresses(); the audit itself does not exist yet, so this is the agreed interface.
 */
final class Service {

	/** True when tracking must not happen on this page (older path setting or a "stop tracking" page rule). */
	public static function page_stopped( $path ) {
		foreach ( Store::active()['stop'] as $r ) {
			if ( Matcher::page( $r, $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Removes the interactions a "stop tracking" element rule covers, before anything is stored.
	 *
	 * @param array[] $events Validated events from the tracker payload (type, name, target, value, comp, …).
	 * @param string  $path   Path of the page they belong to.
	 * @return array[] The events that may be stored.
	 */
	public static function filter_events( array $events, $path ) {
		$rules = array();
		foreach ( Store::active()['stop'] as $r ) {
			if ( 'element' === $r['target'] && 'css' !== $r['field'] && Matcher::in_scope( $r, $path ) ) {
				$rules[] = $r;
			}
		}
		if ( ! $rules ) {
			return $events;
		}
		$out = array();
		foreach ( $events as $ev ) {
			foreach ( $rules as $r ) {
				if ( Matcher::element( $r, $ev, $path ) ) {
					continue 2;
				}
			}
			$out[] = $ev;
		}
		return $out;
	}

	/**
	 * What the browser should apply on this page: CSS selectors to ignore and exact element identifiers to ignore. Rules limited to other
	 * pages are not sent, and the lists are capped, so the tracker's configuration stays small.
	 *
	 * @return array{css:string,ids:string[]}
	 */
	public static function tracker( $path ) {
		$css = array();
		$ids = array();
		foreach ( Store::active()['stop'] as $r ) {
			if ( 'element' !== $r['target'] || ! Matcher::in_scope( $r, $path ) ) {
				continue;
			}
			if ( 'css' === $r['field'] ) {
				$css[] = $r['pattern'];
			} elseif ( 'target' === $r['field'] && 'exact' === $r['match_type'] && '' === $r['event_type'] ) {
				$ids[] = $r['pattern'];
			}
		}
		return array( 'css' => implode( ',', array_slice( $css, 0, 20 ) ), 'ids' => array_slice( $ids, 0, 50 ) );
	}

	/**
	 * Whether an audit finding is suppressed. The audit calls this for every finding it would show or count and keeps the finding itself
	 * (it is listed separately as "excluded"), so a rule never hides the underlying inventory.
	 *
	 * @param array $finding type (finding code), url (page path), element (stable identifier, optional).
	 */
	public static function suppresses( array $finding ) {
		foreach ( Store::active()['audit'] as $r ) {
			if ( Matcher::finding( $r, $finding ) ) {
				return true;
			}
		}
		return false;
	}

	/** Whether any audit feature is registered. The Exclusions screen offers the audit mode only then. */
	public static function audit_available() {
		return (bool) apply_filters( 'userflo_exclusions_audit_available', false );
	}

	/**
	 * The exclusions that live in Settings, in the same shape as a rule, so the Exclusions screen can show everything in one list. They are
	 * edited where they always were; they are not copied into the table.
	 */
	public static function builtin() {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Settings::get( 'exclude_paths' ) ) as $line ) {
			if ( '' !== $line ) {
				$out[] = array( 'kind' => 'path', 'mode' => 'stop', 'label' => $line, 'where' => 'privacy' );
			}
		}
		foreach ( (array) Settings::get( 'exclude_roles' ) as $role ) {
			$out[] = array( 'kind' => 'role', 'mode' => 'stop', 'label' => (string) $role, 'where' => 'privacy' );
		}
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) Settings::get( 'exclude_selectors' ) ) ) ) as $sel ) {
			$out[] = array( 'kind' => 'selector', 'mode' => 'stop', 'label' => $sel, 'where' => 'privacy' );
		}
		return $out;
	}
}
