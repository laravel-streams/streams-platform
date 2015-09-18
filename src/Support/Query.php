<?php

namespace Anomaly\Streams\Platform\Support;

use Anomaly\Streams\Platform\Entry\Contract\EntryInterface;
use Anomaly\Streams\Platform\Entry\EntryCollection;
use Anomaly\Streams\Platform\Entry\EntryModel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class Query
{
    /**
     * These are methods that are
     * protected and may not be
     * accessed during plugin use.
     *
     * @var array
     */
    protected $protectedMethods = [
        'update',
        'delete',
    ];

    /**
     * The hydrator utility.
     *
     * @var Hydrator
     */
    protected $hydrator;

    /**
     * The service container.
     *
     * @var Container
     */
    protected $container;

    /**
     * Create a new StreamPluginFunctions instance.
     *
     * @param Hydrator  $hydrator
     * @param Container $container
     */
    public function __construct(Hydrator $hydrator, Container $container)
    {
        $this->hydrator  = $hydrator;
        $this->container = $container;
    }

    /**
     * Return a collection of results.
     *
     * @param array $parameters
     * @return EntryCollection
     */
    public function get(array $parameters)
    {
        return $this->build($parameters)->get();
    }

    /**
     * Return a result.
     *
     * @param array $parameters
     * @return EntryInterface|EntryModel
     */
    public function first(array $parameters)
    {
        return $this->build($parameters)->first();
    }

    /**
     * Return a paginated result.
     *
     * @param array $parameters
     * @return LengthAwarePaginator
     */
    public function paginate(array $parameters)
    {
        $perPage = array_pull($parameters, 'per_page', 15);

        return $this->build($parameters)->paginate($perPage);
    }

    /**
     * Create a new resource.
     *
     * @param array $parameters
     */
    public function create(array $parameters)
    {
        $model = $this->getModel($parameters);

        return $model->create($parameters);
    }

    /**
     * Update a resource.
     *
     * @param array $parameters
     */
    public function update(array $parameters)
    {
        $attributes = array_pull($parameters, 'attributes');

        if ($entry = $this->first($parameters)) {
            return $entry->update($attributes);
        }

        return false;
    }

    /**
     * Delete a resource.
     *
     * @param array $parameters
     */
    public function delete(array $parameters)
    {
        if ($entry = $this->first($parameters)) {
            return $entry->delete();
        }

        return false;
    }

    /**
     * Build a query.
     *
     * @param array  $parameters
     * @param string $fetch
     * @return array|Builder
     */
    protected function build(array $parameters)
    {
        // Get the model instance.
        $model = $this->getModel($parameters);

        /* @var Builder $query */
        $query = $model->newQuery();

        /*
         * First apply any desired scope.
         */
        if ($scope = array_pull($parameters, 'scope')) {
            call_user_func([$query, camel_case($scope)], array_pull($parameters, 'scope_arguments', []));
        }

        /*
         * Lastly we need to loop through all of the
         * parameters and assume the rest are methods
         * to call on the query builder.
         */
        foreach ($parameters as $method => $arguments) {
            $method = camel_case($method);

            if (in_array($method, $this->protectedMethods)) {
                throw new \Exception("The [{$method}] method is not allowed!");
            }

            if (is_array($arguments)) {
                call_user_func_array([$query, $method], $arguments);
            } else {
                call_user_func_array([$query, $method], [$arguments]);
            }
        }

        return $query;
    }

    /**
     * Get the model from the parameters.
     *
     * @param array $parameters
     * @return EntryModel
     * @throws \Exception
     */
    protected function getModel(array &$parameters)
    {
        /*
         * Determine the model by either using
         * the passed model class string OR
         * building one with namespace / stream.
         */
        if (! $model = array_pull($parameters, 'model')) {
            $stream    = ucfirst(camel_case(array_pull($parameters, 'stream')));
            $namespace = ucfirst(camel_case(array_pull($parameters, 'namespace')));

            if (! $stream || ! $namespace) {
                throw new \Exception('You must provide a model or stream and namespace parameter');
            }

            $model = 'Anomaly\Streams\Platform\Model\\'.$namespace.'\\'.$namespace.$stream.'EntryModel';
        }

        return $this->container->make($model);
    }
}
