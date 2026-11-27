<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Exclusions\Sql;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Visitors sent by an AI assistant: sessions whose referrer is an assistant's own site, or whose utm_source names one (ChatGPT tags the
 * links it gives people with utm_source=chatgpt.com). Counts only, a minimum: apps that strip the referrer, copied links, and Google's AI
 * answers (which arrive as ordinary Google traffic) cannot be told apart. The starter list below can be extended under Settings.
 */
final class AiReferrals {

	/** Assistant => array( domains that send visitors, utm_source values it uses ). A starter list: sites change, so it is editable. */
	const ASSISTANTS = array(
		'ChatGPT / OpenAI'    => array( array( 'chatgpt.com', 'chat.openai.com' ), array( 'chatgpt.com', 'chatgpt', 'openai' ) ),
		'Claude / Anthropic'  => array( array( 'claude.ai' ), array( 'claude.ai', 'claude' ) ),
		'Perplexity'          => array( array( 'perplexity.ai' ), array( 'perplexity.ai', 'perplexity' ) ),
		'Gemini / Google AI'  => array( array( 'gemini.google.com', 'bard.google.com' ), array( 'gemini' ) ),
		'Microsoft Copilot'   => array( array( 'copilot.microsoft.com', 'copilot.cloud.microsoft' ), array( 'copilot' ) ),
		'Grok / xAI'          => array( array( 'grok.com' ), array( 'grok.com' ) ),
		'DeepSeek'            => array( array( 'chat.deepseek.com' ), array( 'deepseek' ) ),
		'Mistral (Le Chat)'   => array( array( 'chat.mistral.ai' ), array() ),
		'Meta AI'             => array( array( 'meta.ai' ), array() ),
		'Qwen'                => array( array( 'chat.qwen.ai' ), array() ),
		'Kimi'                => array( array( 'kimi.com' ), array() ),
		'Poe'                 => array( array( 'poe.com' ), array() ),
		'You.com'             => array( array( 'you.com' ), array() ),
		'Phind'               => array( array( 'phind.com' ), array() ),
		'DuckDuckGo AI'       => array( array( 'duck.ai' ), array() ),
	);

	/** Domain => assistant for the built-in list plus the site owner's extra lines. */
	private static function domains() {
		$map = array();
		foreach ( self::ASSISTANTS as $name => $def ) {
			foreach ( $def[0] as $d ) {
				$map[ $d ] = $name;
			}
		}
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Settings::get( 'ai_extra_sources' ) ) as $line ) {
			$p = array_map( 'trim', explode( '=', $line, 2 ) );
			$d = strtolower( preg_replace( '#^(https?://)?(www\.)?#i', '', $p[0] ) );
			if ( '' !== $d ) {
				$map[ rtrim( $d, '/' ) ] = isset( $p[1] ) && '' !== $p[1] ? $p[1] : $d;
			}
		}
		return $map;
	}

	private static function utm_tokens() {
		$map = array();
		foreach ( self::ASSISTANTS as $name => $def ) {
			foreach ( $def[1] as $t ) {
				$map[ $t ] = $name;
			}
		}
		return $map;
	}

	/** The assistant behind a referrer origin ("https://chatgpt.com") and/or utm_source, or '' when there is none. */
	public static function classify( $referrer, $utm_source, array $domains = null, array $utm = null ) {
		$domains = null === $domains ? self::domains() : $domains;
		$utm     = null === $utm ? self::utm_tokens() : $utm;
		$host    = strtolower( (string) wp_parse_url( (string) $referrer, PHP_URL_HOST ) );
		$host    = preg_replace( '/^www\./', '', $host );
		if ( '' !== $host ) {
			foreach ( $domains as $d => $name ) {
				if ( $host === $d || ( strlen( $host ) > strlen( $d ) && '.' . $d === substr( $host, -strlen( $d ) - 1 ) ) ) {
					return $name;
				}
			}
		}
		$u = strtolower( trim( (string) $utm_source ) );
		if ( '' !== $u ) {
			if ( isset( $utm[ $u ] ) ) {
				return $utm[ $u ];
			}
			if ( isset( $domains[ $u ] ) ) {
				return $domains[ $u ];
			}
		}
		return '';
	}

	/**
	 * @param int $from Range start (unix, inclusive). @param int $to Range end (unix, exclusive).
	 * @return array{total:int,logged_in:int,assistants:array}
	 */
	public static function build( $from, $to ) {
		global $wpdb;
		$s    = Tables::sessions();
		$v    = Tables::visitors();
		$sql  = $wpdb->prepare( "SELECT SUBSTRING_INDEX(s.referrer, '/', 3) AS ref, LOWER(s.utm_source) AS src, COUNT(*) AS n, SUM(s.user_id IS NOT NULL OR v.user_id IS NOT NULL) AS li FROM {$s} s LEFT JOIN {$v} v ON v.id = s.visitor_id WHERE s.started_at >= %s AND s.started_at < %s AND (s.referrer <> '' OR s.utm_source <> '')" . Sql::urls( 's', 'entry_url' ) . ' GROUP BY ref, src LIMIT 5000', Clock::mysql( $from ), Clock::mysql( $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$domains = self::domains();
		$utm     = self::utm_tokens();
		$by      = array();
		$total   = 0;
		$li      = 0;
		foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$name = self::classify( $r['ref'], $r['src'], $domains, $utm );
			if ( '' === $name ) {
				continue;
			}
			$by[ $name ] = ( isset( $by[ $name ] ) ? $by[ $name ] : 0 ) + (int) $r['n'];
			$total      += (int) $r['n'];
			$li         += (int) $r['li'];
		}
		arsort( $by );
		$list = array();
		foreach ( $by as $name => $n ) {
			$list[] = array( 'assistant' => $name, 'sessions' => $n );
		}
		return array( 'total' => $total, 'logged_in' => $li, 'assistants' => $list );
	}
}
