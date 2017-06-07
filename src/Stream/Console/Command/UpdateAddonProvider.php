<?php namespace Anomaly\Streams\Platform\Stream\Console\Command;

use Anomaly\Streams\Platform\Addon\Addon;
use Illuminate\Filesystem\Filesystem;

/**
 * Class UpdateAddonProvider
 *
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 * @author Denis Efremov <efremov.a.denis@gmail.com>
 *
 * @link   http://pyrocms.com/
 */
class UpdateAddonProvider
{

    /**
     * The entity slug.
     *
     * @var string
     */
    private $slug;

    /**
     * The addon instance.
     *
     * @var Addon
     */
    private $addon;

    /**
     * The entity stream namespace.
     *
     * @var string
     */
    private $namespace;

    /**
     * Create a new UpdateAddonProvider instance.
     *
     * @param Addon        $addon
     * @param $slug
     * @param $namespace
     */
    public function __construct(Addon $addon, $slug, $namespace)
    {
        $this->slug      = $slug;
        $this->addon     = $addon;
        $this->namespace = $namespace;
    }

    /**
     * Handle the command.
     *
     * @param Parser     $parser
     * @param Filesystem $filesystem
     */
    public function handle(Filesystem $filesystem)
    {
        $suffix   = ucfirst(camel_case($this->slug));
        $entity   = str_singular($suffix);
        $provider = $this->addon->getServiceProvider();

        $slug   = ucfirst($this->addon->getSlug());
        $vendor = ucfirst($this->addon->getVendor());
        $type   = ucfirst($this->addon->getType());

        $prefix = "{$vendor}\\{$slug}{$type}\\{$entity}";

        $path = $this->addon->getPath("src/{$slug}{$type}ServiceProvider.php");

        $uses = "use Anomaly\\Streams\\Platform\\Model\\{$slug}\\{$slug}{$suffix}EntryModel;\n";
        $uses .= "use {$prefix}\\Contract\\{$entity}RepositoryInterface;\n";
        $uses .= "use {$prefix}\\{$entity}Model;\n";
        $uses .= "use {$prefix}\\{$entity}Repository;\n";

        $this->putInFile($filesystem, $path, '/use.*;\n/i', $uses);

        $this->putInFile(
            $filesystem,
            $path,
            '/protected \$bindings = \[\]/i',
            "protected \$bindings = [\n    ]",
            true
        );

        $this->putInFile(
            $filesystem,
            $path,
            '/protected \$singletons = \[\]/i',
            "protected \$singletons = [\n    ]",
            true
        );

        $this->putInFile(
            $filesystem,
            $path,
            '/protected \$bindings = \[\n/i',
            "        {$slug}{$suffix}EntryModel::class => {$entity}Model::class,\n"
        );

        $this->putInFile(
            $filesystem,
            $path,
            '/protected \$singletons = \[\n/i',
            "        {$entity}RepositoryInterface::class => {$entity}Repository::class,\n"
        );
    }

    /**
     * Puts in file.
     *
     * @param  string  $file_path     The file path
     * @param  string  $insert_marker The insert marker
     * @param  string  $text          The text
     * @param  boolean $replace       The replace flag
     * @return number  Recorded bytes
     */
    private function putInFile(
        Filesystem $filesystem,
        $file_path,
        $insert_marker,
        $text,
        $replace = false
    )
    {
        $contents = $filesystem->get($file_path);

        $new_contents = preg_replace(
            $insert_marker,
            ($replace) ? $text : '$0' . $text,
            $contents,
            1
        );

        return $filesystem->put($file_path, $new_contents);
    }

}
