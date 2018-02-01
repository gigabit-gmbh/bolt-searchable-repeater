<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater;

use Bolt\Extension\Gigabit\SearchableRepeater\Helpers\Storage;
use Bolt\Extension\SimpleExtension;
use Bolt\Storage\EntityManager;
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
        $app['helper.storage.legacy'] = $app->share(
            function ($app) {
                return new Storage($app);
            }
        );

        $app['helper.storage'] = $app->share(
            function ($app) {
                $storage = new EntityManager(
                    $app['db'],
                    $app['dispatcher'],
                    $app['storage.metadata'],
                    $app['logger.system']
                );
                $storage->setLegacyService($app['storage.legacy_service']);
                $storage->setLegacyStorage($app['helper.storage.legacy']);
                $storage->setEntityBuilder($app['storage.entity_builder']);
                $storage->setFieldManager($app['storage.field_manager']);
                $storage->setCollectionManager($app['storage.collection_manager']);

                foreach ($app['storage.repositories'] as $entity => $repo) {
                    $storage->setRepository($entity, $repo);
                }

                $storage->setDefaultRepositoryFactory($app['storage.content_repository']);

                return $storage;
            }
        );


        $app['controller.repeater-search'] = $app->share(
            function ($app) {
                return new Controller\SearchController($app);
            }
        );



    }

}
