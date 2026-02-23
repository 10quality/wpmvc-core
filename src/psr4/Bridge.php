<?php

namespace WPMVC;

use WPMVC\Cache;
use WPMVC\Log;
use WPMVC\Resolver;
use WPMVC\Contracts\Plugable;
use WPMVC\MVC\Engine;
use TenQuality\WP\File;
use Exception;

/**
 * Plugin class.
 * To be extended as main plugin / theme class.
 * Part of the core library of WordPress Plugin / WordPress Theme.
 *
 * @link https://github.com/amostajo/wordpress-plugin-core/blob/v1.0/src/psr4/Plugin.php
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC
 * @version 3.1.15
 */
abstract class Bridge implements Plugable
{
    /**
     * Main configuration file.
     * @since 1.0.0
     * @var \WPMVC\Config
     */
    protected $config;

    /**
     * MVC engine.
     * @since 1.0.0
     * @var \WPMVC\MVC\Engine
     */
    protected $mvc;

    /**
     * Add ons.
     * @since 1.0.2
     * @var array
     */
    protected $addons;

    /**
     * List of WordPress action hooks to add.
     * @since 1.0.3
     * @var array
     */
    protected $actions;

    /**
     * List of WordPress filter hooks to add.
     * @since 1.0.3
     * @var array
     */
    protected $filters;

    /**
     * List of WordPress shortcodes to add.
     * @since 1.0.3
     * @var array
     */
    protected $shortcodes;

    /**
     * List of WordPress widgets to add.
     * @since 1.0.3
     * @var array
     */
    protected $widgets;

    /**
     * List of Models (post_type) to add/register.
     * @since 2.0.4
     * @var array
     */
    protected $models;

    /**
     * List of assets to register or enqueue.
     * @since 2.0.7
     * @var array
     */
    protected $assets;

    /**
     * List of Models that requires bridge processing to function.
     * @since 2.0.4
     * @var array
     */
    protected $_automatedModels;

    /**
     * List of additional configuration files loaded per request.
     * @since 3.1.11
     * @var array
     */
    protected $_configs;

    /**
     * Main constructor
     * @since 1.0.0
     *
     * @param array $config Configuration options.
     */
    public function __construct( Config $config )
    {
        $this->actions = [];
        $this->filters = [];
        $this->shortcodes = [];
        $this->widgets = [];
        $this->models = [];
        $this->assets = [];
        $this->_automatedModels = [];
        $this->_configs = [];
        $this->config = $config;
        $this->mvc = new Engine(
            $this->config->get( 'paths.views' ),
            $this->config->get( 'paths.controllers' ),
            $this->config->get( 'namespace' ),
            $this->config->get( 'paths.theme_path' )
        );
        $this->addons = [];
        // Init
        add_action(
            $this->config->get( 'type' ) === 'theme' ? 'after_setup_theme' : 'plugins_loaded',
            [ &$this, '_init' ],
            1
        );
        // Resolver
        Resolver::add(
            $this->config->get( 'type' ) === 'theme' ? 'theme' : $this->config->get( 'namespace' ),
            $this
        );
    }

    /**
     * Inits framework.
     * @since 3.1.0
     */
    public function _init()
    {
        Cache::init( $this->config );
        Log::init( $this->config );
        $this->set_addons();
        $this->_check_assets();
        $this->_localize();
        // Hooks
        $this->autoload_init();
        if ( is_admin() )
            $this->autoload_on_admin();
        $this->add_hooks();
    }

    /**
     * Returns READ-ONLY properties.
     * @since 1.0.2
     *
     * @param string $property Property name.
     *
     * @return mixed
     */
    public function __get( $property )
    {
        if ( property_exists( $this, $property ) ) {
            switch ( $property ) {
                case 'config':
                    return $this->$property;
            }
        }
        return null;
    }

    /**
     * Calls to class or addon method.
     * Checks "addon_" prefix to search for addon methods.
     * @since 1.0.2
     *
     * @return mixed
     */
    public function __call( $method, $args )
    {
        if ( preg_match( '/addon\_/', $method ) ) {
            $method = preg_replace( '/addon\_/', '', $method );
            // Search addons
            for ( $i = count( $this->addons ) - 1; $i >= 0; --$i ) {
                if ( method_exists( $this->addons[$i], $method ) ) {
                    return call_user_func_array( [ $this->addons[$i], $method ], $args );
                }
            }
        } else if ( preg_match( '/^\_c\_/', $method ) ) {
            // Expected format
            // _c_[return]_[mvccall]
            // Sample - _c_void_ConfigController@local
            $is_return = preg_match( '/^\_c\_return\_/', $method );
            $method = preg_replace( '/^\_c\_(return|void)\_/', '', $method );
            if ( $is_return ) {
                return $this->mvc->action_args(
                    $method,
                    $this->override_args( $method, $args )
                );
            } else {
                $this->mvc->call_args(
                    $method,
                    $this->override_args( $method, $args )
                );
            }
        } else if ( preg_match( '/^\_v\_/', $method ) ) {
            // Expected format
            // _v_[return]_[mvccall]
            // Sample - _v_void_View@test.view
            $is_return = preg_match( '/^\_v\_return\_/', $method );
            $method = preg_replace( '/^\_v\_(return|void)\_/', '', $method );
            $view =  preg_replace( '/[vV]iew\@/', '', $method );
            if ( $is_return ) {
                return $this->mvc->view->get(
                    $view,
                    $this->override_args( $method, $args )
                );
            } else {
                $this->mvc->view->show(
                    $view,
                    $this->override_args( $method, $args )
                );
            }
        } else if ( preg_match( '/^\_save\_/', $method ) ) {
            $this->_save( preg_replace( '/^\_save\_/', '', $method ), $args );
        } else if ( preg_match( '/^\_metabox/', $method ) ) {
            $this->_metaboxes();
        } else if ( preg_match( '/^\_registry_supports\_/', $method ) ) {
            $this->_registry_supports( preg_replace( '/^\_registry_supports\_/', '', $method ), $args );
        } else {
            return call_user_func_array( [ $this, $method ], $args );
        }
    }

    /**
     * Sets plugin addons.
     * @since 1.0.2
     *
     * @return void
     */
    protected function set_addons()
    {
        if ( $this->config->get( 'addons' ) ) {
            foreach ( $this->config->get( 'addons' ) as $addon ) {
                $this->addons[] = new $addon( $this );
            }
        }
    }

    /**
     * Displays view with the parameters passed by.
     * @since 1.0.1
     *
     * @param string $view   Name and location of the view within "[project]/views" path.
     * @param array  $params View parameters passed by.
     *
     * @return void
     */
    public function view( $view, $params = array() )
    {
        $this->mvc->view->show( $view, $params );
    }

    /**
     * Returns view based on the parameters passed by.
     * @since 3.1.8
     *
     * @param string $view   Name and location of the view within "[project]/views" path.
     * @param array  $params View parameters passed by.
     *
     * @return string
     */
    public function get_view( $view, $params = array() )
    {
        return $this->mvc->view->get( $view, $params );
    }

    /**
     * Called by autoload to init class.
     * @since 1.0.2
     * @return void
     */
    public function autoload_init()
    {
        $this->init();
        // Addons
        for ( $i = count( $this->addons ) - 1; $i >= 0; --$i ) {
            $this->addons[$i]->init();
        }
    }

    /**
     * Called by autoload to init on admin.
     * @since 1.0.2
     * @return void
     */
    public function autoload_on_admin()
    {
        $this->on_admin();
        // Addons
        for ( $i = count( $this->addons ) - 1; $i >= 0; --$i ) {
            $this->addons[$i]->on_admin();
        }
    }

    /**
     * Init.
     * @since 1.0.2
     * @return void
     */
    public function init()
    {
        // TODO custom code.
    }

    /**
     * On admin Dashboard.
     * @since 1.0.2
     * @return void
     */
    public function on_admin()
    {
        // TODO custom code.
    }

    /**
     * Adds a WordPress action hook.
     * @since 1.0.3
     *
     * @param string $hook           WordPress hook name.
     * @param string|array $mvc_call Lightweight MVC call. (i.e. 'Controller@method')
     * @param mixed  $priority       Execution priority or MVC params.
     * @param mixed  $accepted_args  Accepted args or priority.
     * @param int    $args           Accepted args.
     */
    public function add_action( $hook, $mvc_call, $priority = 10, $accepted_args = 1, $args = 1 )
    {
        $this->actions[] = $this->get_hook(
            $hook,
            $mvc_call,
            $priority,
            $accepted_args,
            $args
        );
    }

    /**
     * Adds a WordPress filter hook.
     * @since 1.0.3
     *
     * @param string $hook           WordPress hook name.
     * @param string|array $mvc_call Lightweight MVC call. (i.e. 'Controller@method')
     * @param mixed  $priority       Execution priority or MVC params.
     * @param mixed  $accepted_args  Accepted args or priority.
     * @param int    $args           Accepted args.
     */
    public function add_filter( $hook, $mvc_call, $priority = 10, $accepted_args = 1, $args = 1 )
    {
        $this->filters[] = $this->get_hook(
            $hook,
            $mvc_call,
            $priority,
            $accepted_args,
            $args
        );
    }

    /**
     * Adds a WordPress shortcode.
     * @since 1.0.3
     *
     * @param string $tag            WordPress tag name.
     * @param string|array $mvc_call Lightweight MVC call. (i.e. 'Controller@method')
     */
    public function add_shortcode( $tag, $mvc_call, $mvc_args = null )
    {
        $this->shortcodes[] = [
            'tag'        => $tag,
            'mvc'        => $mvc_call,
            'mvc_args'    => $mvc_args,
        ];
    }

    /**
     * Adds a WordPress widget.
     * @since 1.0.3
     *
     * @param string $class Widget class name to add.
     */
    public function add_widget( $class )
    {
        $this->widgets[] = $class;
    }

    /**
     * Adds a Model for registration.
     * @since 2.0.4
     *
     * @param string $class Model class name to add.
     */
    public function add_model( $class )
    {
        $this->models[] = $class;
    }

    /**
     * Adds an asset for registration.
     * @since 2.0.7
     *
     * @param string $asset         Asset relative path (within assets forlder).
     * @param bool   $enqueue       Flag that indicates if asset should be enqueued upon registration.
     * @param array  $dep           Dependencies.
     * @param bool   $flag          Conditional flag. For script assests it indicates it should be enqueued in the footer.
     *                              For style assests it indicates the media for which it has been defined.
     * @param bool   $is_admin      Flag that indicates if asset should be enqueue on admin.
     * @param string $version       Asset version.
     * @param string $name_id       Asset name ID (slug).
     */
    public function add_asset( $asset, $enqueue = true, $dep = [], $flag = null, $is_admin = false, $version = null, $name_id = null )
    {
        if ( $flag === null )
            $flag = strpos( $asset, '.css') !== false ? 'all' : true;
        $this->assets[] = [
            'path'      => $asset,
            'enqueue'   => $enqueue,
            'dep'       => $dep,
            'flag'      => $flag,
            'is_admin'  => $is_admin,
            'version'   => $version,
            'name_id'   => $name_id,
        ];
    }

    /**
     * Adds hooks and filters into WordPress core.
     * @since 1.0.3
     */
    public function add_hooks()
    {
        if ( function_exists( 'add_action' )
            && function_exists( 'add_filter' )
            && function_exists( 'add_shortcode' )
        ) {
            // Actions
            foreach ( $this->actions as $action ) {
                add_action(
                    $action['hook'],
                    [ &$this, $this->get_mapped_mvc_call( $action['mvc'] ) ],
                    $action['priority'],
                    $action['args']
                );
            }
            // Filters
            foreach ( $this->filters as $filter ) {
                add_filter(
                    $filter['hook'],
                    [ &$this, $this->get_mapped_mvc_call( $filter['mvc'], true ) ],
                    $filter['priority'],
                    $filter['args']
                );
            }
            // Shortcodes
            foreach ( $this->shortcodes as $shortcode ) {
                add_shortcode(
                    $shortcode['tag'],
                    [ &$this, $this->get_mapped_mvc_call( $shortcode['mvc'], true ) ]
                );
            }
            // Widgets
            if ( count( $this->widgets ) > 0 ) {
                add_action( 'widgets_init', [ &$this, '_widgets' ], 1 );
            }
            // Models
            if ( count( $this->models ) > 0 ) {
                add_action( 'init', [ &$this, '_models' ], 1 );
            }
            // Assets
            if ( count( $this->assets ) > 0 ) {
                add_action(
                    'wp_enqueue_scripts',
                    [ &$this, '_assets' ],
                    $this->config->get( 'autoenqueue.priority' ) ? $this->config->get( 'autoenqueue.priority' ) : 10
                );
                if ( is_admin() ) {
                    add_action(
                        'admin_enqueue_scripts',
                        [ &$this, '_admin_assets' ],
                        $this->config->get( 'autoenqueue.priority' ) ? $this->config->get( 'autoenqueue.priority' ) : 10
                    );
                }
            }
        }
    }

    /**
     * Returns a configuration file loaded as a Config class.
     * @since 3.1.11
     * 
     * @param string $config_file Configuration file name without extension.
     * 
     * @return null|\WPMVC\Config
     */
    public function load_config( $config_file )
    {
        if ( !array_key_exists( $config_file, $this->_configs ) ) {
            $filename = $this->config->get( 'paths.base' ) . 'Config/' . $config_file . '.php';
            $this->_configs[$config_file] = new Config( File::auth()->exists( $filename ) ? include $filename : [] );
        }
        return $this->_configs[$config_file];
    }

    /**
     * Registers added widgets into WordPress.
     * @since 1.0.3
     */
    public function _widgets()
    {
        foreach ( $this->widgets as $widget ) {
            register_widget( $widget );
        }
    }

    /**
     * Registers added models into WordPress.
     * @since 2.0.4
     */
    public function _models()
    {
        foreach ( $this->models as $model ) {
            $post_name = $this->config->get('namespace').'\Models\\'.$model;
            $post = new $post_name;
            unset( $post_name );
            // Create registry
            $registry = $post->registry;
            // Build registration
            if ( !empty( $registry ) ) {
                if ( empty( $post->registry_labels ) ) {
                    $name = ucwords( preg_replace( '/\-\_/', ' ', $post->type ) );
                    $plural = strpos( $name, ' ' ) === false ? $name.'s' : $name;
                    $registry['labels'] = [
                        'name'               => $plural,
                        'singular_name'      => $name,
                        'menu_name'          => $plural,
                        'name_admin_bar'     => $name,
                        'add_new_item'       => sprintf( 'Add New %s', $name ),
                        'new_item'           => sprintf( 'New %s', $name ),
                        'edit_item'          => sprintf( 'Edit %s', $name ),
                        'view_item'          => sprintf( 'View %s', $name ),
                        'all_items'          => sprintf( 'All %s', $plural ),
                        'search_items'       => sprintf( 'Search %s', $plural ),
                        'parent_item_colon'  => sprintf( 'Parent %s', $plural ),
                        'not_found'          => sprintf( 'No %s found.', strtolower( $plural ) ),
                        'not_found_in_trash' => sprintf( 'No %s found in Trash.', strtolower( $plural ) ),
                    ];
                } else {
                    $registry['labels'] = $post->registry_labels;
                }
                $registry['supports'] = $post->registry_supports;
                if ( empty( $post->registry_rewrite ) ) {
                    $slug = strtolower(preg_replace('/\_/', '-', $post->type));
                    $registry['rewrite'] = [
                        'slug' => strtolower(preg_replace('/\_/', '-', $post->type)),
                    ];
                } else {
                    $registry['rewrite'] = $post->registry_rewrite;
                }
                // Register
                register_post_type( $post->type, $registry );
            } else if ( ! empty( $post->registry_supports ) ) {
                add_action( 'admin_init', [ &$this, '_registry_supports_'.$post->type ] );
            }
            if ( $post->registry_metabox ) {
                // Add save action once
                $addAction = true;
                for ( $i = count( $this->_automatedModels )-1; $i >= 0; --$i ) {
                    if ( $this->_automatedModels[$i]->type === $post->type ) {
                        $addAction = false;
                        break;
                    }
                }
                // Add post
                $this->_automatedModels[] = $post;
                // Hook save_post
                if ( $addAction )
                    add_action( 'save_post', [ &$this, '_save_'.$post->type ] );
                unset( $addAction );
            }
            // Register taxonomies
            if ( !empty( $post->registry_taxonomies ) ) {
                foreach ( $post->registry_taxonomies as $taxonomy => $args ) {
                    if ( !isset( $args ) || !is_array( $args ) )
                        throw new Exception( 'Arguments are missing for taxonomy "'.$taxonomy.'", post type "'.$post->type.'".' );
                    register_taxonomy( $taxonomy, $post->type, apply_filters( 'wpmvc_taxonomy_args_' . $taxonomy,  $args ) );
                }
            }
        }
        // Metabox hook
        add_action( 'add_meta_boxes', [ &$this, '_metabox' ] );
    }

    /**
     * Enqueues assets registered in class.
     * @since 2.0.7
     */
    public function _assets()
    {
        $version = $this->config->get( 'version' ) ? $this->config->get( 'version' ) : '1.0.0';
        $dir = $this->config->get( 'paths.base' )
            ? $this->config->get( 'paths.base' )
            : __DIR__;
        foreach ( $this->assets as $asset ) {
            if ( isset( $asset['is_admin'] ) && $asset['is_admin'] ) continue;
            $name = !empty( $asset['name_id'] )
                ? $asset['name_id']
                : strtolower( preg_replace( '/css|js|\/|\.min|\./', '', $asset['path'] ) )
                    . '-' . strtolower( $this->config->get( 'namespace' ) );
            $asset_version = empty( $asset['version'] ) ? $version : $asset['version'];
            // Styles
            if ( preg_match( '/\.css/', $asset['path'] ) ) {
                wp_register_style(
                    $name,
                    assets_url( $asset['path'], $dir ),
                    $asset['dep'],
                    $asset_version,
                    $asset['flag']
                );
                if ($asset['enqueue'])
                    wp_enqueue_style(
                        $name,
                        assets_url( $asset['path'], $dir ),
                        $asset['dep'],
                        $asset_version,
                        $asset['flag']
                    );
            }
            // Scripts
            if ( preg_match( '/\.js/', $asset['path'] ) ) {
                wp_register_script(
                    $name,
                    assets_url( $asset['path'], $dir ),
                    $asset['dep'],
                    $asset_version,
                    $asset['flag']
                );
                if ($asset['enqueue'])
                    wp_enqueue_script(
                        $name,
                        assets_url( $asset['path'], $dir ),
                        $asset['dep'],
                        $asset_version,
                        $asset['flag']
                    );
            }
        }
    }

    /**
     * Enqueues admin assets registered in class.
     * @since 3.1.0
     */
    public function _admin_assets()
    {
        $version = $this->config->get( 'version' ) ? $this->config->get( 'version' ) : '1.0.0';
        $dir = $this->config->get( 'paths.base' )
            ? $this->config->get( 'paths.base' )
            : __DIR__;
        foreach ( $this->assets as $asset ) {
            if ( ! isset( $asset['is_admin'] ) || ! $asset['is_admin'] ) continue;
            $name = !empty( $asset['name_id'] )
                ? $asset['name_id']
                : strtolower( preg_replace( '/css|js|\/|\.min|\./', '', $asset['path'] ) )
                    . '-' . strtolower( $this->config->get( 'namespace' ) );
            $asset_version = empty( $asset['version'] ) ? $version : $asset['version'];
            // Styles
            if ( preg_match( '/\.css/', $asset['path'] ) ) {
                wp_register_style(
                    $name,
                    assets_url( $asset['path'], $dir ),
                    $asset['dep'],
                    $asset_version,
                    $asset['flag']
                );
                if ($asset['enqueue'])
                    wp_enqueue_style(
                        $name,
                        assets_url( $asset['path'], $dir ),
                        $asset['dep'],
                        $asset_version,
                        $asset['flag']
                    );
            }
            // Scripts
            if ( preg_match( '/\.js/', $asset['path'] ) ) {
                wp_register_script(
                    $name,
                    assets_url( $asset['path'], $dir ),
                    $asset['dep'],
                    $asset_version,
                    $asset['flag']
                );
                if ($asset['enqueue'])
                    wp_enqueue_script(
                        $name,
                        assets_url( $asset['path'], $dir ),
                        $asset['dep'],
                        $asset_version,
                        $asset['flag']
                    );
            }
        }
    }

    /**
     * Removes registered MVC action hook.
     * @since 3.1.15
     *
     * @param string $hook
     * @param string|array $mvc_handler
     * @param int    $priority
     */
    public function remove_action( $hook, $mvc_handler, $priority = 10 )
    {
        remove_action(
            $hook,
            [ &$this, $this->get_mapped_mvc_call( $mvc_handler ) ],
            $priority
        );
    }

    /**
     * Removes registered MVC filter hook.
     * @since 3.1.15
     *
     * @param string $hook
     * @param string|array $mvc_handler
     * @param int    $priority
     */
    public function remove_filter( $hook, $mvc_handler, $priority = 10 )
    {
        remove_filter(
            $hook,
            [ &$this, $this->get_mapped_mvc_call( $mvc_handler, true ) ],
            $priority
        );
    }

    /**
     * Cast MVC call from PHP array Callbacks / Callables to typical string representation
     * @since 3.1.18
     *
     * @param string|array $mvc_call Lightweight MVC call. (i.e. 'Controller@method', or [Controller::class, 'method'])
     *
     * @return string
     */
    private function cast_mvc_call_to_string( $mvc_call )
    {
        if ( is_array( $mvc_call ) && count( $mvc_call ) === 2 ) {
            $class_path = explode( '\\', $mvc_call[0] );
            return end( $class_path ) . '@' . $mvc_call[1];
        }
        return $mvc_call;
    }

    /**
     * Returns class method call mapped to a mvc engine method.
     * @since 1.0.3
     *
     * @param string|array $call
     *
     * @return string
     */
    private function get_mapped_mvc_call( $call, $return = false )
    {
        $call = $this->cast_mvc_call_to_string( $call );
        return ( preg_match( '/[vV]iew\@/', $call ) ? '_v_' : '_c_' )
            . ( $return ? 'return_' : 'void_' )
            . $call;
    }

    /**
     * Returns valid action filter item.
     * @since 1.0.3
     *
     * @param string $hook           WordPress hook name.
     * @param string|array $mvc_call Lightweight MVC call. (i.e. 'Controller@method')
     * @param mixed $priority        Execution priority or MVC params.
     * @param mixed $accepted_args   Accepted args or priority.
     * @param int $args              Accepted args.
     *
     * @return array
     */
    private function get_hook( $hook, $mvc_call, $priority = 10, $accepted_args = 1, $args = null )
    {
        return [
            'hook'      => $hook,
            'mvc'       => $this->cast_mvc_call_to_string( $mvc_call ),
            'priority'  => is_array( $priority ) ? $accepted_args : $priority,
            'args'      => is_array( $priority ) ? ( $args ? $args : count( $priority ) ) : $accepted_args,
            'mvc_args'  => is_array( $priority ) ? $priority : null,
        ];
    }

    /**
     * Override mvc arguments with those defined when adding an action or filter.
     * @since 1.0.3
     *
     * @param string $mvc_call Lightweight MVC call. (i.e. 'Controller@method')
     * @param array  $args     Current args for call.
     *
     * @return array
     */
    private function override_args( $mvc_call, $args )
    {
        // Check on actions
        for ( $i = count( $this->actions ) - 1; $i >= 0; --$i ) {
            if ( ! empty( $this->actions[$i]['mvc_args'] )
                && $this->actions[$i]['mvc'] === $mvc_call
            ) {
                return $this->process_mvc_args( $this->actions[$i]['mvc'], $this->actions[$i]['mvc_args'], $args );
            }
        }
        // Check on filters
        for ( $i = count( $this->filters ) - 1; $i >= 0; --$i ) {
            if ( ! empty( $this->filters[$i]['mvc_args'] )
                && $this->filters[$i]['mvc'] === $mvc_call
            ) {
                return $this->process_mvc_args( $this->actions[$i]['mvc'], $this->filters[$i]['mvc_args'], $args );
            }
        }
        // Check on shortcodes
        for ( $i = count( $this->shortcodes ) - 1; $i >= 0; --$i ) {
            if ( ! empty( $this->shortcodes[$i]['mvc_args'] )
                && $this->shortcodes[$i]['mvc'] === $mvc_call
            ) {
                return $this->process_mvc_args( $this->actions[$i]['mvc'], $this->shortcodes[$i]['mvc_args'], $args );
            }
        }
        return $args;
    }
    /**
     * Process MVC arguments to determine if special treatment is needed.
     * View arguments are processed to be sent as view parameters.
     * @since 3.1.5
     * 
     * @param string $call     MVC call.
     * @param array  $mvc_args MVC defined arguments.
     * @param array  $args     WordPress incoming hook arguments.
     * 
     * @return array
     */
    private function process_mvc_args( $call, $mvc_args, &$args )
    {
        if ( strpos( $call, 'view@' ) !== false ) {
            $view_parmas = [];
            foreach ( array_keys( $mvc_args ) as $index => $key ) {
                if ( is_numeric( $key ) ) {
                    $view_parmas[$mvc_args[$key]] = $args[$index];
                } else if ( is_string( $key ) ) {
                    $view_parmas[$key] = $mvc_args[$key];
                }
            }
            return $view_parmas;
        }
        return $mvc_args;
    }

    /**
     * Addes automated wordpress metaboxes based on post type.
     * @since 2.0.4
     */
    private function _metaboxes()
    {
        // Metaboxes
        for ( $i = count( $this->_automatedModels )-1; $i >= 0; --$i ) {
            if ( $this->_automatedModels[$i]->registry_metabox
                && is_array( $this->_automatedModels[$i]->registry_metabox )
                && count( $this->_automatedModels[$i]->registry_metabox ) > 1
            )
                add_meta_box(
                    '_wpmvc_'.uniqid(),
                    $this->_automatedModels[$i]->registry_metabox['title'],
                    [ &$this, '_c_void_'.$this->_automatedModels[$i]->registry_controller.'@_metabox' ],
                    $this->_automatedModels[$i]->type,
                    $this->_automatedModels[$i]->registry_metabox['context'],
                    $this->_automatedModels[$i]->registry_metabox['priority']
                );
        }
    }

    /**
     * Addes automated WordPress save post functionality.
     * @since 2.0.4
     *
     * @param string $type Post type.
     * @param array  $args Hooks arguments.
     */
    private function _save( $type, $args )
    {
        if ( get_post_type( $args[0] ) !== $type ) return;
        // Check nonce
        $nonce = Request::input( '_wpmvc_nonce', '', true );
        if ( empty( $nonce ) || !wp_verify_nonce( $nonce, '_wpmvc_post' ) )
            return;
        // Save
        for ( $i = count( $this->_automatedModels )-1; $i >= 0; --$i ) {
            if ( $this->_automatedModels[$i]->type == trim( $type ) )
                $this->mvc->call_args(
                    $this->_automatedModels[$i]->registry_controller.'@_save',
                    $args
                );
        }
    }

    /**
     * Checks if generated assets exist or not.
     * @since 2.0.7
     */
    private function _check_assets()
    {
        if ( $this->config->get( 'autoenqueue.enabled' )
            && $this->config->get( 'autoenqueue.enabled' ) === true
        ) {
            $file = File::auth();
            $dir = $this->config->get( 'paths.base' )
                ? $this->config->get( 'paths.base' )
                : __DIR__;
            foreach ( $this->config->get( 'autoenqueue.assets' ) as $asset ) {
                if ( $file->exists( assets_path( $asset['asset'], $dir ) ) ) {
                    $this->add_asset(
                        $asset['asset'],
                        array_key_exists( 'enqueue', $asset ) ? $asset['enqueue'] : true,
                        array_key_exists( 'dep', $asset ) ? $asset['dep'] : array(),
                        array_key_exists( 'footer', $asset ) ? $asset['footer'] : ( array_key_exists( 'flag', $asset ) ? $asset['flag'] : null ),
                        array_key_exists( 'is_admin', $asset ) ? $asset['is_admin'] : false,
                        array_key_exists( 'version', $asset ) ? $asset['version'] : null,
                        array_key_exists( 'id', $asset ) ? $asset['id'] : null
                    );
                }
            }
        }
    }

    /**
     * Registers post type supports for models with no post type registration.
     * @since 2.0.16
     *
     * @param string $type Post type.
     * @param array  $args Hook arguments.
     */
    private function _registry_supports( $type, $args )
    {
        for ( $i = count( $this->models )-1; $i >= 0; --$i ) {
            if ( is_object( $this->models[$i] )
                && $this->models[$i]->type === $type
            )
                add_post_type_support( $type, $this->models[$i]->registry_supports );
        }
    }

    /**
     * Loads localization.
     * @since 3.1.0
     */
    private function _localize()
    {
        if ( $this->config->get( 'localize.enabled' )
            && $this->config->get( 'localize.enabled' ) === true
        ) {
            $domain = $this->config->get( 'localize.textdomain' );
            $locale = apply_filters(
                'plugin_locale',
                function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale(),
                $domain
            );
            if ( $this->config->get( 'localize.unload' ) )
                unload_textdomain( $domain );
            if ( ( ! $this->config->get( 'localize.is_public' )
                    || ! load_textdomain( $domain, sprintf( '%s/%s/%s-%s.mo', WP_LANG_DIR, $domain, $domain, $locale ) )
                )
                &&
                ! load_textdomain( $domain, sprintf( '%s%s-%s.mo', $this->config->get( 'localize.path' ), $domain, $locale ) )
            )
                load_plugin_textdomain( $domain, false, $this->config->get( 'localize.path' ) );
        }
    }
}
