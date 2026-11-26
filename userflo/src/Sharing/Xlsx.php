<?php
namespace UserFlo\Sharing;

defined( 'ABSPATH' ) || exit;

/** Minimal dependency-free .xlsx writer (needs PHP ZipArchive). Strings are written as inline strings; numbers stay numeric. */
final class Xlsx {

	public static function available() {
		return class_exists( '\ZipArchive' );
	}

	private static function esc( $s ) {
		return htmlspecialchars( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $s ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function col( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = (int) ( ( $i - 1 ) / 26 ) ) {
			$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	private static function sheet( array $rows ) {
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="42" customWidth="1"/><col min="2" max="8" width="20" customWidth="1"/></cols><sheetData>';
		foreach ( $rows as $r => $row ) {
			$x .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( $row ) as $c => $v ) {
				$ref = self::col( $c ) . ( $r + 1 );
				$st  = 0 === $r ? ' s="1"' : '';
				if ( is_int( $v ) || is_float( $v ) ) {
					$x .= '<c r="' . $ref . '"' . $st . '><v>' . $v . '</v></c>';
				} else {
					$x .= '<c r="' . $ref . '" t="inlineStr"' . $st . '><is><t xml:space="preserve">' . self::esc( Feed::cell( $v ) ) . '</t></is></c>';
				}
			}
			$x .= '</row>';
		}
		return $x . '</sheetData></worksheet>';
	}

	/**
	 * @param array  $sheets name => rows.
	 * @param string $path   Output file.
	 * @return bool
	 */
	public static function write( array $sheets, $path ) {
		if ( ! self::available() ) {
			return false;
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		$wb    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
		$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		$i     = 0;
		foreach ( $sheets as $name => $rows ) {
			++$i;
			$name   = \UserFlo\Core\Text::substr( preg_replace( '/[\[\]\:\*\?\/\\\\]/', ' ', (string) $name ), 31 );
			$types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$wb    .= '<sheet name="' . self::esc( $name ) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
			$rels  .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
			$zip->addFromString( 'xl/worksheets/sheet' . $i . '.xml', self::sheet( $rows ) );
		}
		$rels .= '<Relationship Id="rId' . ( $i + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
		$zip->addFromString( '[Content_Types].xml', $types . '</Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', $wb . '</sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>' );
		return $zip->close();
	}
}
