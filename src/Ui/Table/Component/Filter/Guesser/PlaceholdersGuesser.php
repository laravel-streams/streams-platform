<?php

namespace Anomaly\Streams\Platform\Ui\Table\Component\Filter\Guesser;

use Anomaly\Streams\Platform\Ui\Table\TableBuilder;

/**
 * Class PlaceholdersGuesser
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class PlaceholdersGuesser
{

    /**
     * Guess some table table filter placeholders.
     *
     * @param TableBuilder $builder
     */
    public static function guess(TableBuilder $builder)
    {
        $filters = $builder->getFilters();
        $stream  = $builder->getTableStream();

        $module = app('module.collection')->active();

        foreach ($filters as &$filter) {

            // Skip if we already have a placeholder.
            if (isset($filter['placeholder'])) {
                continue;
            }

            // Get the placeholder off the field.
            if ($stream && $field = $stream->getField(array_get($filter, 'field'))) {

                /*
                 * Always use the field name
                 * as the placeholder. Placeholders
                 * that are assigned otherwise usually
                 * feel out of context:
                 *
                 * "Choose an option..." in the filter
                 * would just be weird.
                 */
                $placeholder = $field->name;

                if (trans()->has($placeholder)) {
                    $filter['placeholder'] = $placeholder;
                }
            }

            if (!$module) {
                continue;
            }

            $placeholder = $module->getNamespace('field.' . $filter['slug'] . '.placeholder');

            if (!isset($filter['placeholder']) && trans()->has($placeholder)) {
                $filter['placeholder'] = $placeholder;
            }

            $placeholder = $module->getNamespace('field.' . $filter['slug'] . '.name');

            if (!isset($filter['placeholder']) && trans()->has($placeholder)) {
                $filter['placeholder'] = $placeholder;
            }

            if (!array_get($filter, 'placeholder')) {
                $filter['placeholder'] = $filter['slug'];
            }

            if (
                !trans()->has($filter['placeholder'])
                && config('streams.system.lazy_translations')
            ) {
                $filter['placeholder'] = ucwords(humanize($filter['placeholder']));
            }
        }

        $builder->setFilters($filters);
    }
}
