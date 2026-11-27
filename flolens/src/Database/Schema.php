<?php
namespace UserFlo\Database;

defined( 'ABSPATH' ) || exit;

/** CREATE TABLE statements for dbDelta (schema version 20). */
final class Schema {

	public static function sql() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$v = Tables::visitors();
		$s = Tables::sessions();
		$p = Tables::pageviews();
		$e = Tables::events();
		$h = Tables::heat();
		$g = Tables::goals();
		$gh = Tables::goal_hits();
		$o = Tables::orders();
		$x = Tables::exclusions();
		$ap = Tables::audit_pages();
		$ae = Tables::audit_elements();
		$al = Tables::audit_locations();
		$af = Tables::audit_findings();
		$ar = Tables::alert_rules();
		$as = Tables::alert_state();
		$ad = Tables::alert_deliveries();
		$db = Tables::dashboards();
		$ai = Tables::ai_log();
		$bt = Tables::bots();
		$sm = Tables::summary();

		return array(
			"CREATE TABLE {$v} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  visitor_uid char(32) NOT NULL,
  user_id bigint(20) unsigned DEFAULT NULL,
  first_seen datetime NOT NULL,
  last_seen datetime NOT NULL,
  session_count int(10) unsigned NOT NULL DEFAULT 0,
  pageview_count int(10) unsigned NOT NULL DEFAULT 0,
  event_count int(10) unsigned NOT NULL DEFAULT 0,
  engaged_seconds int(10) unsigned NOT NULL DEFAULT 0,
  first_referrer varchar(500) NOT NULL DEFAULT '',
  first_landing varchar(500) NOT NULL DEFAULT '',
  device varchar(20) NOT NULL DEFAULT '',
  browser varchar(40) NOT NULL DEFAULT '',
  os varchar(40) NOT NULL DEFAULT '',
  country char(2) DEFAULT NULL,
  ip varchar(64) NOT NULL DEFAULT '',
  city varchar(80) NOT NULL DEFAULT '',
  identified_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY visitor_uid (visitor_uid),
  KEY user_id (user_id),
  KEY last_seen (last_seen)
) {$c};",
			"CREATE TABLE {$s} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_uid char(32) NOT NULL,
  visitor_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned DEFAULT NULL,
  started_at datetime NOT NULL,
  last_activity_at datetime NOT NULL,
  login_at datetime DEFAULT NULL,
  logout_at datetime DEFAULT NULL,
  pageview_count int(10) unsigned NOT NULL DEFAULT 0,
  event_count int(10) unsigned NOT NULL DEFAULT 0,
  engaged_seconds int(10) unsigned NOT NULL DEFAULT 0,
  entry_url varchar(500) NOT NULL DEFAULT '',
  exit_url varchar(500) NOT NULL DEFAULT '',
  referrer varchar(500) NOT NULL DEFAULT '',
  device varchar(20) NOT NULL DEFAULT '',
  browser varchar(40) NOT NULL DEFAULT '',
  os varchar(40) NOT NULL DEFAULT '',
  screen_w smallint(5) unsigned NOT NULL DEFAULT 0,
  screen_h smallint(5) unsigned NOT NULL DEFAULT 0,
  ip varchar(64) NOT NULL DEFAULT '',
  country char(2) DEFAULT NULL,
  city varchar(80) NOT NULL DEFAULT '',
  lat decimal(6,3) DEFAULT NULL,
  lon decimal(7,3) DEFAULT NULL,
  utm_source varchar(100) NOT NULL DEFAULT '',
  utm_medium varchar(100) NOT NULL DEFAULT '',
  utm_campaign varchar(150) NOT NULL DEFAULT '',
  utm_term varchar(150) NOT NULL DEFAULT '',
  utm_content varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY session_uid (session_uid),
  KEY visitor_started (visitor_id,started_at),
  KEY last_activity_at (last_activity_at),
  KEY user_activity (user_id,last_activity_at),
  KEY started_ref (started_at,referrer),
  KEY started_country (started_at,country),
  KEY started_campaign (started_at,utm_campaign(40))
) {$c};",
			"CREATE TABLE {$p} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  pv_uid char(16) NOT NULL,
  session_id bigint(20) unsigned NOT NULL,
  visitor_id bigint(20) unsigned NOT NULL,
  viewed_at datetime NOT NULL,
  url varchar(500) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  referrer varchar(500) NOT NULL DEFAULT '',
  engaged_seconds int(10) unsigned NOT NULL DEFAULT 0,
  duration_seconds int(10) unsigned NOT NULL DEFAULT 0,
  max_scroll tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY pv_uid (pv_uid),
  KEY session_viewed (session_id,viewed_at),
  KEY visitor_viewed (visitor_id,viewed_at),
  KEY viewed_url (viewed_at,url),
  KEY url_viewed (url(191),viewed_at)
) {$c};",
			"CREATE TABLE {$e} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  dedupe_key char(32) NOT NULL,
  pageview_id bigint(20) unsigned NOT NULL DEFAULT 0,
  session_id bigint(20) unsigned NOT NULL,
  visitor_id bigint(20) unsigned NOT NULL,
  occurred_at datetime NOT NULL,
  type varchar(20) NOT NULL,
  name varchar(100) NOT NULL DEFAULT '',
  target varchar(255) NOT NULL DEFAULT '',
  value varchar(500) NOT NULL DEFAULT '',
  component varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY dedupe_key (dedupe_key),
  KEY session_time (session_id,occurred_at),
  KEY visitor_time (visitor_id,occurred_at),
  KEY type_time (type,occurred_at),
  KEY type_name_visitor (type,name,visitor_id),
  KEY pageview_id (pageview_id),
  KEY occurred_at (occurred_at)
) {$c};",
			// Heatmap aggregates: counters only, no visitor, session or IP data.
			"CREATE TABLE {$h} (
  day date NOT NULL,
  url_hash char(32) NOT NULL,
  url varchar(500) NOT NULL DEFAULT '',
  device varchar(10) NOT NULL,
  x smallint(5) unsigned NOT NULL,
  y smallint(5) unsigned NOT NULL,
  clicks int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,url_hash,device,x,y),
  KEY url_day (url_hash,day)
) {$c};",
			// Goals: what counts as an outcome. type page|event|funnel; match_type exact|prefix|contains; for a funnel `pattern` holds the step goal ids as JSON.
			// min_count: matches needed in one session (1 = once). Pro: within_min (must happen this soon after the session began), starts_on/ends_on
			// (time frame), and measure = target (count over a weekday/time window and compare with a threshold: compare more|atleast|less|atmost).
			"CREATE TABLE {$g} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL DEFAULT '',
  type varchar(10) NOT NULL DEFAULT 'page',
  match_type varchar(10) NOT NULL DEFAULT 'exact',
  pattern varchar(300) NOT NULL DEFAULT '',
  event_type varchar(20) NOT NULL DEFAULT '',
  value decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(10) NOT NULL DEFAULT 'active',
  min_count smallint(5) unsigned NOT NULL DEFAULT 1,
  measure varchar(8) NOT NULL DEFAULT 'session',
  within_min int(10) unsigned NOT NULL DEFAULT 0,
  starts_on date DEFAULT NULL,
  ends_on date DEFAULT NULL,
  days varchar(20) NOT NULL DEFAULT '',
  time_from varchar(5) NOT NULL DEFAULT '',
  time_to varchar(5) NOT NULL DEFAULT '',
  compare varchar(8) NOT NULL DEFAULT '',
  threshold int(10) unsigned NOT NULL DEFAULT 0,
  count_what varchar(10) NOT NULL DEFAULT 'hits',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status (status)
) {$c};",
			// A session completes a goal at most once: the primary key makes the insert idempotent. No visitor data beyond internal ids.
			"CREATE TABLE {$gh} (
  goal_id bigint(20) unsigned NOT NULL,
  session_id bigint(20) unsigned NOT NULL,
  visitor_id bigint(20) unsigned NOT NULL,
  hit_at datetime NOT NULL,
  PRIMARY KEY  (goal_id,session_id),
  KEY session_id (session_id),
  KEY visitor_id (visitor_id),
  KEY hit_at (hit_at)
) {$c};",
			// WooCommerce orders as numbers only. WooCommerce stays the source of truth; this exists so reports are fast and a sale can be tied to the
			// visit (session_uid) that was active at checkout. No names, e-mail addresses, addresses or customer ids are ever stored here.
			"CREATE TABLE {$o} (
  order_id bigint(20) unsigned NOT NULL,
  created_gmt datetime NOT NULL,
  status varchar(30) NOT NULL DEFAULT '',
  currency char(3) NOT NULL DEFAULT '',
  total decimal(14,2) NOT NULL DEFAULT 0,
  tax decimal(14,2) NOT NULL DEFAULT 0,
  shipping decimal(14,2) NOT NULL DEFAULT 0,
  discount decimal(14,2) NOT NULL DEFAULT 0,
  refunded decimal(14,2) NOT NULL DEFAULT 0,
  tax_refunded decimal(14,2) NOT NULL DEFAULT 0,
  shipping_refunded decimal(14,2) NOT NULL DEFAULT 0,
  session_uid char(32) DEFAULT NULL,
  updated_gmt datetime NOT NULL,
  PRIMARY KEY  (order_id),
  KEY created_status (created_gmt,status),
  KEY session_uid (session_uid)
) {$c};",
			// Exclusion rules. mode stop|stats|audit; target page|element|finding; field says which recorded value is compared (url, target, name, value,
			// component, or css for a selector the browser applies); match exact|prefix|contains. page_scope limits a rule to pages under a path.
			"CREATE TABLE {$x} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  mode varchar(8) NOT NULL DEFAULT 'stats',
  target varchar(8) NOT NULL DEFAULT 'element',
  field varchar(10) NOT NULL DEFAULT 'url',
  match_type varchar(10) NOT NULL DEFAULT 'exact',
  pattern varchar(300) NOT NULL DEFAULT '',
  event_type varchar(20) NOT NULL DEFAULT '',
  page_scope varchar(300) NOT NULL DEFAULT '',
  finding_type varchar(40) NOT NULL DEFAULT '',
  label varchar(200) NOT NULL DEFAULT '',
  note varchar(300) NOT NULL DEFAULT '',
  status varchar(10) NOT NULL DEFAULT 'active',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY mode_status (mode,status)
) {$c};",
			// Website audit. One scanner fills these: pages (what was fetched and when), elements (one row per reusable element, identified like the
			// tracker identifies it), locations (which page an element is on, how many times) and findings (page-level when element_id is 0).
			"CREATE TABLE {$ap} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  url_hash char(32) NOT NULL,
  url varchar(500) NOT NULL DEFAULT '',
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  http_status smallint(5) unsigned NOT NULL DEFAULT 0,
  final_url varchar(500) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  description varchar(500) NOT NULL DEFAULT '',
  canonical varchar(500) NOT NULL DEFAULT '',
  robots varchar(100) NOT NULL DEFAULT '',
  content_hash char(32) NOT NULL DEFAULT '',
  element_count int(10) unsigned NOT NULL DEFAULT 0,
  metrics text,
  state varchar(10) NOT NULL DEFAULT 'queued',
  error varchar(120) NOT NULL DEFAULT '',
  failures tinyint(3) unsigned NOT NULL DEFAULT 0,
  first_seen datetime NOT NULL,
  scanned_at datetime DEFAULT NULL,
  next_scan_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY url_hash (url_hash),
  KEY next_scan (next_scan_at),
  KEY state (state)
) {$c};",
			"CREATE TABLE {$ae} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ekey char(32) NOT NULL,
  kind varchar(10) NOT NULL DEFAULT 'control',
  label varchar(200) NOT NULL DEFAULT '',
  identifier varchar(255) NOT NULL DEFAULT '',
  value_sig varchar(500) NOT NULL DEFAULT '',
  href varchar(500) NOT NULL DEFAULT '',
  pages int(10) unsigned NOT NULL DEFAULT 0,
  instances int(10) unsigned NOT NULL DEFAULT 0,
  first_seen datetime NOT NULL,
  last_seen datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ekey (ekey),
  KEY kind (kind),
  KEY identifier (identifier(191))
) {$c};",
			"CREATE TABLE {$al} (
  element_id bigint(20) unsigned NOT NULL,
  page_id bigint(20) unsigned NOT NULL,
  instances smallint(5) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY  (element_id,page_id),
  KEY page_id (page_id)
) {$c};",
			"CREATE TABLE {$af} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  element_id bigint(20) unsigned NOT NULL DEFAULT 0,
  code varchar(40) NOT NULL,
  class varchar(14) NOT NULL DEFAULT 'verified',
  severity tinyint(3) unsigned NOT NULL DEFAULT 1,
  evidence varchar(300) NOT NULL DEFAULT '',
  first_seen datetime NOT NULL,
  last_seen datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY page_element_code (page_id,element_id,code),
  KEY code (code),
  KEY element_id (element_id)
) {$c};",
			"CREATE TABLE {$ai} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  at datetime NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  label varchar(80) NOT NULL DEFAULT '',
  kind varchar(24) NOT NULL DEFAULT '',
  pack varchar(40) NOT NULL DEFAULT '',
  days smallint(5) unsigned NOT NULL DEFAULT 0,
  status smallint(5) unsigned NOT NULL DEFAULT 200,
  bytes int(10) unsigned NOT NULL DEFAULT 0,
  agent varchar(80) NOT NULL DEFAULT '',
  preview varchar(600) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY at (at),
  KEY user_id (user_id)
) {$c};",
			"CREATE TABLE {$ar} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  kind varchar(10) NOT NULL,
  metric varchar(12) NOT NULL DEFAULT '',
  params text NOT NULL,
  channels text NOT NULL,
  cooldown_s int(10) unsigned NOT NULL DEFAULT 0,
  status varchar(8) NOT NULL DEFAULT 'active',
  last_fired_at datetime DEFAULT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_kind (status,kind)
) {$c};",
			"CREATE TABLE {$as} (
  rule_id bigint(20) unsigned NOT NULL,
  counter bigint(20) unsigned NOT NULL DEFAULT 0,
  baseline bigint(20) unsigned NOT NULL DEFAULT 0,
  next_milestone bigint(20) unsigned NOT NULL DEFAULT 0,
  last_eval_day date DEFAULT NULL,
  breached tinyint(1) unsigned NOT NULL DEFAULT 0,
  note varchar(40) NOT NULL DEFAULT '',
  updated_at datetime NOT NULL,
  PRIMARY KEY  (rule_id)
) {$c};",
			"CREATE TABLE {$ad} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  rule_id bigint(20) unsigned NOT NULL,
  channel_id varchar(16) NOT NULL,
  dedupe_key varchar(64) NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'queued',
  attempt tinyint(3) unsigned NOT NULL DEFAULT 0,
  next_try_at datetime DEFAULT NULL,
  claimed_at datetime DEFAULT NULL,
  http_status smallint(5) unsigned NOT NULL DEFAULT 0,
  last_error varchar(60) NOT NULL DEFAULT '',
  payload text NOT NULL,
  created_at datetime NOT NULL,
  sent_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY rule_key_channel (rule_id,dedupe_key,channel_id),
  KEY status_next (status,next_try_at),
  KEY rule_created (rule_id,created_at),
  KEY created_at (created_at)
) {$c};",
			"CREATE TABLE {$db} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uid char(12) NOT NULL,
  name varchar(80) NOT NULL,
  description varchar(255) NOT NULL DEFAULT '',
  owner bigint(20) unsigned NOT NULL DEFAULT 0,
  is_default tinyint(1) unsigned NOT NULL DEFAULT 0,
  config longtext NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uid (uid),
  KEY is_default (is_default)
) {$c};",
			// Bot counters: requests by AI crawlers, search engines and other bots per day and page. Counters only.
			"CREATE TABLE {$bt} (
  day date NOT NULL,
  bot varchar(40) NOT NULL,
  kind varchar(8) NOT NULL,
  url_hash char(32) NOT NULL,
  url varchar(500) NOT NULL DEFAULT '',
  hits int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,bot,url_hash),
  KEY kind_day (kind,day),
  KEY url_day (url_hash,day)
) {$c};",
			// Daily summaries kept after the raw rows are deleted: one row per local day for the totals (dim 'total', written last: it marks the day
			// as complete) and a few top rows per dimension (page, source, country, device, channel), goal completions (goal; amount = value), sales
			// per currency (order; amount = revenue), interactions per type (event) and custom events by name (custom). Counts and sums only.
			"CREATE TABLE {$sm} (
  day date NOT NULL,
  dim varchar(10) NOT NULL,
  k_hash char(32) NOT NULL,
  k varchar(300) NOT NULL DEFAULT '',
  sessions int(10) unsigned NOT NULL DEFAULT 0,
  pageviews int(10) unsigned NOT NULL DEFAULT 0,
  visitors int(10) unsigned NOT NULL DEFAULT 0,
  new_visitors int(10) unsigned NOT NULL DEFAULT 0,
  logged_in int(10) unsigned NOT NULL DEFAULT 0,
  engaged int(10) unsigned NOT NULL DEFAULT 0,
  single int(10) unsigned NOT NULL DEFAULT 0,
  amount decimal(14,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,dim,k_hash),
  KEY dim_day (dim,day)
) {$c};",
		);
	}
}
