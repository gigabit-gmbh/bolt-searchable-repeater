<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater\Controller;

use Bolt\Collection\MutableBag;
use Bolt\Controller\Frontend;
use Bolt\Response\TemplateResponse;
use Bolt\Response\TemplateView;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class.
 *
 * @author Thomas Helmrich <thomas@helmri.ch>
 */
class SearchController extends Frontend
{

    /**
     * SearchController constructor.
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function getConfigurationRoutes()
    {
        return $this->app['config']->get('routing', []);
    }

    /**
     * Returns the Entity Manager.
     *
     * @return \Bolt\Storage\EntityManager|\Bolt\Legacy\Storage
     */
    protected function storage()
    {
        return $this->app['helper.storage'];
    }

    /**
     * The search result page controller.
     *
     * @param Request $request The Symfony Request
     * @param array $contenttypes The content type slug(s) you want to search for
     *
     * @return TemplateResponse|TemplateView
     */
    public function searchWithRepeater(Request $request, array $contenttypes = null)
    {
        /** @var TemplateView $renderedTemplate */
        $renderedTemplate = $this->search($request, $contenttypes);

        if (isset($contenttypes)) {
            $mutableBag = MutableBag::from($renderedTemplate->getContext());

            $mutableBag->set("contentTypes", $contenttypes);

            if ($renderedTemplate instanceof TemplateView) {
                $renderedTemplate->setContext($mutableBag);
            }
        }

        return $renderedTemplate;
    }

}
