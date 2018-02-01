<?php

namespace Bolt\Extension\Gigabit\SearchableRepeater\Helpers;

use Bolt\Helpers\Excerpt;
use Bolt\Storage\Entity\ContentValuesTrait as BaseContentValuesTrait;
use Bolt\Storage\Entity\FieldValue;
use Bolt\Storage\Field\Collection\RepeatingFieldCollection;
use Twig\Markup;

/**
 * Extends ContentValuesTrait including field type repaeter for the excerpt
 *
 * @author Thomas Helmrich <thomas@gigabit.de>
 */
trait ContentValuesTrait
{

    use BaseContentValuesTrait {
        BaseContentValuesTrait::getExcerpt as getRepeaterExcerpt;
    }

    /**
     * Create an excerpt for the content.
     *
     * @param int $length
     * @param bool $includeTitle
     * @param string|array $focus
     *
     * @return Markup
     */
    public function getExcerpt($length = 200, $includeTitle = false, $focus = null)
    {
        return $this->getRepeaterExcerpt($length, $includeTitle, $focus);
    }

    /**
     * Create an excerpt for the content including repeater field type
     *
     * @param int $length
     * @param bool $includeTitle
     * @param string|array $focus
     *
     * @return Markup
     */
    public function getRepeaterExcerpt($length = 200, $includeTitle = false, $focus = null)
    {
        $excerptParts = [];

        if (!empty($this->contenttype['fields'])) {
            foreach ($this->contenttype['fields'] as $key => $field) {
                // Skip empty fields, and fields used as 'title'.
                if (!isset($this->values[$key]) || in_array($key, $this->getTitleColumnName())) {
                    continue;
                }
                // add 'text', 'html' and 'textarea' fields.
                if (in_array($field['type'], ['text', 'html', 'textarea'])) {
                    $excerptParts[] = $this->values[$key];
                }
                // add 'markdown' field
                if ($field['type'] === 'markdown') {
                    $excerptParts[] = $this->app['markdown']->text($this->values[$key]);
                }
                // add 'repeater' field
                if ($field['type'] === 'repeater') {
                    /** @var RepeatingFieldCollection $repeater */
                    $repeater = $this->values[$key];
                    /** @var FieldValue $repeatField */
                    foreach ($repeater->flatten() as $repeatField) {
                        if (in_array($repeatField->getFieldType(), ['text', 'html', 'textarea'])) {
                            $excerptParts[] = $repeatField->getValue();
                        }
                        if ($repeatField->getFieldType() === 'markdown') {
                            $excerptParts[] = $this->app['markdown']->text($repeatField->getValue());
                        }
                    }
                }
            }
        }

        $excerpter = new Excerpt(implode(' ', $excerptParts), $this->getTitle());
        $excerpt = $excerpter->getExcerpt($length, $includeTitle, $focus);

        return new Markup($excerpt, 'UTF-8');
    }

}
