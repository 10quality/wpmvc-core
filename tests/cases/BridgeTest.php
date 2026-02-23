<?php

use PHPUnit\Framework\TestCase;
/**
 * Tests global functions.
 *
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC
 * @version 3.1.11
 */
class BridgeTest extends TestCase
{
    /**
     * Test bridge constructor.
     * @group bridge
     * @group config
     */
    function testConstruct()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Assert
        $this->assertInstanceOf(\WPMVC\Config::class, $main->config);
        $this->assertEquals('test', $main->config->get('assert'));
    }
    /**
     * Test dynamic method on controller. Void.
     * @group bridge
     * @group mvc
     */
    function testControllerVoidMethod()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Exec
        ob_start();
        $main->{'_c_void_TestController@action'}('void');
        // Assert
        $this->assertEquals('void', ob_get_clean());
    }
    /**
     * Test dynamic method on controller. Return.
     * @group bridge
     * @group mvc
     */
    function testControllerReturnMethod()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Exec
        $return = $main->{'_c_return_TestController@filter'}(123);
        // Assert
        $this->assertEquals(123, $return);
    }
    /**
     * Test dynamic method on view. Void.
     * @group bridge
     * @group mvc
     */
    function testViewVoidMethod()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Exec
        ob_start();
        $main->{'_v_void_view@view'}();
        // Assert
        $this->assertEquals('Test View', ob_get_clean());
    }
    /**
     * Test dynamic method on view. Return.
     * @group bridge
     * @group mvc
     */
    function testViewReturnMethod()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Exec
        $return = $main->{'_v_return_view@view'}();
        // Assert
        $this->assertEquals('Test View', $return);
    }
    /**
     * Tests parameter override.
     * @group bridge
     * @group mvc
     */
    function testControllerParameterOverride()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        $main->add_action( 'test1', 'TestController@filter', ['override'] );
        $main->add_action( 'test2', [\UnitTesting\Controllers\TestController::class, 'filter'], ['override'] );
        // Exec
        $return1 = $main->{'_c_return_TestController@filter'}(123);
        $return2 = $main->{'_c_return_TestController@filter'}(123);
        // Assert
        $this->assertEquals('override', $return1);
        $this->assertEquals('override', $return2);
    }
    /**
     * Tests view parameter override.
     * @group bridge
     * @group mvc
     */
    function testViewParameterOverride()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        $main->add_action( 'test', 'view@view', ['param', 'id'] );
        // Exec
        ob_start();
        $main->{'_v_void_view@view'}('title', 123);
        // Assert
        $this->assertEquals('<h1>title</h1><h2>123</h2>Test View', ob_get_clean());
    }
    /**
     * Test method.
     * @since 3.1.8
     * @group bridge
     * @group mvc
     */
    function testViewMethod()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Exec
        ob_start();
        $main->view('view', ['param' => 'view()']);
        // Assert
        $this->assertEquals('<h1>view()</h1>Test View', ob_get_clean());
    }
    /**
     * Test method.
     * @since 3.1.8
     * @group bridge
     * @group mvc
     */
    function testGetViewMethod()
    {
        // Prepare
        global $config;
        $main = new Main($config);
        // Exec
        $html = $main->get_view('view', ['param' => 'gat_view()']);
        // Assert
        $this->assertEquals('<h1>gat_view()</h1>Test View', $html);
    }
    /**
     * Test method.
     * @since 3.1.15
     * @group bridge
     * @group mvc
     * @group hooks
     */
    function testAddAction()
    {
        // Prepare
        global $config;
        global $hooks;
        $main = new Main($config);
        // Run
        $main->add_action( 'test', 'TestController@testing' );
        $main->add_action( 'test2', [\UnitTesting\Controllers\TestController::class, 'testing'] );
        $main->add_action( 'test3', 'view@test2' );
        $main->add_hooks();
        // Assert
        $this->assertArrayHasKey('test', $hooks['actions']);
        $this->assertArrayHasKey('test2', $hooks['actions']);
        $this->assertArrayHasKey('test3', $hooks['actions']);
        $this->assertEquals('[{},"_c_void_TestController@testing"]', json_encode($hooks['actions']['test']));
        $this->assertEquals('[{},"_c_void_TestController@testing"]', json_encode($hooks['actions']['test2']));
        $this->assertEquals('[{},"_v_void_view@test2"]', json_encode($hooks['actions']['test3']));
    }
    /**
     * Test method.
     * @since 3.1.15
     * @group bridge
     * @group mvc
     * @group hooks
     */
    function testAddFilter()
    {
        // Prepare
        global $config;
        global $hooks;
        $main = new Main($config);
        // Run
        $main->add_filter( 'controller1', 'FilterController@filtering' );
        $main->add_filter( 'controller2', [\UnitTesting\Controllers\TestController::class, 'filter'] );
        $main->add_hooks();
        // Assert
        $this->assertArrayHasKey('controller1', $hooks['filters']);
        $this->assertArrayHasKey('controller2', $hooks['filters']);
        $this->assertEquals('[{},"_c_return_FilterController@filtering"]', json_encode($hooks['filters']['controller1']));
        $this->assertEquals('[{},"_c_return_TestController@filter"]', json_encode($hooks['filters']['controller2']));
    }
    /**
     * Test method.
     * @since 3.1.15
     * @group bridge
     * @group mvc
     * @group hooks
     */
    function testRemoveAction()
    {
        // Prepare
        global $config;
        global $hooks;
        $main = new Main($config);
        // Run
        $main->add_action( 'action1', 'ActionController@to_remove' );
        $main->add_action( 'action2', [\UnitTesting\Controllers\TestController::class, 'action'] );
        $main->add_hooks();
        $main->remove_action( 'action1', 'ActionController@to_remove' );
        $main->remove_action( 'action2', [\UnitTesting\Controllers\TestController::class, 'action'] );
        // Assert
        $this->assertArrayNotHasKey('action1', $hooks['actions']);
        $this->assertArrayHasKey('action1', $hooks['removed']);
        $this->assertArrayNotHasKey('action2', $hooks['actions']);
        $this->assertArrayHasKey('action2', $hooks['removed']);
        $this->assertEquals('[{},"_c_void_ActionController@to_remove"]', json_encode($hooks['removed']['action1']));
        $this->assertEquals('[{},"_c_void_TestController@action"]', json_encode($hooks['removed']['action2']));
    }
    /**
     * Test method.
     * @since 3.1.15
     * @group bridge
     * @group mvc
     * @group hooks
     */
    function testRemoveFilter()
    {
        // Prepare
        global $config;
        global $hooks;
        $main = new Main($config);
        // Run
        $main->add_filter( 'filter1', 'FilterController@to_remove' );
        $main->add_filter( 'filter2', [\UnitTesting\Controllers\TestController::class, 'filter'] );
        $main->add_hooks();
        $main->remove_filter( 'filter1', 'FilterController@to_remove' );
        $main->remove_filter( 'filter2', [\UnitTesting\Controllers\TestController::class, 'filter'] );
        // Assert
        $this->assertArrayNotHasKey('filter1', $hooks['filters']);
        $this->assertArrayHasKey('filter1', $hooks['removed']);
        $this->assertArrayNotHasKey('filter2', $hooks['filters']);
        $this->assertArrayHasKey('filter2', $hooks['removed']);
        $this->assertEquals('[{},"_c_return_FilterController@to_remove"]', json_encode($hooks['removed']['filter1']));
        $this->assertEquals('[{},"_c_return_TestController@filter"]', json_encode($hooks['removed']['filter2']));
    }
}