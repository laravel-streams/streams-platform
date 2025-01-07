<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Entry\Entry;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Streams\Core\Support\Traits\HasMemory;
use Streams\Core\Entry\Contract\EntryInterface;
use Streams\Core\Criteria\Contract\AdapterInterface;

/**
 * Adapters act as drivers to satisfy the
 * query building needs of the higher criteria.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    use Macroable;
    use HasMemory;

    protected $query;

    protected Stream $stream;

    public function getQuery()
    {
        return $this->query;
    }

    abstract public function orderBy($field, $direction = 'asc'): static;

    abstract public function limit($limit, $offset = 0): static;

    abstract public function where($field, $operator = null, $value = null, $nested = null): static;

    public function orWhere($field, $operator = null, $value = null): static
    {
        return $this->where($field, $operator, $value, 'or');
    }

    abstract public function get(array $parameters = []): Collection;

    abstract public function count(array $parameters = []): int;

    abstract public function delete(array $parameters = []): bool;

    abstract public function save(EntryInterface $entry): bool;

    abstract public function truncate(): void;

    protected function collect($entries): Collection
    {
        if ($entries instanceof Collection) {
            $entries = $entries->all();
        }

        $collection = $this->stream
            ->repository()
            ->newCollection();

        array_map(function ($entry) use ($collection) {
            try {
                $entry = $this->make($entry);
            } catch (\Throwable ) {
                $entry = $this->make($entry);
            }
            // @todo this is where all entries get stream info.
            // Maybe we do like __stream to prevent collision 
            //$entry->stream = $this->stream;
            $collection->push($entry);
        }, $entries);

        return $collection;
    }

    public function newInstance(array $attributes = []): EntryInterface
    {
        $prototype = $this->stream->config('abstract', Entry::class);

        unset($attributes['__created_at']);
        unset($attributes['__updated_at']);

        $prototype = new $prototype([
            'stream' => $this->stream,
        ]);
        
        // $prototype->setPrototypeProperties(
        //     Arr::keyBy($this->stream->getOriginalPrototypeAttributes()['fields'], 'handle')
        // );
        
        $this->fillDefaults($attributes);
        
        $prototype->setPrototypeAttributes($attributes);

        return $prototype;
    }

    /**
     * Return an entry interface from data.
     *
     * @param $entry
     * @return EntryInterface
     */
    protected function make($entry)
    {
        $data = $entry;

        if (!is_array($entry)) {
            $data = $entry->toArray();
        }

        $data = Arr::undot($data);
        
        unset($data['__created_at']);
        unset($data['__updated_at']);

        $keyName = $this->stream->config('key_name', 'id');

        if (method_exists($entry, 'getId')) {
            $id = $entry->getId();
        } else {
            $id = $data[$keyName];
        }

        $data = array_merge([$keyName => $id], $data);
        
        $prototype = $this->stream->config('abstract', Entry::class);

        unset($data['__created_at']);
        unset($data['__updated_at']);

        $entry = new $prototype([
            'stream' => $this->stream,
        ]);

        foreach ($data as $key => &$value) {
            
            if (!$field = $this->stream->fields->get($key)) {
                continue;
            }

            $value = is_null($value) ? $value : $field->restore($value);
        }

        $entry = $entry->setRawPrototypeAttributes($data);
        
        return $entry;
    }

    protected function callParameterMethods(array $parameters): void
    {
        foreach ($parameters as $key => $call) {

            $method = Str::camel($key);

            foreach ($call as $parameters) {
                call_user_func_array([$this, $method], $parameters);
            }
        }
    }

    protected function fillDefaults(array &$attributes): void
    {
        foreach ($this->stream->fields as $field) {

            if (!$default = $field->config('default')) {
                continue;
            }

            if (array_key_exists($field->handle, $attributes)) {
                continue;
            }

            $attributes[$field->handle] = is_null($default) ? null : $field->default($default);
        }
    }
}
