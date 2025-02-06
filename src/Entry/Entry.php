<?php

namespace Streams\Core\Entry;

use Serializable;
use Carbon\Carbon;
use JsonSerializable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Streams\Core\Stream\Stream;
use Illuminate\Validation\Validator;
use Illuminate\Support\Traits\Macroable;
use Streams\Core\Support\Traits\Fluency;
use Streams\Core\Support\Facades\Streams;
use Illuminate\Contracts\Support\Jsonable;
use Streams\Core\Support\Traits\HasMemory;
use Illuminate\Contracts\Support\Arrayable;
use Streams\Core\Entry\Contract\EntryInterface;

class Entry implements
    JsonSerializable,
    EntryInterface,
    Serializable,
    Arrayable,
    Jsonable
{
    use Macroable {
        Macroable::__call as private callMacroable;
    }

    use Fluency {
        Fluency::__construct as private constructFluency;
    }

    use HasMemory;
    use Searchable;

    public ?Stream $stream;

    public function __construct(array $attributes = [])
    {
        $stream = Arr::pull($attributes, 'stream');

        if (is_string($stream)) {
            $stream = Streams::make($stream);
        }

        if ($this->stream = $stream) {
            $this->setPrototypeProperties($this->stream()->fields->toArray());
        }

        $this->constructFluency($attributes);
    }

    public function getIdAttribute()
    {
        $name = $this->stream()->config('key_name', 'id') ?? 'id';

        $value = $this->__prototype['attributes'][$name] ?? $this->getPrototypeAttributeDefault($name);

        return $value;
    }

    public function lastModified(): Carbon
    {
        return $this->once(__METHOD__, function () {

            // @todo this should be configured
            $datetime = $this->__updated_at;

            if (!$datetime instanceof \Datetime) {
                $datetime = new Carbon($datetime);
            }

            return $datetime;
        });
    }

    public function stream(): Stream
    {
        return $this->stream;
    }

    public function save(array $options = []): bool
    {
        return $this->stream()
            ->repository()
            ->save($this);
    }

    public function delete(): bool
    {
        return $this->stream()
            ->repository()
            ->delete($this);
    }

    public function validator(): Validator
    {
        return $this->stream()->validator($this);
    }

    public function toArray()
    {
        // @todo needs work
        $protected = $this->stream ? $this->stream()->fields->filter(function ($field) {
            return $field?->protected ? $field : null;
        })->keys()->toArray() : [];

        return array_diff_key($this->getAttributes(), array_flip(array_merge([
            '__prototype',
            '__created_at',
            '__updated_at',
        ], $protected)));
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function serialize()
    {
        return serialize($this->toArray());
    }

    public function unserialize($data)
    {
        $this->constructFluency(unserialize($data));
    }

    public function __toString()
    {
        return $this->toJson();
    }

    public function __call($method, $arguments)
    {
        if (static::hasMacro($method)) {
            return $this->callMacroable($method, $arguments);
        }

        $key = Str::snake($method);

        if ($this->hasPrototypeAttribute($key)) {
            return $this->decoratePrototypeAttribute($key);
        }

        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }
}
