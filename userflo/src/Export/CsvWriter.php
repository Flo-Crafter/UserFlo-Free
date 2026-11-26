<?php
namespace UserFlo\Export;

defined( 'ABSPATH' ) || exit;

final class CsvWriter implements WriterInterface {

	public static function extension() {
		return 'csv';
	}
	public static function mime() {
		return 'text/csv; charset=utf-8';
	}
	public static function multi_dataset() {
		return false;
	}
	public function begin( array $meta ) {
		return '';
	}
	public function dataset_start( $name, array $columns, $first_dataset ) {
		return CsvExporter::line( $columns );
	}
	public function row( array $row, $first_in_dataset ) {
		return CsvExporter::line( $row );
	}
	public function dataset_end() {
		return '';
	}
	public function end() {
		return '';
	}
}
