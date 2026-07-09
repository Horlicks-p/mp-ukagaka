<?php
/**
 * Uninstall cleanup for MP Ukagaka.
 *
 * @package MP_Ukagaka
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mp_ukagaka' );
delete_option( 'mpu_last_diary_date' );
delete_option( 'mpu_last_diary_post_id' );
delete_option( 'moelog_bot_blocker_banned_ips' );
delete_option( 'moelog_bot_blocker_db_version' );

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'mpu_daily_stats_cleanup' );
	wp_clear_scheduled_hook( 'mpu_daily_diary_check' );
}

global $wpdb;

if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
				OR option_name LIKE %s
				OR option_name LIKE %s
				OR option_name LIKE %s",
			'_transient_mpu_%',
			'_transient_timeout_mpu_%',
			'_site_transient_mpu_%',
			'_site_transient_timeout_mpu_%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
				OR option_name LIKE %s
				OR option_name LIKE %s
				OR option_name LIKE %s",
			'_transient_moelog_bot_blocker_%',
			'_transient_timeout_moelog_bot_blocker_%',
			'_site_transient_moelog_bot_blocker_%',
			'_site_transient_timeout_moelog_bot_blocker_%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
				OR option_name LIKE %s",
			'mpu_stats_%',
			'mpu_chat_lock_%'
		)
	);

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}moelog_bot_blocker_logs" );
}
