<?php

namespace Streams\Core\Field\Types;

use Illuminate\Support\Str;
use Streams\Core\Field\Field;
use Illuminate\Support\Collection;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Field\Schema\ArraySchema;
use Streams\Core\Support\Facades\Hydrator;
use Illuminate\Contracts\Support\Arrayable;
use Streams\Core\Entry\Contract\EntryInterface;
use Streams\Core\Field\Decorator\ArrayDecorator;
use Streams\Core\Field\Types\Validation\ValidateArrayItems;

class ArrayFieldType extends Field
{
    public $rules = [
        'array',
        'nullable',
        // 'valid_items' => ValidateArrayItems::class,
    ];

    public function cast($value)
    {
        if (is_string($value) && ($json = json_decode($value, true)) !== null) {
            $value = $json;
        }

        if (is_string($value) && Str::isSerialized($value)) {
            $value = unserialize($value);
        }

        if (is_string($value)) {
            $value = (array) $value;
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if ($wrapper = $this->config('wrapper')) {
            $value = $this->wrapArray($value, $wrapper);
        }

        return $value;
    }

    public function modify($value)
    {
        if (!is_array($value)) {
            $value = $this->cast($value);
        }

        foreach ($value ?? [] as &$item) {

            if (is_object($item) && $item instanceof EntryInterface) {
                $item = [
                    '@stream' => $item->stream()->id,
                ] + $item->getAttributes();
            }

            if (
                is_object($item) && ($item instanceof Arrayable
                || in_array(Prototype::class, class_uses($item)))
            ) {
                $item = array_merge([
                    '@abstract' => get_class($item),
                ], $item->toArray());
            }

            if (is_object($item)) {
                $item = Hydrator::dehydrate($item);
            }
        }

        return $value;
    }

    public function restore($value)
    {
        if (is_string($value)) {
            $value = $this->cast($value);
        }

        foreach ($value as $key => $item) {

            if (!is_array($item) && $stream = $this->config('related')) {
                
                $value[$key] = Streams::repository($stream)->find($item);
                
                continue;
            }
            
            if (!is_array($item)) {
                continue;
            }

            [$meta, $item] = $this->separateMeta($item);

            if (!$meta && $stream = $this->config('stream')) {

                // @todo gross
                if (is_array($stream)) {
                    $value[$key] = Streams::build($stream)->repository()->newInstance($item);
                } else {
                    $value[$key] = Streams::repository($stream)->newInstance($item);
                }

                continue;
            }

            // @todo eager loading/array of IDs
            if (!$meta && $stream = $this->config('related')) {

                $value[$key] = Streams::repository($stream)->find($item);

                continue;
            }

            if (isset($meta['@stream'])) {

                $value[$key] = $this->restoreStreamEntry($meta, $item);

                continue;
            }

            if (isset($meta['@abstract'])) {
                
                $value[$key] = $this->restoreInstance($meta, $item);

                continue;
            }
        }

        if ($wrapper = $this->config('wrapper')) {
            $value = $this->wrapArray($value, $wrapper);
        }
        
        return $value;
    }

    public function getSchemaName()
    {
        return ArraySchema::class;
    }

    public function getDecoratorName()
    {
        return ArrayDecorator::class;
    }

    public function generator()
    {
        return function () {
            for ($i = 0; $i < 10; $i++) {
                $values[] = fake()->randomElement([
                    // @todo Support types as defined in items
                    fake()->word(),
                    fake()->randomNumber(),
                ]);
            }
    
            return $values;
        };
    }

    protected function wrapArray($array, $wrapper)
    {
        if ($wrapper == 'collection') {
            return new Collection($array);
        }

        return new $wrapper($array);
    }

    protected function separateMeta(array $value)
    {
        $meta = preg_grep('/^\@/', array_keys($value));

        $meta = array_intersect_key($value, array_flip($meta));

        array_map(function ($key) use (&$value) {
            unset($value[$key]);
        }, array_keys($meta));

        return [$meta, $value];
    }

    protected function restoreStreamEntry(array $meta, array $value)
    {
        return Streams::repository($meta['@stream'])->newInstance($value);
    }

    protected function restoreInstance(array $meta, array $value)
    {
        return new $meta['@abstract']($value);
    }
}
