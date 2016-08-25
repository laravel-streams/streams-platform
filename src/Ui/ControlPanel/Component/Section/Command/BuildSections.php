<?php namespace Anomaly\Streams\Platform\Ui\ControlPanel\Component\Section\Command;

use Anomaly\Streams\Platform\Ui\ControlPanel\Component\Section\SectionBuilder;
use Anomaly\Streams\Platform\Ui\ControlPanel\ControlPanelBuilder;


/**
 * Class BuildSections
 *
 * @link          http://anomaly.is/streams-platform
 * @author        AnomalyLabs, Inc. <hello@anomaly.is>
 * @author        Ryan Thompson <ryan@anomaly.is>
 * @package       Anomaly\Streams\Platform\Ui\ControlPanel\Component\Section\Command
 */
class BuildSections
{

    /**
     * The control_panel builder.
     *
     * @var ControlPanelBuilder
     */
    protected $builder;

    /**
     * Create a new BuildSections instance.
     *
     * @param ControlPanelBuilder $builder
     */
    public function __construct(ControlPanelBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Handle the command.
     *
     * @param SectionBuilder $builder
     */
    public function handle(SectionBuilder $builder)
    {
        $builder->build($this->builder);
    }
}
