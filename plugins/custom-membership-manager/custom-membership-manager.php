<?php
/*
Plugin Name: Custom Membership Manager
Description: User registration with subscription-based permission control.
Version: 1.0
Author: Your Name
*/

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-cmm-init.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-cmm-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-cmm-roles.php';

register_activation_hook(__FILE__, ['CMM_Roles', 'add_roles']);
register_deactivation_hook(__FILE__, ['CMM_Roles', 'remove_roles']);

CMM_Init::init();
