<?php

namespace WPMVC;

use WPMVC\KLogger\Logger;
use WPMVC\Config;
use WPMVC\Contracts\Loggable;

/**
 * Log class.
 *
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC
 * @version 3.1.8
 */
class Log implements Loggable
{
    /**
     * Log path.
     * @since 1.0.1
     */
    protected static $path;

    /**
     * Log driver.
     * @since 1.0.0
     */
    protected static $logger;

    /**
     * Default constructor.
     * @since 1.0.0
     * 
     * @param array $config Config settings.
     */
    public function __construct( Config $config )
    {
        if ( ! isset( self::$logger ) ) {
            $path = function_exists( 'apply_filters' )
                ? apply_filters( 'wpmvc_log_path_config', $config->get( 'paths.log' ) )
                : $config->get( 'paths.log' );
            // Create folder
            if ( ! is_dir( $path ) ) {
                mkdir( $path, 0777, true );
            }
            // Init logger
            self::$path = $path;
        }
    }

    /**
     * Static constructor.
     * @since 1.0.0
     * @param array $config Config settings.
     */
    public static function init( Config $config )
    {
        new self( $config );
    }

    /**
     * Returns Logger instance.
     * @since 1.0.0
     * @return mixed.
     */
    public static function instance()
    {
        if ( ! isset( self::$logger ) ) {
            self::$logger = new Logger( self::$path );
        }
        return self::$logger;
    }

    /**
     * Prints message information in log.
     * @since 1.0.0
     * @param string $message Message information to display in log.
     */
    public static function info( $message )
    {
        $logger = self::instance();
        if ( defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && !empty( WP_TESTS_CONFIG_FILE_PATH ) ) {
            if ( function_exists( '_wpmvc_log_info' ) )
                _wpmvc_test_log_info( $message );
        } elseif ( $logger ) {
            $logger->info( $message );
        }
    }

    /**
     * Debugs / prints value in log.
     * @since 1.0.0
     * @param mixed $message Message to debug.
     * @param array $values  Value(s) to debug.
     */
    public static function debug( $message, $values = null )
    {
        $logger = self::instance();
        if ( defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && !empty( WP_TESTS_CONFIG_FILE_PATH ) ) {
            if ( function_exists( '_wpmvc_test_log' ) )
                _wpmvc_test_log( 'debug', $message, $values );
        } elseif ( $logger ) {
            $logger->debug(
                is_string( $message ) ? $message : 'value',
                $values === null && ! is_string( $message )
                    ? ( is_array( $message ) ? $message : [ $message ] )
                    : ( is_array( $values )
                        ? $values
                        : ( is_object( $values )
                            ? (array)$values
                            : [ $values ]
                        )
                    )
            );
        }
    }

    /**
     * Prints error log.
     * @since 1.0.0
     * @param mixed $e Exception / error.
     */
    public static function error( $e )
    {
        $logger = self::instance();
        if ( defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && !empty( WP_TESTS_CONFIG_FILE_PATH ) ) {
            if ( function_exists( '_wpmvc_test_log' ) )
                _wpmvc_test_log( 'error', $e );
        } elseif ( $logger ) {
            $logger->error( $e );
        }
    }
}
