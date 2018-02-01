<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater\Twig;

use Bolt\Extension\Gigabit\SearchableRepeater\Helpers\ContentValuesTrait;


/**
 * Class Excerpt
 *
 * @author Thomas Helmrich <thomas@gigabit.de>
 */
class Excerpt
{
    use ContentValuesTrait;

    public $contenttype;
    public $values = [];
    public $app;

    public function __construct($contenttype, $values, $app)
    {
        $this->app = $app;
        $this->contenttype = $contenttype;
        $this->values = $values;
    }
}