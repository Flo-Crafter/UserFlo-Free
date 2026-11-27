<?php
namespace UserFlo\Admin;

use UserFlo\Core\Clock;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Presentation helpers shared by the admin screens. */
final class Ui {

	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/** UTC MySQL datetime => site-local formatted string. */
	public static function dt( $utc ) {
		if ( ! $utc ) {
			return '—';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $utc . ' UTC' ) );
	}

	public static function duration( $seconds ) {
		$s = max( 0, (int) $seconds );
		if ( $s < 60 ) {
			return $s . 's';
		}
		if ( $s < 3600 ) {
			return floor( $s / 60 ) . 'm ' . ( $s % 60 ) . 's';
		}
		return floor( $s / 3600 ) . 'h ' . floor( ( $s % 3600 ) / 60 ) . 'm';
	}

	public static function elapsed( $session ) {
		return max( 0, strtotime( $session->last_activity_at . ' UTC' ) - strtotime( $session->started_at . ' UTC' ) );
	}

	public static function visitor_label( $row ) {
		if ( ! empty( $row->user_id ) ) {
			if ( Settings::get( 'show_identity' ) && ! empty( $row->user_login ) ) {
				return ! empty( $row->display_name ) ? $row->display_name : $row->user_login;
			}
			return __( 'Registered user', 'flolens' ) . ' #' . (int) $row->user_id;
		}
		return __( 'Anonymous', 'flolens' ) . ' · ' . substr( $row->visitor_uid, 0, 8 );
	}

	/**
	 * Honest session state: observed logout, still active, or estimated end.
	 *
	 * @return array{label:string,class:string}
	 */
	public static function session_state( $s ) {
		switch ( \UserFlo\Core\SessionState::of( $s ) ) {
			case \UserFlo\Core\SessionState::LOGGED_OUT_OBSERVED:
				return array( 'label' => __( 'Logged out (observed)', 'flolens' ), 'class' => 'uf-badge-obs' );
			case \UserFlo\Core\SessionState::ACTIVE:
				return array( 'label' => __( 'Active', 'flolens' ), 'class' => 'uf-badge-live' );
			default:
				return array( 'label' => __( 'Ended (estimated)', 'flolens' ), 'class' => 'uf-badge-est' );
		}
	}

	public static function badge( $state ) {
		return '<span class="uf-badge ' . esc_attr( $state['class'] ) . '">' . esc_html( $state['label'] ) . '</span>';
	}

	public static function stat( $label, $value, $hint = '' ) {
		return '<div class="uf-stat"><div class="uf-stat-v' . ( strlen( (string) $value ) > 14 ? ' uf-stat-sm' : '' ) . '">' . esc_html( (string) $value ) . '</div><div class="uf-stat-l">' . esc_html( $label ) . '</div>' . ( $hint ? '<div class="uf-stat-h">' . esc_html( $hint ) . '</div>' : '' ) . '</div>';
	}

	public static function pager( $page_slug, array $args, $total, $page, $per ) {
		$pages = (int) ceil( $total / $per );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<div class="uf-pager">';
		/* translators: 1: current page, 2: total pages, 3: total items */
		echo '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d items)', 'flolens' ), $page, $pages, $total ) ) . '</span> ';
		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( self::url( $page_slug, array_merge( $args, array( 'paged' => $page - 1 ) ) ) ) . '">&laquo; ' . esc_html__( 'Previous', 'flolens' ) . '</a> ';
		}
		if ( $page < $pages ) {
			echo '<a class="button" href="' . esc_url( self::url( $page_slug, array_merge( $args, array( 'paged' => $page + 1 ) ) ) ) . '">' . esc_html__( 'Next', 'flolens' ) . ' &raquo;</a>';
		}
		echo '</div>';
	}

	public static function open( $title ) {
		echo '<div class="wrap uf-wrap">';
		\UserFlo\Brand::bar();
		echo '<h1 class="uf-title">' . esc_html( $title ) . '</h1>';
	}

	public static function close() {
		echo '</div>';
	}
}
