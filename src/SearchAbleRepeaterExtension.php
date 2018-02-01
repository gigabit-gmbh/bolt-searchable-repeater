<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater;

use Bolt\Extension\Gigabit\SearchableRepeater\Controller\SearchController;
use Bolt\Extension\SimpleExtension;

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
        return [
            '/suche' => new SearchController(),
        ];
    }

}
