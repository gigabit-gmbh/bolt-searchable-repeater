<?php

namespace Bolt\Extension\Gigabit\Controller;

use Bolt\Controller\Base;
use Bolt\Controller\ConfigurableBase;
use Bolt\Helpers\Input;
use Bolt\Response\TemplateResponse;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller class.
 *
 * @author Thomas Helmrich <thomas@helmri.ch>
 */
class SearchController extends ConfigurableBase
{
    protected function getConfigurationRoutes()
    {
        return $this->app['config']->get('routing', []);
    }

    /**
     * The search result page controller.
     *
     * @param Request $request The Symfony Request
     * @param array $contenttypes The content type slug(s) you want to search for
     *
     * @return TemplateResponse
     */
    public function search(Request $request, array $contenttypes = null)
    {
        $q = '';
        $context = __FUNCTION__;

        if ($request->query->has('q')) {
            $q = $request->query->get('q');
        } elseif ($request->query->has($context)) {
            $q = $request->query->get($context);
        }
        $q = Input::cleanPostedData($q, false);

        $page = $this->app['pager']->getCurrentPage($context);

        // Theme value takes precedence over default config @see https://github.com/bolt/bolt/issues/3951
        $pageSize = $this->getOption('theme/search_results_records', false);
        if ($pageSize === false && !$pageSize = $this->getOption('general/search_results_records', false)) {
            $pageSize = $this->getOption('theme/listing_records', false) ?: $this->getOption(
                'general/listing_records',
                10
            );
        }

        $offset = ($page - 1) * $pageSize;
        $limit = $pageSize;

        // set-up filters from URL
        $filters = [];
        foreach ($request->query->all() as $key => $value) {
            if (strpos($key, '_') > 0) {
                list($contenttypeslug, $field) = explode('_', $key, 2);
                if (isset($filters[$contenttypeslug])) {
                    $filters[$contenttypeslug][$field] = $value;
                } else {
                    $contenttype = $this->getContentType($contenttypeslug);
                    if (is_array($contenttype)) {
                        $filters[$contenttypeslug] = [
                            $field => $value,
                        ];
                    }
                }
            }
        }
        if (count($filters) == 0) {
            $filters = null;
        }

        $result = $this->storage()->searchContent($q, $contenttypes, $filters, $limit, $offset);

        /** @var \Bolt\Pager\PagerManager $manager */
        $manager = $this->app['pager'];
        $manager
            ->createPager($context)
            ->setCount($result['no_of_results'])
            ->setTotalpages(ceil($result['no_of_results'] / $pageSize))
            ->setCurrent($page)
            ->setShowingFrom($offset + 1)
            ->setShowingTo($offset + count($result['results']));

        $manager->setLink($this->generateUrl('search', ['q' => $q]).'&page_search=');

        $globals = [
            'records' => $result['results'],
            $context => $result['query']['sanitized_q'],
            'searchresult' => $result,
        ];

        $template = $this->templateChooser()->search();

        return $this->render($template, [], $globals);
    }

}
