<?php
/*
Plugin Name: Product Roadmap Manager
Description: Standalone admin task management plugin for roadmap planning, technical cleanup, and follow-ups.
Version: 1.0.2
Author: Josh Chalmers
License: GPL2
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit;
}

define('PRM_PLUGIN_FILE', __FILE__);
define('PRM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PRM_PLUGIN_VERSION', '1.0.2');

require_once PRM_PLUGIN_PATH . 'includes/class-prm-plugin.php';

PRM_Plugin::init();
