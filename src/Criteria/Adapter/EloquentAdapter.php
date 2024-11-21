<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Streams\Core\Entry\Contract\EntryInterface;

class EloquentAdapter extends AbstractAdapter
{
    protected $query;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;

        $model = $stream->config('source.model');

        $stream->config('abstract', $model);

        $this->query = (new $model)->newQuery();
    }

    public function orderBy($field, $direction = 'asc'): static
    {
        $this->query = $this->query->orderBy($field, $direction);

        return $this;
    }

    public function limit($limit, $offset = 0): static
    {
        $this->query = $this->query->take($limit)->skip($offset);

        return $this;
    }

    public function where($field, $operator = null, $value = null, $nested = null): static
    {
        if (!$value) {
            $value = $operator;
            $operator = '=';
        }

        $method = Str::studly($nested ? $nested . '_where' : 'where');

        if (strtoupper($operator) == 'IN') {

            $method = $method . 'In';

            $this->query = $this->query->{$method}($field, $value);
        } elseif (strtoupper($operator) == 'NOT IN') {

            $method = $method . 'NotIn';

            $this->query = $this->query->{$method}($field, $value);
        } else {
            $this->query = $this->query->{$method}($field, $operator, $value);
        }

        return $this;
    }

    public function withTrashed($toggle): static
    {
        if ($toggle) {
            $this->query = $this->query->withTrashed();
        }

        return $this;
    }

    public function whereJsonContains($column, $value, $boolean = 'and', $not = false): static
    {
        $this->query->whereJsonContains($column, $value, $boolean, $not);

        return $this;
    }
    
    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and')
    {
        $this->query->whereJsonLength($column, $operator, $value, $boolean);

        return $this;
    }

    public function whereFullText($columns, $value, array $options = [], $boolean = 'and'): static
    {
        $this->query->whereFullText($columns, $value, $options, $boolean);

        return $this;
    }

    public function groupBy(...$groups): static
    {
        $this->query->groupBy(...$groups);

        return $this;
    }

    public function join($stream, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $this->query->join($stream, $first, $operator, $second, $type, $where);

        return $this;
    }

    public function select($columns = ['*']): static
    {
        $this->query = $this->query->select($columns);

        return $this;
    }

    public function with($relations): static
    {
        $this->query = $this->query->with($relations);

        return $this;
    }

    public function get(array $parameters = []): Collection
    {
        $this->callParameterMethods($parameters);

        return $this->collect($this->query->get());
    }

    public function count(array $parameters = []): int
    {
        $this->callParameterMethods($parameters);

        return $this->query->count();
    }

    public function save($entry): bool
    {
        return $entry->save();
    }

    public function delete(array $parameters = []): bool
    {
        $this->callParameterMethods($parameters);

        return $this->query->delete();
    }

    public function truncate(): void
    {
        $this->query->truncate();
    }

    protected function make($entry): EntryInterface
    {
        return $entry;
    }

    public function newInstance(array $attributes = []): EntryInterface
    {
        $model = $this->stream->config('source.model');

        $this->fillDefaults($attributes);

        $model = new $model($attributes);

        return $model;
    }
}
