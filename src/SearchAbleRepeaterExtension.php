<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater;

use Bolt\Extension\SimpleExtension;
use Silex\Application;

/**
 * ExtensionName extension class.
 *
 * @author Thomas Helmrich <thomas@helmri.ch>
 */
class SearchAbleRepeaterExtension extends SimpleExtension
{

    /**
     * @param Application $app
     */
    protected function registerServices(Application $app)
    {
        $app['controller.repeater-search'] = $app->share(
            function ($app) {
                return new Controller\SearchController($app);
            }
        );
    }

}
