<?php
namespace UserFlo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * A streaming export format. Each method returns the text to append to the output file, so a job can
 * be resumed after any batch. Register further formats (XLSX, ZIP bundles…) with the `userflo_export_formats` filter.
 */
interface WriterInterface {

	/** File extension without dot. */
	public static function extension();

	/** MIME type for downloads. */
	public static function mime();

	/** True when one file may contain several datasets (JSON); false for single-dataset formats (CSV). */
	public static function multi_dataset();

	public function begin( array $meta );

	/** @param string[] $columns */
	public function dataset_start( $name, array $columns, $first_dataset );

	public function row( array $row, $first_in_dataset );

	public function dataset_end();

	public function end();
}
