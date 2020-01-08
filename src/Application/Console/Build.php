<?php

namespace Anomaly\Streams\Platform\Application\Console;

use Anomaly\Streams\Platform\Application\Event\SystemIsBuilding;
use \Illuminate\Contracts\Console\Kernel;
use Illuminate\Console\Command;

/**
 * Class Build
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class Build extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build the system.';

    /**
     * Execute the console command.
     */
    public function handle(Kernel $console)
    {
        $this->info('Building system.');

        event(new SystemIsBuilding($this));

        $this->info('Compiling entry models.');

        $console->call('streams:compile');

        $console->call('assets:publish', []);

        $this->info('Assets published.');

        $this->info('Building search index.');

        $console->call('streams:index', ['--flush' => true]);

        event(new SystemIsBuilding($this));
    }
}
