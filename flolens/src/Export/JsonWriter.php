<?php
namespace UserFlo\Export;

defined( 'ABSPATH' ) || exit;

/** One JSON document: {"meta":{…},"visitors":[…],"sessions":[…],…}. Identifiers are preserved so relationships can be rebuilt. */
final class JsonWriter implements WriterInterface {

	public static function extension() {
		return 'json';
	}
	public static function mime() {
		return 'application/json; charset=utf-8';
	}
	public static function multi_dataset() {
		return true;
	}
	public function begin( array $meta ) {
		return '{"meta":' . wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ',"relations":' . wp_json_encode( array(
			'sessions.visitor_id'  => 'visitors.id',
			'pageviews.session_id' => 'sessions.id',
			'pageviews.visitor_id' => 'visitors.id',
			'events.pageview_id'   => 'pageviews.id',
			'events.session_id'    => 'sessions.id',
			'events.visitor_id'    => 'visitors.id',
		) );
	}
	public function dataset_start( $name, array $columns, $first_dataset ) {
		return ',' . wp_json_encode( (string) $name ) . ':[';
	}
	public function row( array $row, $first_in_dataset ) {
		return ( $first_in_dataset ? '' : ',' ) . wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
	}
	public function dataset_end() {
		return ']';
	}
	public function end() {
		return '}';
	}
}
