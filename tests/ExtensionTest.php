<?php

namespace Bolt\Extension\YourName\ExtensionName\Tests;

use Bolt\Extension\Gigabit\SearchableRepeater\SearchAbleRepeaterExtension;
use Bolt\Tests\BoltUnitTest;
use Bolt\Extension\YourName\ExtensionName\Extension;

/**
 * Ensure that the ExtensionName extension loads correctly.
 *
 */
class ExtensionTest extends BoltUnitTest
{
    public function testExtensionRegister()
    {
        $app = $this->getApp();
        $extension = new SearchAbleRepeaterExtension();
        $extension->register($app);
        $app['extensions']->register($extension);
        $name = $extension->getName();
        $this->assertSame($name, 'ExtensionName');
        $this->assertSame($extension, $app["extensions.$name"]);
    }
}
