<?php

namespace Anomaly\Streams\Platform\Ui\Grid\Command;

use Anomaly\Streams\Platform\Ui\Grid\Component\Item\Command\BuildItems;
use Anomaly\Streams\Platform\Ui\Grid\GridBuilder;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Class BuildGrid.
 *
 * @link          http://anomaly.is/streams-platform
 * @author        AnomalyLabs, Inc. <hello@anomaly.is>
 * @author        Ryan Thompson <ryan@anomaly.is>
 * @package       Anomaly\Streams\Platform\Ui\Grid\Command
 */
class BuildGrid implements SelfHandling
{
    use DispatchesJobs;

    /**
     * The grid builder.
     *
     * @var GridBuilder
     */
    protected $builder;

    /**
     * Create a new BuildGrid instance.
     *
     * @param GridBuilder $builder
     */
    public function __construct(GridBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Handle the command.
     */
    public function handle()
    {
        /*
         * Resolve and set the grid model and stream.
         */
        $this->dispatch(new SetGridModel($this->builder));
        $this->dispatch(new SetGridStream($this->builder));
        $this->dispatch(new SetGridOptions($this->builder));
        $this->dispatch(new SetDefaultOptions($this->builder));
        $this->dispatch(new SetGridRepository($this->builder));
        $this->dispatch(new SetDefaultParameters($this->builder));

        /*
         * Before we go any further, authorize the request.
         */
        $this->dispatch(new AuthorizeGrid($this->builder));

        /*
         * Get grid entries.
         */
        $this->dispatch(new GetGridEntries($this->builder));

        /*
         * Lastly grid items.
         */
        $this->dispatch(new BuildItems($this->builder));
    }
}
