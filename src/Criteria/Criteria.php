<?php

namespace Streams\Core\Criteria;

use Illuminate\Support\Arr;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Traits\Macroable;
use Streams\Core\Support\Traits\HasMemory;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Streams\Core\Entry\Contract\EntryInterface;
use Streams\Core\Criteria\Contract\AdapterInterface;
use Illuminate\Pagination\Paginator as SimplePaginator;

/**
 * Criteria serve as the abstraction layer
 * for building queries. The logic is passed
 * through to the adapters to handle the storage
 * specific strategies of applying the query logic.
 */
class Criteria
{

    use HasMemory;

    use Macroable {
        Macroable::__call as private callMacroable;
    }

    protected array $parameters = [];

    protected Stream $stream;

    protected array $eagerLoad = [];

    public AdapterInterface $adapter;

    public function __construct(
        Stream $stream,
        AdapterInterface $adapter,
        array $parameters = []
    ) {
        $this->parameters = $parameters;
        $this->adapter = $adapter;
        $this->stream = $stream;
    }

    public function find(string|int $id)
    {
        $keyName = $this->stream->config('key_name', 'id');

        return $this
            ->where($keyName, $id)
            ->first();
    }

    public function first()
    {
        if (!isset($this->parameters['limit'])) {
            $this->limit(1);
        }

        return $this->get()->first();
    }

    public function firstOrCreate(array $attributes)
    {
        $result = $this->first();

        return $result ?: $this->create($attributes);
    }

    public function cache(int $seconds = null, string $key = null)
    {
        $seconds = $seconds ?: $this->stream->config('cache.ttl', 60 * 60);

        $this->parameters['cache'] = [$seconds, $key];

        return $this;
    }

    public function fresh()
    {
        $this->parameters['cache'] = false;

        return $this;
    }

    public function orderBy($field, $direction = 'asc')
    {
        $this->parameters['order_by'][] = [$field, $direction];

        return $this;
    }

    public function limit(int $limit, int $offset = 0)
    {
        $this->parameters['limit'][] = [$limit, $offset];

        return $this;
    }

    public function where(
        string $field,
        string $operator = null,
        $value = null,
        string $nested = null
    ) {
        $hash = md5(serialize([$field, $operator, $value, $nested]));

        $this->parameters['where'][$hash] = [$field, $operator, $value, $nested];

        return $this;
    }

    public function orWhere(string $field, string $operator = null, $value = null)
    {
        $this->where($field, $operator, $value, 'or');

        return $this;
    }

    public function get(): Collection
    {
        $enabled = $this->stream->config('cache.enabled', false);

        if ($enabled && !isset($this->parameters['cache'])) {
            $this->cache();
        }

        $cache = Arr::pull($this->parameters, 'cache');

        if ($cache) {

            $fingerprint = $this->stream->handle . '.query__' . md5(serialize($this->parameters));

            $seconds = $cache[0];
            $key = Arr::get($cache, 1);

            return $this->stream->cache()->remember($key ?: $fingerprint, $seconds, function () {
                
                $results = $this->eagerLoadRelations($this->adapter->get($this->parameters));

                $this->parameters = [];

                return $results;
            });
        }

        $results = $this->eagerLoadRelations($this->adapter->get($this->parameters));

        $this->parameters = [];

        return $results;
    }

    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $this->limit($count, ($page - 1) * $count)->get();

            Arr::pull($this->parameters, 'limit');

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    public function count()
    {
        $enabled = $this->stream->config('cache.enabled', false);

        if ($enabled && !isset($this->parameters['cache'])) {
            $this->cache();
        }

        $cache = Arr::get($this->parameters, 'cache');

        if ($cache) {

            $fingerprint = $this->stream->id . '.query.count__' . md5(serialize($this->parameters));

            return $this->stream->cache()->remember(Arr::get($cache, 1) ?: $fingerprint, $cache[0], function () {
                return $this->adapter->count(array_diff_key($this->parameters, array_flip(['cache'])));
            });
        }

        $count =  $this->adapter->count($this->parameters);

        $this->parameters = [];

        return $count;
    }

    public function create(array $attributes = [])
    {
        $this->stream->cache()->flush();

        $entry = $this->newInstance($attributes);

        $entry->fire('creating', [
            'entry' => $entry,
        ]);

        $entry->save();

        $entry->fire('created', [
            'entry' => $entry,
        ]);

        return $entry;
    }

    public function save(EntryInterface $entry)
    {
        $this->stream->cache()->flush();

        $entry->fire('saving', [
            'entry' => $entry,
        ]);

        $result = $this->adapter->save($entry);

        $entry->fire('saved', [
            'entry' => $entry,
        ]);

        return (bool) $result;
    }

    public function delete(): bool
    {
        $this->stream->cache()->flush();

        return $this->adapter->delete($this->parameters);
    }

    public function truncate(): void
    {
        $this->stream->cache()->flush();

        $this->adapter->truncate();
    }

    public function paginate(array|int $parameters = []): Paginator
    {
        if (is_numeric($parameters)) {
            $parameters = [
                'per_page' => $parameters,
            ];
        }

        $path = Request::url();

        $page = Arr::get($parameters, 'page');
        $total = Arr::get($parameters, 'total');
        $perPage = Arr::get($parameters, 'per_page');
        $pageName = Arr::get($parameters, 'page_name', 'page');
        $limitName = Arr::get($parameters, 'limit_name', 'limit');
        $simple = Arr::get($parameters, 'simple', false);

        if (!$simple && !$total) {
            $total = $this->count();
        }

        if (!$page) {
            $page = (int) Request::get($pageName, 1);
        }

        if (!$perPage) {
            $perPage = (int) Request::get($limitName, $perPage) ?: 25;
        }

        $offset = $page * $perPage - $perPage;

        $entries = $this->limit($perPage, $offset)->get();

        if ($simple) {

            $paginator = new SimplePaginator(
                $entries,
                $perPage,
                $page,
                [
                    'path' => $path,
                    'pageName' => $pageName,
                ]
            );

            $paginator->hasMorePagesWhen($entries->isNotEmpty());
        } else {

            $paginator = new LengthAwarePaginator(
                $entries,
                $total,
                $perPage,
                $page,
                [
                    'path' => $path,
                    'pageName' => $pageName,
                ]
            );
        }

        $paginator->appends(Request::all());

        return $paginator;
    }

    public function with($relations = []): Criteria
    {
        $relations = (array) $relations;

        foreach ($relations as $relation) {
            $this->eagerLoad[$relation] = $relation;
        }

        return $this;
    }

    protected function eagerLoadRelations(Collection $entries): Collection
    {
        foreach ($this->eagerLoad as $relation) {
            $this->eagerLoadRelation($relation, $entries);
        }

        return $entries;
    }

    protected function eagerLoadRelation(string $relation, Collection $entries): Collection
    {
        $ids = $entries->map(function ($entry) use ($relation) {
            return $entry->{$relation};
        })->filter()->all();

        $related = $this->stream->fields->get($relation)->related();

        $keyName = $related->config('key_name', 'id');

        $relatives = $related->entries()
            ->where($keyName, 'IN', $ids)
            ->get();

        $entries->each(function ($entry) use ($relation, $keyName, $relatives) {
            if ($relative = $relatives->where($keyName, $entry->{$relation})->first()) {
                $entry->{$relation} = $relative;
            }
        });

        return $entries;
    }

    public function newInstance(array $attributes = [])
    {
        return $this->adapter->newInstance($attributes);
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters = [])
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function __call($method, $arguments = [])
    {
        if (static::hasMacro($method)) {
            return $this->callMacroable($method, $arguments);
        }

        $this->parameters[$method][md5(serialize($arguments))] = $arguments;

        return $this;
    }
}
