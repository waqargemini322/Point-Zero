<?php
class CMM_Init {
    public static function init() {
        add_action('init', [__CLASS__, 'register_scripts']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function register_scripts() {
        wp_register_style('cmm-style', plugins_url('../assets/style.css', __FILE__));
    }

    public static function enqueue_scripts() {
        wp_enqueue_style('cmm-style');
    }
}
