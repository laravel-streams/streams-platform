<?php

namespace Streams\Core\Field\Types;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Field\Field;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Support\Facades\Hydrator;
use Streams\Core\Field\Schema\ObjectSchema;
use Streams\Core\Entry\Contract\EntryInterface;
use Streams\Core\Field\Types\Validation\ValidateObjectType;

class ObjectFieldType extends Field
{
    public $rules = [
        'valid_type' => ValidateObjectType::class,
    ];

    public function modify($value): array
    {
        if (is_object($value) && $value instanceof EntryInterface) {
            $value = [
                '@stream' => $value->stream()->id,
            ] + $value->getAttributes();
        }

        if (
            is_object($value) && method_exists($value, 'getPrototypeAttribute')
        ) {
            $value = array_merge([
                '@prototype' => get_class($value),
            ], $value->toArray());
        }

        if (is_object($value)) {
            $value = array_merge([
                '@generic' => get_class($value),
            ], Hydrator::dehydrate($value));
        }

        return $value;
    }

    public function restore($value): object
    {
        if (is_object($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        [$meta, $value] = $this->separateMeta($value);

        if (isset($meta['@stream'])) {
            return $this->restoreEntry($meta, $value);
        }

        if (isset($meta['@prototype'])) {
            return $this->restorePrototype($meta, $value);
        }

        return $this->restoreGeneric($value, Arr::get($meta, '@generic', 'stdClass'));
    }

    public function cast($value): object
    {
        if (is_array($value)) {
            return (object) $value;
        }

        if (is_object($value)) {
            return $value;
        }

        if (is_string($value) && ($json = json_decode($value)) !== null) {
            return $json;
        }

        if (is_string($value) && Str::isSerialized($value)) {
            return unserialize($value);
        }

        throw new \Exception('Value cannot be cast to object.');
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

    protected function restoreEntry(array $meta, array $value)
    {
        return Streams::repository($meta['@stream'])->newInstance($value);
    }

    protected function restorePrototype(array $meta, array $value)
    {
        return new $meta['@prototype']($value);
    }

    protected function restoreGeneric(array $attributes, string $generic = 'stdClass')
    {
        $generic = new $generic;

        foreach ($attributes as $key => $value) {
            $generic->{$key} = $value;
        }

        return $generic;
    }

    public function getSchemaName()
    {
        return ObjectSchema::class;
    }
}
