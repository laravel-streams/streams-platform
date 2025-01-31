<?php

namespace Streams\Core\Criteria\Contract;

use Illuminate\Support\Collection;
use Streams\Core\Entry\Contract\EntryInterface;

interface AdapterInterface
{

    /**
     * Add criteria for sorting entries.
     *
     * @param string $field
     * @param string|null $direction
     * @param string|null $value
     * @return $this
     */
    public function orderBy($field, $direction = 'asc');

    /**
     * Limit the entries returned.
     *
     * @param int $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit($limit, $offset = 0);

    /**
     * Add criteria for returning entries.
     *
     * @param string $field
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function where($field, $operator = null, $value = null, $nested = null);

    /**
     * Add nested criteria for returning entries.
     *
     * @param string $field
     * @param string|null $operator
     * @param string|null $value
     * @return $this
     */
    public function orWhere($field, $operator = null, $value = null);

    /**
     * Get the criteria results.
     * 
     * @param array $parameters
     * @return Collection
     */
    public function get(array $parameters = []): Collection;

    /**
     * Count the criteria results.
     * 
     * @return int
     */
    public function count();

    /**
     * Save attributes to the database.
     *
     * @param  array $attributes
     * @return array of saved attributes
     */
    public function save(array $attributes): array;

    /**
     * Delete an entry.
     *
     * @param array $parameters
     * @return bool
     */
    public function delete(array $parameters = []);

    /**
     * Delete all entries.
     *
     * @return void
     */
    public function truncate();

    /**
     * Return a new entry instance.
     *
     * @param array $attributes
     * @return EntryInterface
     */
    public function newInstance(array $attributes = []);
}
