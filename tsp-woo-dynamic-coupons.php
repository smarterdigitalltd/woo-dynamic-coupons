<?php

/**
 * Woo Dynamic Coupons
 *
 * Plugin Name:     Woo Dynamic Coupons
 * Plugin URI:      https://topspinpro.com
 * Description:     Create dynamic coupons and publish them via an API
 * Version:         1.0.0
 * Author:          Doug Belchamber
 * Author URI:      https://github.com/smarterdigitalltd
 * Text Domain:     'woo-dynamic-coupons'
 * Requires WP:     5.0.0
 * Requires PHP:    7.0
 * Tested up to:    7.4
 */

namespace YourPrefix\WooDynamicCoupons;

/**
 * If this file is called directly, abort.
 *
 * @since 1.0.0
 */
if ( ! defined( 'WPINC' ) ) {
	die( 'No entry' );
}

/**
 * Require dependencies
 *
 * @since 1.0.0
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Plugin Init
 *
 * @since 1.0.0
 */
add_action( 'plugins_loaded', function () {
	Plugin::getInstance();
} );

/**
 * Flush rewrite rules
 */
function flushRewriteRules(): void
{
	delete_option( 'rewrite_rules' );
	flush_rewrite_rules();
}

/**
 * Run actions when plugin is activated
 */
function registerHooks(): void
{
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\flushRewriteRules' );
	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\flushRewriteRules' );
	register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\flushRewriteRules' );
}

registerHooks();
