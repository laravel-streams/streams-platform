<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;

class EloquentAdapter extends AbstractAdapter
{
    protected $query;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;

        $this->resetQuery();
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

    public function get(array $parameters = []): array
    {
        $this->resetQuery()->callParameterMethods($parameters);

        return $this->query->get()->all();
    }

    public function count(array $parameters = []): int
    {
        $this->resetQuery()->callParameterMethods($parameters);

        return $this->query->count();
    }

    public function save(array $attributes): array
    {
        $model = $this->stream->config('source.model');

        $model = new $model($attributes);

        $model->save();

        return $model->getAttributes();
    }

    public function delete(array $parameters = []): bool
    {
        $this->resetQuery()->callParameterMethods($parameters);

        return $this->query->delete();
    }

    public function truncate(): void
    {
        $this->query->truncate();
    }

    protected function resetQuery(): static
    {
        $model = $this->stream->config('source.model');

        $this->query = (new $model)->newQuery();

        return $this;
    }

    public function __call($method, $arguments = [])
    {
        $result = $this->query->$method(...$arguments);

        if (is_string($result)) {
            return $result;
        }

        $this->query = $result;

        return $this;
    }
}
