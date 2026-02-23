<?php
/**
 * WordPress compatibility functions.
 * Emulates WordPress functions for testing purposes.
 *
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC
 * @version 3.1.15
 */
function is_wp_error($check)
{
    return is_a($check, 'WP_Error');
}

function wp_upload_dir()
{
    return [
        'path'  => 'C:\\temp\\phpunit\\uploads',
        'url'   => 'http://localhost/phpunit/uploads',
    ];
}

function wp_get_image_editor($path)
{
    return new WP_Image($id);
}

function apply_filters($key, $value)
{
    return $value;
}

function home_url($route = '')
{
    $url = 'http://localhost/phpunit';
    if (defined('ICL_LANGUAGE_CODE'))
        $url = ICL_LANGUAGE_CODE === 'en' ? 'http://dev.example.com' : 'http://dev.example.com/'.ICL_LANGUAGE_CODE;
    return $url.$route;
}

function get_home_path()
{
    return 'C:\\temp\\phpunit\\';
}

function add_action($key, $call) {
    global $hooks;
    $hooks['actions'][$key] = $call;
}

function add_filter($key, $call) {
    global $hooks;
    $hooks['filters'][$key] = $call;
}

function remove_action($key, $call) {
    global $hooks;
    if (array_key_exists($key, $hooks['actions']))
        unset($hooks['actions'][$key]);
    $hooks['removed'][$key] = $call;
}

function remove_filter($key, $call) {
    global $hooks;
    if (array_key_exists($key, $hooks['filters']))
        unset($hooks['filters'][$key]);
    $hooks['removed'][$key] = $call;
}

function add_shortcode($key, $call) {}

function do_action($key) {}

function register_widget($class) {}

function register_post_type($type, $args) {}

function register_taxonomy() {}

function add_meta_box() {}

function wp_nonce_field() {}

function wp_verify_nonce()
{
    return true;
}

function get_stylesheet_directory()
{
    return __DIR__;
}

function get_filesystem_method() {
    return 'direct';
}

function request_filesystem_credentials() {
    return true;
}

function WP_Filesystem() {
    return true;
}

function site_url()
{
    return 'http://localhost';
}

function submit_button()
{
    return '<button></button>';
}

function sanitize_text_field($value)
{
    return $value;
}

function sanitize_email($value)
{
    return $value;
}
function icl_object_id()
{
    return uniqid();
}
function wp_mkdir_p($path)
{
    return mkdir( $path, 0777, true );
}