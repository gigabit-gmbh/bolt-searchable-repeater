<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater\Helpers;

use Bolt\Collection\Arr;
use Bolt\Legacy\Content;
use Bolt\Legacy\Storage as BaseLegacyStorage;
use Silex\Application;

/**
 * Helper class to get repeater content for search
 *
 * @deprecated Deprecated since 3.0, to be removed in 4.0.
 */
class Storage extends BaseLegacyStorage
{

    protected $helperApp;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->helperApp = $app;
    }

    /**
     * Search through a single ContentType, respects repeater type
     *
     * Search, weigh and return the results.
     *
     * @param       $query
     * @param       $contenttype
     * @param       $fields
     * @param array $filter
     * @param bool $implode
     *
     * @return Content
     */
    private function searchSingleContentType($query, $contenttype, $fields, array $filter = null, $implode = false)
    {
        // This could be even more configurable
        // (see also Content->getFieldWeights)
        $searchableTypes = ['text', 'textarea', 'html', 'markdown'];
        $table = $this->getContenttypeTablename($contenttype);
        $fieldValueTable = $this->getTablename('field-value');

        if ($implode) {
            $query['words'] = [implode(' ', $query['words'])];
        }

        // Build fields 'WHERE'
        $fieldsWhere = [];
        foreach ($fields as $field => $fieldconfig) {
            if (in_array($fieldconfig['type'], $searchableTypes)) {
                foreach ($query['words'] as $word) {
                    // Build the LIKE, lowering the searched field to cover case-sensitive database systems
                    $fieldsWhere[] = sprintf(
                        'LOWER(%s.%s) LIKE LOWER(%s)',
                        $table,
                        $field,
                        $this->helperApp['db']->quote('%'.$word.'%')
                    );
                }
            } elseif ($fieldconfig['type'] === 'repeater') {
                foreach ($query['words'] as $word) {
                    // Build the LIKE, lowering the searched field to cover case-sensitive database systems
                    $word = $this->helperApp['db']->quote('%'.$word.'%');
                    $fieldsWhere[] = sprintf(
                        "((LOWER(%s.value_text) LIKE LOWER(%s)) OR (LOWER(%s.value_string) LIKE LOWER(%s)))",
                        $fieldValueTable,
                        $word,
                        $fieldValueTable,
                        $word
                    );
                }
            }
        }

        // make taxonomies work
        $taxonomytable = $this->getTablename('taxonomy');
        $taxonomies = $this->getContentTypeTaxonomy($contenttype);
        $tagsWhere = [];
        $tagsQuery = '';
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy['behaves_like'] == 'tags') {
                foreach ($query['words'] as $word) {
                    $tagsWhere[] = sprintf(
                        '%s.slug LIKE %s',
                        $taxonomytable,
                        $this->helperApp['db']->quote('%'.$word.'%')
                    );
                }
            }
        }
        // only add taxonomies if they exist
        if (!empty($taxonomies) && !empty($tagsWhere)) {
            $tagsQueryA = sprintf("%s.contenttype = '%s'", $taxonomytable, $contenttype);
            $tagsQueryB = implode(' OR ', $tagsWhere);
            $tagsQuery = sprintf(' OR (%s AND (%s))', $tagsQueryA, $tagsQueryB);
        }

        // Build filter 'WHERE"
        // @todo make relations work as well
        $filterWhere = [];
        if (!is_null($filter)) {
            foreach ($fields as $field => $fieldconfig) {
                if (isset($filter[$field])) {
                    $filterWhere[] = $this->parseWhereParameter($table.'.'.$field, $filter[$field]);
                }
            }
        }

        // Build actual where
        $where = [];
        $where[] = sprintf("%s.status = 'published'", $table);
        $where[] = '(( '.implode(' OR ', $fieldsWhere).' ) '.$tagsQuery.' )';
        $where = array_merge($where, $filterWhere);

        // Build SQL query
        $select = sprintf(
            'SELECT %s.id FROM %s LEFT JOIN %s ON %s.id = %s.content_id LEFT JOIN %s ON %s.id = %s.content_id WHERE %s GROUP BY %s.id',
            $table,
            $table,
            $taxonomytable,
            $table,
            $taxonomytable,
            $fieldValueTable,
            $table,
            $fieldValueTable,
            implode(' AND ', $where),
            $table
        );

        // Run Query
        $results = $this->helperApp['db']->fetchAll($select);

        if (!empty($results)) {
            $ids = implode(' || ', Arr::column($results, 'id'));

            $results = $this->getContent($contenttype, ['id' => $ids, 'returnsingle' => false]);

            // Convert and weight
            foreach ($results as $result) {
                $result->weighSearchResult($query);
            }
        }

        return $results;
    }

    /**
     *
     * Overwrite / duplication , because necessary searchSingleContentType is called within that function.
     *
     * Search through actual content.
     *
     * Unless the query is invalid it will always return a 'result array'. It may
     * complain in the log but it won't abort.
     *
     * @param string $q Search string
     * @param array $contenttypes Contenttype names to search for:
     *                              - string: Specific contenttype
     *                              - null:   Every searchable contenttype
     * @param array $filters Additional filters for contenttypes
     *                              - key is contenttype
     *                              - value is filter
     * @param integer $limit limit the number of results
     * @param integer $offset skip this number of results
     *
     * @return mixed false if query is invalid, an array with results if query was executed
     */
    public function searchContent($q, array $contenttypes = null, array $filters = null, $limit = 9999, $offset = 0)
    {
        $query = $this->decodeSearchQuery($q);
        if (!$query['valid']) {
            return false;
        }

        $appCt = $this->helperApp['config']->get('contenttypes');

        // By default we only search through searchable contenttypes
        if (is_null($contenttypes)) {
            $contenttypes = array_keys($appCt);
            $contenttypes = array_filter(
                $contenttypes,
                function ($ct) use ($appCt) {
                    if ((isset($appCt[$ct]['searchable']) && $appCt[$ct]['searchable'] === false) ||
                        (isset($appCt[$ct]['viewless']) && $appCt[$ct]['viewless'] === true)
                    ) {
                        return false;
                    }

                    return true;
                }
            );
            $contenttypes = array_map(
                function ($ct) use ($appCt) {
                    return $appCt[$ct]['slug'];
                },
                $contenttypes
            );
        }

        // Build our search results array
        $results = [];

        // First, attempt to search for the literal string, eg. "Lorum Ipsum"
        foreach ($contenttypes as $contenttype) {
            $ctconfig = $this->getContentType($contenttype);

            $fields = $ctconfig['fields'];
            $filter = null;

            if (is_array($filters) && isset($filters[$contenttype])) {
                $filter = $filters[$contenttype];
            }

            $subResults = $this->searchSingleContentType($query, $contenttype, $fields, $filter, true);

            $results = array_merge($results, $subResults);
        }

        // If that didn't produce results, search for "Lorum" or "Ipsum"
        if (empty($results)) {
            foreach ($contenttypes as $contenttype) {
                $ctconfig = $this->getContentType($contenttype);

                $fields = $ctconfig['fields'];
                $filter = null;

                if (is_array($filters) && isset($filters[$contenttype])) {
                    $filter = $filters[$contenttype];
                }

                $subResults = $this->searchSingleContentType($query, $contenttype, $fields, $filter, false);

                $results = array_merge($results, $subResults);
            }
        }

        // Sort the results
        usort($results, [$this, 'compareSearchWeights']);

        $noOfResults = count($results);

        $pageResults = [];
        if ($offset < $noOfResults) {
            $pageResults = array_slice($results, $offset, $limit);
        }

        return [
            'query' => $query,
            'no_of_results' => $noOfResults,
            'results' => $pageResults,
        ];
    }

    /**
     * Overwrite, because of private in parent.
     *
     * @param string $q
     *
     * @return array
     */
    private function decodeSearchQuery($q)
    {
        $words = preg_split('|[\r\n\t ]+|', trim($q));

        $words = array_map(
            function ($word) {
                return mb_strtolower($word);
            },
            $words
        );
        $words = array_filter(
            $words,
            function ($word) {
                return strlen($word) >= 2;
            }
        );

        return [
            'valid' => count($words) > 0,
            'in_q' => $q,
            'use_q' => implode(' ', $words),
            'sanitized_q' => strip_tags($q),
            'words' => $words,
        ];
    }

    /**
     * Overwrite, because of private in parent.
     *
     * Helper function to set the proper 'where' parameter,
     * when getting values like '<2012' or '!bob'.
     *
     * @param string $key
     * @param string $value
     * @param mixed $fieldtype
     *
     * @return string
     */
    private function parseWhereParameter($key, $value, $fieldtype = false)
    {
        $value = trim($value);

        // check if we need to split.
        if (strpos($value, " || ") !== false) {
            $values = explode(" || ", $value);
            foreach ($values as $index => $value) {
                $values[$index] = $this->parseWhereParameter($key, $value, $fieldtype);
            }

            return "( ".implode(" OR ", $values)." )";
        } elseif (strpos($value, " && ") !== false) {
            $values = explode(" && ", $value);
            foreach ($values as $index => $value) {
                $values[$index] = $this->parseWhereParameter($key, $value, $fieldtype);
            }

            return "( ".implode(" AND ", $values)." )";
        }

        // Set the correct operator for the where clause
        $operator = "=";

        $first = substr($value, 0, 1);

        if ($first == "!") {
            $operator = "!=";
            $value = substr($value, 1);
        } elseif (substr($value, 0, 2) == "<=") {
            $operator = "<=";
            $value = substr($value, 2);
        } elseif (substr($value, 0, 2) == ">=") {
            $operator = ">=";
            $value = substr($value, 2);
        } elseif ($first == "<") {
            $operator = "<";
            $value = substr($value, 1);
        } elseif ($first == ">") {
            $operator = ">";
            $value = substr($value, 1);
        } elseif ($first == "%" || substr($value, -1) == "%") {
            $operator = "LIKE";
        }

        // Use strtotime to allow selections like "< last monday" or "this year"
        if (in_array($fieldtype, ['date', 'datetime']) && ($timestamp = strtotime($value)) !== false) {
            $value = date('Y-m-d H:i:s', $timestamp);
        }

        $parameter = sprintf(
            "%s %s %s",
            $this->helperApp['db']->quoteIdentifier($key),
            $operator,
            $this->helperApp['db']->quote($value)
        );

        return $parameter;
    }

    /**
     * Overwrite, because of private in parent.
     *
     * @param \Bolt\Legacy\Content $a
     * @param \Bolt\Legacy\Content $b
     *
     * @return int
     */
    private function compareSearchWeights(Content $a, Content $b)
    {
        if ($a->getSearchResultWeight() > $b->getSearchResultWeight()) {
            return -1;
        }
        if ($a->getSearchResultWeight() < $b->getSearchResultWeight()) {
            return 1;
        }
        if ($a['datepublish'] > $b['datepublish']) {
            // later is more important
            return -1;
        }
        if ($a['datepublish'] < $b['datepublish']) {
            // earlier is less important
            return 1;
        }

        return strcasecmp($a['title'], $b['title']);
    }

}
