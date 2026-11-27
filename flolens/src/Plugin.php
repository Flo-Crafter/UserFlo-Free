<?php
namespace UserFlo;

use UserFlo\Admin\Menu;
use UserFlo\Database\Migrator;
use UserFlo\Identity\AuthHooks;
use UserFlo\Privacy\PersonalData;
use UserFlo\Privacy\Retention;
use UserFlo\Rest\Routes;
use UserFlo\Tracking\Collector;
use UserFlo\Tracking\Enqueue;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the modules together. Contains no business logic.
 */
final class Plugin {

	public static function boot() {
		// Multisite: never reuse one site's cached settings after switch_to_blog().
		add_action( 'switch_blog', array( '\\UserFlo\\Settings\\Settings', 'reset_cache' ) );
		Migrator::maybe_upgrade();
		Retention::register();
		\UserFlo\Insights\Summary::register();
		\UserFlo\Insights\UtmBackfill::register();
		\UserFlo\Commerce\Capture::register();
		add_action( 'update_option_' . \UserFlo\Settings\Settings::OPTION, array( \UserFlo\Modules::class, 'on_settings_saved' ), 10, 2 );
		\UserFlo\I18n::register();
		\UserFlo\Translations\Store::register();
		\UserFlo\Translations\Routes::register();
		\UserFlo\Help\Routes::register();
		\UserFlo\Geo\Database::register();
		PersonalData::register();
		\UserFlo\Export\Jobs::register();
		Enqueue::register();
		\UserFlo\Admin\HistoryBar::register();
		\UserFlo\Tracking\BotCounter::register();
		\UserFlo\Tracking\FormShortcode::register();
		Collector::register();
		add_action( 'userflo_module_toggled', array( '\\UserFlo\\Audit\\Scanner', 'module_toggled' ), 10, 2 );
		add_filter( 'userflo_exclusions_audit_available', array( '\\UserFlo\\Audit\\Findings', 'available' ) );
		if ( \UserFlo\Modules::on( 'audit' ) ) {
			\UserFlo\Audit\Scanner::register();
			if ( ! wp_next_scheduled( \UserFlo\Audit\Scanner::TICK ) ) {
				\UserFlo\Audit\Scanner::sync(); // First run on this site, or the schedule was lost.
			}
		}
		if ( \UserFlo\Modules::on( 'sharing' ) ) {
			\UserFlo\Sharing\Frontend::register(); // Share pages and feeds exist only while the module is on; an expired or disabled link falls through to the normal 404.
		}
		\UserFlo\Frontend\Shortcodes::register();
		Routes::register();
		AuthHooks::register();
		/**
		 * Everything FloLens Lite needs is wired. Add-ons (FloLens Pro) attach their own hooks here and never replace a Lite class.
		 * Fires on plugins_loaded, so an add-on that registers later than this still works through its own hooks.
		 */
		do_action( 'userflo_loaded' );
		if ( is_admin() ) {
			Menu::register();
			\UserFlo\Admin\SplitNotice::register();
			if ( \UserFlo\Modules::on( 'sharing' ) ) {
				\UserFlo\Admin\SharingPage::register();
			}
		}
	}

	public static function activate() {
		Migrator::run();
		\UserFlo\Settings\Settings::ensure_defaults();
		Retention::schedule();
		if ( ! \UserFlo\Admin\SetupWizard::is_done() ) {
			set_transient( \UserFlo\Admin\SetupWizard::REDIRECT, 1, 60 ); // First activation: open the setup wizard.
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( Retention::HOOK );
		wp_clear_scheduled_hook( Retention::HOOK . '_more' );
		wp_clear_scheduled_hook( \UserFlo\Export\Jobs::CRON );
		wp_clear_scheduled_hook( \UserFlo\Geo\Database::CRON );
		wp_unschedule_hook( \UserFlo\Audit\Scanner::TICK );
	}

	/** Capability required to view analytics (filterable). */
	public static function cap() {
		return apply_filters( 'userflo_view_capability', 'manage_options' );
	}
}
