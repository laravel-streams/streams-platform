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

    abstract public function get(array $parameters = []): array;

    abstract public function count(array $parameters = []): int;

    abstract public function delete(array $parameters = []): bool;

    abstract public function save(array $attributes): array;

    abstract public function truncate(): void;

    protected function callParameterMethods(array $parameters): void
    {
        foreach ($parameters as $key => $call) {

            $method = Str::camel($key);

            foreach ($call as $parameters) {
                call_user_func_array([$this, $method], $parameters);
            }
        }
    }
}
