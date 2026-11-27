<?php
namespace UserFlo\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Names the bot behind a user agent: an AI crawler (training, search and assistant fetchers of AI companies), a search engine, or another
 * bot (SEO tools, monitors, link previews, scripts). A bot is recognised only by the name it gives itself, which anyone can fake; the
 * screens say so. A browser user agent returns null.
 */
final class Bots {

	const AI     = 'ai';
	const SEARCH = 'search';
	const OTHER  = 'other';

	/**
	 * AI crawlers: token in the user agent => array( name shown, assistant it belongs to, what it does ).
	 * What it does: training (collects pages to train models), search (builds an answer-search index), user (fetches a page because a person asked
	 * an assistant just now), or '' when the vendor does not say. The first match wins, so longer names come first. Names are the ones the
	 * vendors document; crawlers whose names are not documented are not guessed here, they can be added under Settings.
	 */
	const AI_BOTS = array(
		'OAI-SearchBot'                => array( 'OAI-SearchBot (OpenAI)', 'ChatGPT / OpenAI', 'search' ),
		'ChatGPT-User'                 => array( 'ChatGPT-User (OpenAI)', 'ChatGPT / OpenAI', 'user' ),
		'OAI-AdsBot'                   => array( 'OAI-AdsBot (OpenAI)', 'ChatGPT / OpenAI', '' ),
		'GPTBot'                       => array( 'GPTBot (OpenAI)', 'ChatGPT / OpenAI', 'training' ),
		'Claude-SearchBot'             => array( 'Claude-SearchBot (Anthropic)', 'Claude / Anthropic', 'search' ),
		'Claude-User'                  => array( 'Claude-User (Anthropic)', 'Claude / Anthropic', 'user' ),
		'Claude-Web'                   => array( 'Claude-Web (Anthropic)', 'Claude / Anthropic', '' ),
		'ClaudeBot'                    => array( 'ClaudeBot (Anthropic)', 'Claude / Anthropic', 'training' ),
		'anthropic-ai'                 => array( 'anthropic-ai (Anthropic)', 'Claude / Anthropic', 'training' ),
		'Perplexity-User'              => array( 'Perplexity-User', 'Perplexity', 'user' ),
		'PerplexityBot'                => array( 'PerplexityBot', 'Perplexity', 'search' ),
		'Google-CloudVertexBot'        => array( 'Google-CloudVertexBot', 'Gemini / Google AI', '' ),
		'Google-Extended'              => array( 'Google-Extended', 'Gemini / Google AI', 'training' ),
		'GoogleOther'                  => array( 'GoogleOther', 'Gemini / Google AI', '' ),
		'Applebot-Extended'            => array( 'Applebot-Extended (Apple)', 'Apple Intelligence', 'training' ),
		'meta-externalagent'           => array( 'Meta-ExternalAgent', 'Meta AI', 'training' ),
		'meta-externalfetcher'         => array( 'Meta-ExternalFetcher', 'Meta AI', 'user' ),
		'FacebookBot'                  => array( 'FacebookBot (Meta)', 'Meta AI', 'training' ),
		'Amazonbot'                    => array( 'Amazonbot', 'Amazon (Alexa, Nova)', '' ),
		'Bytespider'                   => array( 'Bytespider (ByteDance)', 'ByteDance (Doubao)', 'training' ),
		'MistralAI-User'               => array( 'MistralAI-User', 'Mistral (Le Chat)', 'user' ),
		'cohere-training-data-crawler' => array( 'cohere-training-data-crawler', 'Cohere', 'training' ),
		'cohere-ai'                    => array( 'cohere-ai', 'Cohere', '' ),
		'DuckAssistBot'                => array( 'DuckAssistBot', 'DuckDuckGo AI', 'user' ),
		'YouBot'                       => array( 'YouBot', 'You.com', 'search' ),
		'CCBot'                        => array( 'CCBot (Common Crawl)', 'Common Crawl (feeds many models)', 'training' ),
		'Diffbot'                      => array( 'Diffbot', 'Other AI crawlers', 'training' ),
		'Omgilibot'                    => array( 'Omgilibot', 'Other AI crawlers', 'training' ),
		'ImagesiftBot'                 => array( 'ImagesiftBot', 'Other AI crawlers', 'training' ),
		'PanguBot'                     => array( 'PanguBot', 'Other AI crawlers', 'training' ),
		'Timpibot'                     => array( 'Timpibot', 'Other AI crawlers', 'training' ),
		'Ai2Bot-Dolma'                 => array( 'AI2Bot-Dolma', 'Other AI crawlers', 'training' ),
		'AI2Bot'                       => array( 'AI2Bot', 'Other AI crawlers', 'training' ),
	);

	const SEARCH_BOTS = array(
		'Google-InspectionTool' => 'Googlebot', 'AdsBot-Google' => 'Google Ads bot', 'Mediapartners-Google' => 'Google AdSense bot', 'Googlebot' => 'Googlebot', 'bingbot' => 'Bingbot', 'BingPreview' => 'Bing preview',
		'DuckDuckBot' => 'DuckDuckBot', 'YandexBot' => 'YandexBot', 'Baiduspider' => 'Baiduspider', 'Applebot' => 'Applebot', 'Slurp' => 'Yahoo Slurp', 'SeznamBot' => 'SeznamBot', 'Qwantbot' => 'Qwantbot', 'MojeekBot' => 'MojeekBot',
	);

	const OTHER_BOTS = array(
		'AhrefsBot' => 'AhrefsBot', 'SemrushBot' => 'SemrushBot', 'MJ12bot' => 'MJ12bot', 'DotBot' => 'DotBot', 'BLEXBot' => 'BLEXBot', 'PetalBot' => 'PetalBot',
		'facebookexternalhit' => 'Facebook link preview', 'Twitterbot' => 'Twitterbot', 'LinkedInBot' => 'LinkedInBot', 'Slackbot' => 'Slackbot', 'WhatsApp' => 'WhatsApp preview', 'TelegramBot' => 'TelegramBot', 'Discordbot' => 'Discordbot',
		'UptimeRobot' => 'UptimeRobot', 'Pingdom' => 'Pingdom', 'GTmetrix' => 'GTmetrix', 'HeadlessChrome' => 'Headless Chrome', 'python-requests' => 'python-requests', 'Go-http-client' => 'Go-http-client', 'curl/' => 'curl', 'Wget' => 'Wget', 'okhttp' => 'okhttp',
	);

	/** Requests the site makes to itself (cron loopbacks, FloLens's own page scan) are not visits by anyone else. */
	private static function own( $ua ) {
		return 0 === strpos( $ua, 'WordPress/' ) || false !== stripos( $ua, 'FloLens' );
	}

	/** @return array{bot:string,kind:string}|null */
	public static function classify( $ua ) {
		$ua = (string) $ua;
		if ( '' === $ua ) {
			return array( 'bot' => 'No user agent', 'kind' => self::OTHER );
		}
		if ( self::own( $ua ) ) {
			return null;
		}
		foreach ( self::custom() as $token => $assistant ) { // Crawlers the site owner added under Settings.
			if ( false !== stripos( $ua, $token ) ) {
				return array( 'bot' => substr( $token, 0, 40 ), 'kind' => self::AI );
			}
		}
		foreach ( self::AI_BOTS as $token => $info ) {
			if ( false !== stripos( $ua, $token ) ) {
				return array( 'bot' => $info[0], 'kind' => self::AI );
			}
		}
		foreach ( array( self::SEARCH => self::SEARCH_BOTS, self::OTHER => self::OTHER_BOTS ) as $kind => $list ) {
			foreach ( $list as $token => $name ) {
				if ( false !== stripos( $ua, $token ) ) {
					return array( 'bot' => $name, 'kind' => $kind );
				}
			}
		}
		if ( UaParser::is_bot( $ua ) ) {
			return array( 'bot' => 'Other bots', 'kind' => self::OTHER );
		}
		return null;
	}

	/** Extra AI crawlers from Settings: token => assistant name. */
	public static function custom() {
		static $memo = array();
		$raw = (string) \UserFlo\Settings\Settings::get( 'ai_extra_bots' );
		if ( ! isset( $memo[ $raw ] ) ) {
			$map = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
				$p = array_map( 'trim', explode( '=', $line, 2 ) );
				if ( '' !== $p[0] ) {
					$map[ $p[0] ] = isset( $p[1] ) && '' !== $p[1] ? $p[1] : 'Other AI crawlers';
				}
			}
			$memo[ $raw ] = $map;
		}
		return $memo[ $raw ];
	}

	/**
	 * The assistant and purpose behind a counted bot name (as stored), for grouping.
	 *
	 * @return array{assistant:string,purpose:string}
	 */
	public static function detail( $name ) {
		foreach ( self::AI_BOTS as $info ) {
			if ( $info[0] === $name ) {
				return array( 'assistant' => $info[1], 'purpose' => $info[2] );
			}
		}
		foreach ( self::custom() as $token => $assistant ) {
			if ( substr( $token, 0, 40 ) === $name ) {
				return array( 'assistant' => $assistant, 'purpose' => '' );
			}
		}
		return array( 'assistant' => 'Other AI crawlers', 'purpose' => '' );
	}

	public static function kinds() {
		return array( self::AI, self::SEARCH, self::OTHER );
	}
}
