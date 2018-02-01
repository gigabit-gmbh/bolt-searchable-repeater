<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater;

use Bolt\Extension\SimpleExtension;
use Silex\ControllerCollection;

/**
 * ExtensionName extension class.
 *
 * @author Thomas Helmrich <thomas@helmri.ch>
 */
class SearchAbleRepeaterExtension extends SimpleExtension
{

    /**
     * {@inheritdoc}
     *
     * Mount the ExampleController class to all routes that match '/example/url/*'
     *
     * To see specific bindings between route and controller method see 'connect()'
     * function in the ExampleController class.
     */
    protected function registerFrontendControllers()
    {
        /*return [
            '/example/url' => new SearchController(),
        ];*/
    }

    /**
     * {@inheritdoc}
     *
     * This first route will be handled in this extension class,
     * then we switch to an extra controller class for the routes.
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        //$collection->match('/example/url', [$this, 'routeExampleUrl']);
    }

}
