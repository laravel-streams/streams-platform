<?php

namespace Streams\Core\Criteria\Adapter;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Streams\Core\Entry\Contract\EntryInterface;

class FileAdapter extends AbstractAdapter
{
    protected $original = [];
    protected $data = [];
    protected $query;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;

        $this->readData();
    }

    public function orderBy($field, $direction = 'asc'): static
    {
        $this->query = $this->query->sortBy($field, SORT_REGULAR, strtolower($direction) === 'desc' ? true : false);

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

        $method = $nested ? Str::camel($nested . '_where') : 'where';

        if ($operator == 'LIKE') {
            $this->query = $this->query->filter(function ($entry) use ($field, $value) {
                return strpos(
                    strtolower(data_get($entry, $field)),
                    strtolower(str_replace('%', '', $value))
                ) !== false;
            });
        } elseif ($operator == 'CONTAINS') {
            $this->query = $this->query->filter(function ($entry) use ($field, $value) {
                return in_array($value, $entry->{$field}) > 0;
            });
        } elseif ($operator == 'IN') {
            $this->query = $this->query->whereIn($field, $value);
        } elseif ($operator == 'NOT IN') {
            $this->query = $this->query->whereNotIn($field, $value);
        } else {
            $this->query = $this->query->{$method}($field, $operator, $value);
        }

        return $this;
    }

    public function get(array $parameters = []): array
    {
        $this->query = collect($this->data);

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

        $format = $this->stream->config('source.format', 'json') ?: 'json';

        if ($format == 'csv') {

            $fields = $this->stream->fields->keys()->all();

            $fields = array_combine($fields, array_fill(0, count($fields), null));

            $this->data[$key] = array_merge($fields, $attributes);
        }

        if (in_array($format, ['json'])) {
            $this->data[$key] = $attributes;
        }

        $this->writeData();

        return $attributes;
    }

    public function delete(array $parameters = []): bool
    {
        $keyName = $this->stream->config('key_name', 'id');

        $this->get($parameters)->each(function ($entry) use ($keyName) {
            unset($this->data[$entry->{$keyName}]);
        });

        $this->writeData();

        return true;
    }

    public function truncate(): void
    {
        $this->data = [];

        $this->writeData();
    }

    protected function make($entry): EntryInterface
    {
        return $this->newInstance($entry);
    }

    protected function readData()
    {
        $format = $this->stream->config('source.format');
        $file = $this->stream->config('source.file', Config::get('streams.core.data_path') . '/' . $this->stream->handle . '.' . ($format ?: 'json'));

        if (!file_exists($file)) {
            $file = base_path($file);
        }
        
        $format = $format ?: pathinfo($file, PATHINFO_EXTENSION);

        if (!file_exists($file)) {

            $this->data = $this->original = [];

            return;
        }

        $keyName = $this->stream->config('key_name', 'id');

        if ($format == 'json') {

            $data = json_decode(file_get_contents($file), true);

            array_walk($data, function ($item, $key) use ($keyName) {

                $key = Arr::get($item, $keyName, $key);

                $this->data[$key] = [$keyName => $key] + $item;
            });
        }

        if ($format == 'csv') {

            $handle = fopen($file, 'r');

            $i = 0;

            $fields = [];

            while (($row = fgetcsv($handle)) !== false) {

                if ($i == 0) {
                    $fields = $row;
                    $i++;
                    continue;
                }

                $row = array_combine($fields, $row);

                foreach ($row as $key => $value) {
                    if (!is_numeric($value) && $json = json_decode($value)) {
                        $row[$key] = $json;
                    }
                }

                $key = Arr::get($row, $keyName, $i + 1);

                $this->data[$key] = [$keyName => $key] + $row;

                $i++;
            }

            fclose($handle);
        }
    }

    protected function writeData()
    {
        $format = $this->stream->config('source.format', 'json');

        $file = base_path(trim($this->stream->config('source.file', Config::get('streams.core.data_path') . '/' . $this->stream->handle . '.' . ($format ?: 'json')), '/\\'));

        $keyName = $this->stream->config(' ', 'id');

        $data = [];

        array_walk($this->data, function ($item, $key) use (&$data, $keyName) {

            $key = Arr::get($item, $keyName) ?: $key;
            
            $data[(string) $key] = $item;
        });

        $this->data = $data;
        
        if (!file_exists($file)) {
            File::ensureDirectoryExists(dirname($file), 0755, true);
        }

        if ($format == 'json') {
            file_put_contents($file, json_encode($this->data, JSON_PRETTY_PRINT));
        }

        if ($format == 'csv') {

            $handle = fopen($file, 'w');

            if (!$fieldNames = $this->stream->fields->keys()->all()) {
                 $fieldNames = array_keys(reset($this->data));
            }

            fputcsv($handle, $fieldNames);

            array_map(function ($item) use ($handle) {

                foreach ($item as $key => $value) {
                    if (is_array($value)) {
                        $item[$key] = json_encode($value);
                    }
                }

                fputcsv($handle, $item);
            }, $this->data);

            fclose($handle);
        }

        return true;
    }
}
