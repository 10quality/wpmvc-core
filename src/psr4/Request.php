<?php

namespace WPMVC;

/**
 * Request class.
 * Used to get web input from query string or WordPress' query vars.
 *
 * @link https://github.com/amostajo/lightweight-mvc/blob/v1.0/src/Request.php
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC
 * @version 3.1.11
 */
class Request
{
    /**
     * Gets input from either WordPress query vars or request's POST or GET.
     * @since 1.0.0
     *
     * @global object $wp_query WordPress query.
     *
     * @param string      $key      Name of the input.
     * @param mixed       $default  Default value if data is not found.
     * @param bool        $clear    Clears out source value.
     * @param bool|string $sanitize Sanitation callable or flag that indicates if auto-sanitation should be applied.
     *
     * @return mixed
     */
    public static function input( $key, $default = null, $clear = false, $sanitize = true )
    {
        global $wp_query;
        $value = null;
        // Check if it exists in wp_query
        if ( isset( $wp_query ) && isset( $wp_query->query_vars ) && array_key_exists( $key, $wp_query->query_vars ) ) {
            $value = $wp_query->query_vars[$key];
            if ( $clear ) unset( $wp_query->query_vars[$key] );
        } else if ( $_POST && array_key_exists( $key, $_POST ) ) {
            $value = $_POST[$key];
            if ( $clear ) unset( $_POST[$key] );
        } else if ( array_key_exists( $key, $_GET ) ) {
            $value = $_GET[$key];
            if ( $clear ) unset( $_GET[$key] );
        }
        return $value == null ? $default : self::sanitize( $value, $sanitize );
    }
    /**
     * Returns all variables stored in $_GET, $_POST and $wp_query->query_vars.
     * @since 3.1.11
     *
     * @global object $wp_query WordPress query.
     * 
     * @param bool|string $sanitize Sanitation callable or flag that indicates if auto-sanitation should be applied.
     * 
     * @return array
     */
    public static function all( $sanitize = true )
    {
        global $wp_query;
        $return = $_GET;
        if ( $_POST )
            $return = array_merge( $return, $_POST );
        if ( isset( $wp_query ) && isset( $wp_query->query_vars ) )
            $return = array_merge( $return, $wp_query->query_vars );
        return array_map( function( $value ) use( &$sanitize ) {
            return self::sanitize( $value, $sanitize );
        }, $return );
    }
    /**
     * Returns a sanitized value.
     * @since 3.1.11
     * 
     * @param mixed       $value    Value to sanitize.
     * @param bool|string $sanitize Sanitation callable or flag that indicates if auto-sanitation should be applied.
     * 
     * @return mixed
     */
    public static function sanitize( $value, $sanitize = true )
    {
        if ( $sanitize === false )
            return $value;
        if ( ! is_array( $value ) ) $value = trim( $value );
        if ( is_array( $value ) ) {
            if ( $sanitize === true || ! is_callable( $sanitize ) )
                $sanitize = apply_filters( 'wpmvc_request_sanitize_array', 'WPMVC\Request::sanitize_array' );
            return call_user_func_array( $sanitize, [$value] );
        } elseif ( is_numeric( $value ) ) {
            if ( strpos( $value, '.' ) !== false ) {
                if ( $sanitize === true || ! is_callable( $sanitize ) )
                    $sanitize = apply_filters( 'wpmvc_request_sanitize_float', 'floatval' );
                return call_user_func_array( $sanitize, [$value] );
            } else {
                if ( $sanitize === true || ! is_callable( $sanitize ) )
                    $sanitize = apply_filters( 'wpmvc_request_sanitize_int', 'intval' );
                return call_user_func_array( $sanitize, [$value] );
            }
        } elseif ( strtolower( $value ) === 'true' || strtolower( $value ) === 'false' ) {
            return strtolower( $value ) === 'true';
        } elseif ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
            if ( $sanitize === true || ! is_callable( $sanitize ) )
                $sanitize = apply_filters( 'wpmvc_request_sanitize_email', 'sanitize_email' );
            return call_user_func_array( $sanitize, [$value] );
        } else {
            if ( $sanitize === true || ! is_callable( $sanitize ) )
                $sanitize = apply_filters( 'wpmvc_request_sanitize_string', 'sanitize_text_field' );
            return call_user_func_array( $sanitize, [$value] );
        }
    }
    /**
     * Returns sanitized array.
     * @since 3.1.11
     * 
     * @param array $value
     * 
     * @return array
     */
    public static function sanitize_array( $value )
    {
        foreach ( $value as $key => $sub ) {
            $value[$key] = self::sanitize( $sub );
        }
        return $value;
    }
     /**
     * Validate Request
     * @param array $items
     */
    public static function validate($items)
    {
        $response = new \WPMVC\Response;

        foreach ($items as $item => $rules) {

            //Check for Rules
            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            $value = self::input($item);

            foreach ($rules as $rule) {
                $itemCaps = ucwords(str_replace("_", " ", $item));

                switch ($rule) {
                    case 'required':
                        if (empty($value)) {
                            $response->error($item, "$itemCaps is required");
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $response->error($item, "$itemCaps must be a number number");
                        }
                        break;
                }
            }
        }

        if ($response->passes) {
            return true;
        }

        $response->json();
    }
    
    
}
