<?php

namespace WPMVC;

use Closure;
use WPMVC\Config;
use WPMVC\PHPFastCache\phpFastCache;
use WPMVC\Contracts\Cacheable;

/**
 * Cache class.
 *
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC
 * @version 3.1.7
 */
class Cache implements Cacheable
{
	/**
	 * Fast cache class engine.
	 */
	protected static $fastcache;

	/**
	 * Default constructor.
	 * @since 1.0.0
	 * @param array $config Config settings.
	 */
	public function __construct( Config $config )
	{
		if ( ! isset( self::$fastcache )
			&& $config->get( 'cache' )
		) {
			$cache = function_exists( 'apply_filters' )
				? apply_filters( 'wpmvc_cache_config', $config->get( 'cache' ) )
				: $config->get( 'cache' );
			// Create folder
			if ( array_key_exists( 'storage' , $cache )
				&& array_key_exists( 'path' , $cache )
				&& ( $cache['storage'] == 'auto'
					|| $cache['storage'] == 'files' )
				&& ! is_dir( $cache['path'] )
			) {
				if ( $config->get( 'mkdir' ) === 'mkdir' ) {
					mkdir( $cache['path'], 0777, true );
				} else {
					wp_mkdir_p( $cache['path'] );
				}
			}
			// Init cache
			phpFastCache::setup( $cache );
			self::$fastcache = phpFastCache();
			phpFastCache::$disabled = ! array_key_exists( 'enabled' , $cache ) || ! $cache['enabled'];
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
	 * Returns Cache instance.
	 * @since 1.0.0
	 * @return mixed.
	 */
	public static function instance()
	{
		return isset( self::$fastcache ) ? self::$fastcache : false;
	}

	/**
	 * Returns value stored in cache.
	 * @since 1.0.0
	 * 
	 * @param string $key     Cache key name.
	 * @param mixed  $default Defuault return value.
	 * 
	 * @return mixed
	 */
	public static function get( $key, $default = null )
	{
		$cache = self::instance();
		return $cache && $cache->isExisting( $key ) ? $cache->get( $key ) : $default;
	}

	/**
	 * Adds a value to cache.
	 * @since 1.0.0
	 * @param string $key     Main plugin object as reference.
	 * @param mixed  $value   Value to cache.
	 * @param int  	 $expires Expiration time in minutes.
	 */
	public static function add( $key, $value, $expires )
	{
		$cache = self::instance();
		if ( $cache && $value != null) {
			$cache->set( $key, $value, $expires * 60 );
		}
	}

	/**
	 * Returns flag if a given key has a value in cache or not.
	 * @since 1.0.0
	 * @param string $key Cache key name.
	 * @return bool
	 */
	public static function has( $key )
	{
		$cache = self::instance();
		if ( $cache ) {
			return $cache->isExisting( $key );
		}
		return false;
	}

	/**
	 * Returns the value of a given key.
	 * If it doesn't exist, then the value pass by is returned.
	 * @since 1.0.0
	 * @param string   $key     Main plugin object as reference.
	 * @param int  	   $expires Expiration time in minutes.
	 * @param callable $value   Callable that returns value to cache.
	 * @return mixed
	 */
	public static function remember( $key, $expires, $callable )
	{
		$cache = self::instance();
		if ( $cache ) {
			if ( $cache->isExisting( $key ) ) {
				return $cache->get( $key );
			} else if ( $callable !== null && is_callable( $callable ) ) {
				$value = call_user_func_array( $callable, [] );
				$cache->set( $key, $value, $expires * 60 );
				return $value;
			}
		}
		return $closure();
	}

	/**
	 * Removes a key / value from cache.
	 * @since 1.0.0
	 * @param string $key Cache key name.
	 */
	public static function forget( $key )
	{
		$cache = self::instance();
		if ( $cache ) {
			$cache->delete( $key );
		}
	}

	/**
	 * Flushes all cache keys and values.
	 * @since 1.0.0
	 */
	public static function flush()
	{
		$cache = self::instance();
		if ( $cache ) {
			$cache->clean();
		}
	}
}
