<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;

class FilesystemAdapter extends AbstractAdapter
{
    protected Filesystem $disk;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;

        $this->disk = Storage::disk($stream->config('source.disk', 'local'));
    }

    public function orderBy($field, $direction = 'asc'): static
    {
        $this->query = $this->query->sortBy(
            $field,
            SORT_REGULAR,
            strtolower($direction) === 'desc' ? true : false
        );

        return $this;
    }

    public function limit($limit, $offset = 0): static
    {
        $this->query = $this->query->slice($offset, $limit);

        return $this;
    }

    public function where($field, $operator = null, $value = null, $nested = null): static
    {
        if (!$value) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtoupper($operator);

        $method = $nested ? Str::studly($nested . '_where') : 'where';

        if ($operator == 'LIKE') {
            $this->query = $this->query->filter(function ($entry) use ($field, $value) {
                return strpos(
                    strtolower(data_get($entry, $field)),
                    strtolower(str_replace('%', '', $value))
                ) !== false;
            });
        } elseif ($operator == 'IN') {
            $this->query = $this->query->whereIn($field, $value);
        } else {
            $this->query = $this->query->{$method}($field, $operator, $value);
        }

        return $this;
    }

    public function get(array $parameters = []): Collection
    {
        $this->query = $this->collect($this->disk->allFiles());

        $this->callParameterMethods($parameters);

        return $this->query;
    }

    public function count(array $parameters = []): int
    {
        $this->query = $this->collect($this->disk->allFiles());

        $this->callParameterMethods($parameters);

        return $this->query->count();
    }

    public function save(array $attributes): array
    {
        $keyName = $this->stream->config('key_name', 'id');

        /**
         * Remove these protected
         * and automated attributes.
         */
        Arr::pull($attributes, 'id');
        Arr::pull($attributes, 'stream');
        Arr::pull($attributes, 'created_at');
        Arr::pull($attributes, 'updated_at');

        $this->query
            ->get($attributes[$keyName])
            ->save($attributes);

        return $attributes;
    }

    public function delete(array $parameters = []): bool
    {
        $this->callParameterMethods($parameters);

        $this->query->delete();

        return true;
    }

    public function truncate(): void
    {
        $this->query->truncate();
    }

    protected function make($entry)
    {
        return ['path' => $entry];
    }
}
