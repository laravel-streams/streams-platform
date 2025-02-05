<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Streams\Core\Entry\Contract\EntryInterface;

class CollectionAdapter extends AbstractAdapter
{
    public Collection $data;

    protected Stream $stream;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;

        $this->loadData();
    }

    protected function loadData(Collection $collection = null)
    {
        $this->data = $collection ?: new Collection();
    }

    public function orderBy($field, $direction = 'asc'): static
    {
        $this->data = $this->data->sortBy($field, SORT_REGULAR, $direction === 'desc');

        return $this;
    }

    public function limit($limit, $offset = 0): static
    {
        $this->data = $this->data->slice($offset, $limit);

        return $this;
    }

    public function where($field, $operator = null, $value = null, $nested = null): static
    {
        if (!$value) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtoupper($operator);

        $method = $nested ? Str::camel($nested . '_where') : 'where';

        if ($operator == 'LIKE') {
            $this->data = $this->data->filter(function ($entry) use ($field, $value) {
                return strpos(
                    strtolower(data_get($entry, $field)),
                    strtolower(str_replace('%', '', $value))
                ) !== false;
            });
        } elseif ($operator == 'CONTAINS') {
            $this->data = $this->data->filter(function ($entry) use ($field, $value) {
                return in_array($value, $entry->{$field}) > 0;
            });
        } elseif ($operator == 'IN') {
            $this->data = $this->data->whereIn($field, $value);
        } elseif ($operator == 'NOT IN') {
            $this->data = $this->data->whereNotIn($field, $value);
        } else {
            $this->data = $this->data->{$method}($field, $operator, $value);
        }

        return $this;
    }

    public function orWhere($field, $operator = null, $value = null): static
    {
        return $this->where($field, $operator, $value, 'or');
    }

    public function get(array $parameters = []): array
    {
        $this->query = $this->collect($this->data);

        $this->callParameterMethods($parameters);

        return $this->query->all();
    }

    public function count(array $parameters = []): int
    {
        return count($this->get($parameters));
    }

    public function save(array $attributes): array
    {
        $keyName = $this->stream->config('key_name', 'id');

        $key = Arr::get($attributes, $keyName);

        $fields = $this->stream->fields->keys()->all();

        $fields = array_combine($fields, array_fill(0, count($fields), null));

        $this->data[$key] = array_merge($fields, $attributes);
        
        return $attributes;
    }

    public function delete(array $parameters = []): bool
    {
        $keyName = $this->stream->config('key_name', 'id');

        $this->get($parameters)->each(function ($entry) use ($keyName) {
            unset($this->data[$entry->{$keyName}]);
        });

        return true;
    }

    public function truncate(): void
    {
        $this->data = [];
    }
}
