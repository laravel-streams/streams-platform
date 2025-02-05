<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Streams\Core\Entry\Contract\EntryInterface;

class DatabaseAdapter extends AbstractAdapter
{
    protected $query;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;

        $this->initializeQuery();
    }

    protected function initializeQuery(): void
    {
        if (!$connection = $this->stream->config('source.connection')) {
            $connection = Config::get('database.default');
        }

        $this->query = DB::connection($connection)
            ->table($this->stream->config('source.table', $this->stream->id));
    }

    public function whereJsonContains(
        $column,
        $value,
        $boolean = 'and',
        $not = false
    ): static {
        $this->query->whereJsonContains($column, $value, $boolean, $not);

        return $this;
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

        // @todo needs work.
        if (strtoupper($operator) == 'IN') {

            if (!$nested) {
                $this->query->whereIn($field, $value);
            } else {
                $this->query->orWhereIn($field, $value);
            }

            return $this;
        }

        if (strtoupper($operator) == 'NOT IN') {

            if (!$nested) {
                $this->query->whereNotIn($field, $value);
            } else {
                $this->query->orWhereNotIn($field, $value);
            }

            return $this;
        }

        $method = Str::studly($nested ? $nested . '_where' : 'where');

        $this->query = $this->query->{$method}($field, $operator, $value);

        return $this;
    }

    public function get(array $parameters = []): array
    {
        $this->callParameterMethods($parameters);

        return $this->query->get()->all();
    }

    public function count(array $parameters = []): int
    {
        $this->callParameterMethods($parameters);

        $count = $this->query->count();

        $this->initializeQuery();

        return $count;
    }

    public function save(array $attributes): array
    {
        $keyName = $this->stream->config('key_name', 'id');

        $id = Arr::get($attributes, $keyName);

        if ($id) {
            $this->query->where($keyName, $id);
        }
        
        if ($id && $this->query->exists()) {
            
            $this->query->update($attributes);

            return $attributes;
        } elseif ($keyName === false) {
            
            $this->query->insert($attributes);

            return $attributes;
        }

        $id = $this->query->insertGetId($attributes);

        $attributes[$keyName] = $id;

        return $attributes;
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

    public function __call($method, $arguments = [])
    {
        $this->query = $this->query->$method(...$arguments);
        
        return $this;
    }
}
