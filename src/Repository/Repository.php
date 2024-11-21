<?php

namespace Streams\Core\Repository;

use Illuminate\Support\Str;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Collection;
use Streams\Core\Criteria\Criteria;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Macroable;
use Streams\Core\Support\Traits\HasMemory;
use Streams\Core\Criteria\Adapter\FileAdapter;
use Streams\Core\Criteria\Adapter\SelfAdapter;
use Streams\Core\Entry\Contract\EntryInterface;
use Streams\Core\Support\Traits\FiresCallbacks;
use Streams\Core\Criteria\Adapter\DatabaseAdapter;
use Streams\Core\Criteria\Adapter\EloquentAdapter;
use Streams\Core\Criteria\Adapter\FilebaseAdapter;
use Streams\Core\Criteria\Adapter\CollectionAdapter;
use Streams\Core\Criteria\Adapter\FilesystemAdapter;
use Streams\Core\Repository\Contract\RepositoryInterface;

/**
 * This class is responsible for top level
 * abstraction of common criteria for entries.
 */
class Repository implements RepositoryInterface
{
    use Macroable;
    use HasMemory;
    use FiresCallbacks;

    public function __construct(protected Stream $stream)
    {
    }

    public function all(): Collection
    {
        return $this
            ->newCriteria()
            ->get();
    }

    public function find(string|int $id)
    {
        return $this
            ->newCriteria()
            ->find($id);
    }

    public function findAll(array $ids): Collection
    {
        $keyName = $this->stream->config('key_name', 'id');

        return $this
            ->newCriteria()
            ->where($keyName, 'IN', $ids)
            ->get();
    }

    public function findBy(string $field, $value): EntryInterface|null
    {
        return $this
            ->newCriteria()
            ->where($field, $value)
            ->first();
    }

    public function findAllWhere(string $field, $operator, $value = null): Collection
    {
        return $this
            ->newCriteria()
            ->where($field, $operator, $value)
            ->get();
    }

    public function count(): int
    {
        return $this
            ->newCriteria()
            ->count();
    }

    public function create(array $attributes): EntryInterface
    {
        return $this
            ->newCriteria()
            ->create($attributes);
    }

    public function save(EntryInterface $entry): bool
    {
        return (bool) $this
            ->newCriteria()
            ->save($entry);
    }

    public function delete(EntryInterface $entry): bool
    {
        $criteria = $this->newCriteria();

        $entry->fire('deleting', [
            'entry' => $entry,
        ]);

        $keyName = $this->stream->config('key_name', 'id');

        $criteria->where($keyName, $entry->{$keyName});

        return $criteria->delete();
    }

    public function truncate(): void
    {
        $this
            ->newCriteria()
            ->truncate();
    }

    public function newInstance(array $attributes = []): EntryInterface
    {
        return $this
            ->newCriteria()
            ->newInstance($attributes);
    }

    public function newCriteria(): Criteria
    {
        $default = Config::get('streams.core.default_source', 'filebase');

        $criteria = $this->stream->config('criteria') ?: Criteria::class;

        if ($adapter = $this->stream->config('source.adapter')) {
            $adapter = new $adapter($this->stream);
        }

        if (!$adapter) {

            $adapter = $this->stream->config('source.type', $default);

            if (class_exists($adapter)) {
                return new $criteria($this->stream, new $adapter($this->stream));
            }

            $adapter = Str::camel("new_{$adapter}_adapter");

            $adapter = $this->$adapter();
        }

        return new $criteria($this->stream, $adapter);
    }

    public function newCollection(array $entries = []): Collection
    {
        $collection = $this->stream->config('collection', Collection::class);

        return new $collection($entries);
    }

    public function newSelfAdapter(): SelfAdapter
    {
        return new SelfAdapter($this->stream);
    }

    public function newFileAdapter(): FileAdapter
    {
        return new FileAdapter($this->stream);
    }

    public function newFilesystemAdapter(): FilesystemAdapter
    {
        return new FilesystemAdapter($this->stream);
    }

    public function newFilebaseAdapter(): FilebaseAdapter
    {
        return new FilebaseAdapter($this->stream);
    }

    public function newDatabaseAdapter(): DatabaseAdapter
    {
        return new DatabaseAdapter($this->stream);
    }

    public function newEloquentAdapter(): EloquentAdapter
    {
        return new EloquentAdapter($this->stream);
    }

    public function newCollectionAdapter(): CollectionAdapter
    {
        return new CollectionAdapter($this->stream);
    }
}
