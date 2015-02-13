<?php namespace Anomaly\Streams\Platform\Model;

use Anomaly\Streams\Platform\Model\Event\ModelWasCreated;
use Anomaly\Streams\Platform\Model\Event\ModelWasDeleted;
use Anomaly\Streams\Platform\Model\Event\ModelWasRestored;
use Anomaly\Streams\Platform\Model\Event\ModelWasSaved;
use Anomaly\Streams\Platform\Model\Event\ModelWasUpdated;
use Illuminate\Bus\Dispatcher as CommandDispatcher;
use Illuminate\Events\Dispatcher as EventDispatcher;

/**
 * Class EloquentObserver
 *
 * @link    http://anomaly.is/streams-platform
 * @author  AnomalyLabs, Inc. <hello@anomaly.is>
 * @author  Ryan Thompson <ryan@anomaly.is>
 * @package Anomaly\Streams\Platform\Model
 */
class EloquentObserver
{

    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Events\Dispatcher
     */
    protected $events;

    /**
     * The command dispatcher.
     *
     * @var \Illuminate\Bus\Dispatcher
     */
    protected $commands;

    /**
     * Create a new EloquentObserver instance.
     *
     * @param EventDispatcher   $events
     * @param CommandDispatcher $commands
     */
    public function __construct(EventDispatcher $events, CommandDispatcher $commands)
    {
        $this->events   = $events;
        $this->commands = $commands;
    }

    /**
     * Run before creating a record.
     *
     * @param EloquentModel $model
     */
    public function creating(EloquentModel $model)
    {
    }

    /**
     * Run after a record is created.
     *
     * @param EloquentModel $model
     */
    public function created(EloquentModel $model)
    {
        $model->flushCacheCollection();

        $this->events->fire(new ModelWasCreated($model));
    }

    /**
     * Run before attempting to save a record.
     * Return false to cancel the operation.
     *
     * @param  EloquentModel $model
     * @return bool
     */
    public function saving(EloquentModel $model)
    {
        return true;
    }

    /**
     * Run after saving a record.
     *
     * @param EloquentModel $model
     */
    public function saved(EloquentModel $model)
    {
        $model->flushCacheCollection();

        $this->events->fire(new ModelWasSaved($model));
    }

    /**
     * Run before a record is updated.
     *
     * @param EloquentModel $model
     */
    public function updating(EloquentModel $model)
    {
    }

    /**
     * Run before multiple records are updated.
     *
     * @param EloquentModel $model
     */
    public function updatingMany(EloquentModel $model)
    {
    }

    /**
     * Run after a record has been updated.
     *
     * @param EloquentModel $model
     */
    public function updated(EloquentModel $model)
    {
        $model->flushCacheCollection();

        $this->events->fire(new ModelWasUpdated($model));
    }

    /**
     * Run after multiple records have been updated.
     *
     * @param EloquentModel $model
     */
    public function updatedMany(EloquentModel $model)
    {
        $model->flushCacheCollection();
    }

    /**
     * Run before deleting a record.
     *
     * @param EloquentModel $model
     */
    public function deleting(EloquentModel $model)
    {
    }

    /**
     * Run before deleting multiple records.
     *
     * @param EloquentModel $model
     */
    public function deletingMany(EloquentModel $model)
    {
    }

    /**
     * Run after a record has been deleted.
     *
     * @param EloquentModel $model
     */
    public function deleted(EloquentModel $model)
    {
        $model->flushCacheCollection();

        $this->events->fire(new ModelWasDeleted($model));
    }

    /**
     * Run after multiple records have been deleted.
     *
     * @param EloquentModel $model
     */
    public function deletedMany(EloquentModel $model)
    {
        $model->flushCacheCollection();
    }

    /**
     * Run before restoring a record.
     *
     * @param EloquentModel $model
     */
    public function restoring(EloquentModel $model)
    {
    }

    /**
     * Run after a record has been restored.
     *
     * @param EloquentModel $model
     */
    public function restored(EloquentModel $model)
    {
        $model->flushCacheCollection();

        $this->events->fire(new ModelWasRestored($model));
    }
}
