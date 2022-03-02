<?php

namespace Streams\Core\Field\Types;

use Illuminate\Support\Arr;
use Streams\Core\Field\Field;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Entry\Contract\EntryInterface;

class PolymorphicFieldType extends Field
{
    public function modify($value)
    {
        if ($value instanceof EntryInterface) {
            $value = [
                'type' => get_class($value),
                'data' => Arr::make($value),
            ];
        }

        return $value;
    }

    public function restore($value)
    {
        return $this->decorate($value);
    }

    public function expand($value)
    {
        if (is_object($value)) {
            return $value;
        }

        $stream = $this->entry->{$this->config('morph_type', $this->field . '_type')};
        $key = $this->entry->{$this->config('foreign_key', $this->field . '_id')};

        return Streams::repository($stream)->find($key);
    }
}
