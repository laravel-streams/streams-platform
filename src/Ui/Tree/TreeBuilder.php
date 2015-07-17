<?php namespace Anomaly\Streams\Platform\Ui\Tree;

use Anomaly\Streams\Platform\Model\EloquentModel;
use Anomaly\Streams\Platform\Stream\Contract\StreamInterface;
use Anomaly\Streams\Platform\Traits\FiresCallbacks;
use Anomaly\Streams\Platform\Ui\Tree\Command\AddAssets;
use Anomaly\Streams\Platform\Ui\Tree\Command\BuildTree;
use Anomaly\Streams\Platform\Ui\Tree\Command\LoadTree;
use Anomaly\Streams\Platform\Ui\Tree\Command\MakeTree;
use Anomaly\Streams\Platform\Ui\Tree\Command\PostTree;
use Anomaly\Streams\Platform\Ui\Tree\Command\SetTreeResponse;
use Anomaly\Streams\Platform\Ui\Tree\Component\Item\Contract\ItemInterface;
use Anomaly\Streams\Platform\Ui\Tree\Contract\TreeRepositoryInterface;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Class TreeBuilder
 *
 * @link          http://anomaly.is/streams-platform
 * @author        AnomalyLabs, Inc. <hello@anomaly.is>
 * @author        Ryan Thompson <ryan@anomaly.is>
 * @package       Anomaly\Streams\Platform\Ui\Tree
 */
class TreeBuilder
{

    use FiresCallbacks;
    use DispatchesJobs;

    /**
     * The tree model.
     *
     * @var null|string
     */
    protected $model = null;

    /**
     * The buttons configuration.
     *
     * @var array|string
     */
    protected $buttons = [];

    /**
     * The tree options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * The tree assets.
     *
     * @var array
     */
    protected $assets = [];

    /**
     * The tree instance.
     *
     * @var Tree
     */
    protected $tree;

    /**
     * @param Tree $tree
     */
    function __construct(Tree $tree)
    {
        $this->tree = $tree;
    }

    /**
     * Build the tree.
     */
    public function build()
    {
        $this->fire('ready', ['builder' => $this]);

        $this->dispatch(new BuildTree($this));

        if (app('request')->isMethod('post')) {
            $this->dispatch(new PostTree($this));
        }
    }

    /**
     * Make the tree response.
     */
    public function make()
    {
        $this->build();

        if (!app('request')->isMethod('post')) {
            $this->dispatch(new LoadTree($this));
            $this->dispatch(new AddAssets($this));
            $this->dispatch(new MakeTree($this));
        }
    }

    /**
     * Render the tree.
     *
     * @return Response
     */
    public function render()
    {
        $this->make();

        $this->dispatch(new SetTreeResponse($this));

        return $this->tree->getResponse();
    }

    /**
     * Get the tree.
     *
     * @return Tree
     */
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * Set the tree model.
     *
     * @param string $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get the tree model.
     *
     * @return null|string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set the buttons configuration.
     *
     * @param $buttons
     * @return $this
     */
    public function setButtons($buttons)
    {
        $this->buttons = $buttons;

        return $this;
    }

    /**
     * Get the buttons configuration.
     *
     * @return array
     */
    public function getButtons()
    {
        return $this->buttons;
    }

    /**
     * The the options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set the options.
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get an option value.
     *
     * @param      $key
     * @param null $default
     * @return mixed
     */
    public function getOption($key, $default = null)
    {
        return array_get($this->options, $key, $default);
    }

    /**
     * Set an option value.
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function setOption($key, $value)
    {
        array_set($this->options, $key, $value);

        return $this;
    }

    /**
     * Get the assets.
     *
     * @return array
     */
    public function getAssets()
    {
        return $this->assets;
    }

    /**
     * Set the assets.
     *
     * @param $assets
     * @return $this
     */
    public function setAssets($assets)
    {
        $this->assets = $assets;

        return $this;
    }

    /**
     * Add an asset.
     *
     * @param $collection
     * @param $asset
     * @return $this
     */
    public function addAsset($collection, $asset)
    {
        if (!isset($this->assets[$collection])) {
            $this->assets[$collection] = [];
        }

        $this->assets[$collection][] = $asset;

        return $this;
    }

    /**
     * Get the tree's stream.
     *
     * @return StreamInterface|null
     */
    public function getTreeStream()
    {
        return $this->tree->getStream();
    }

    /**
     * Get the tree model.
     *
     * @return EloquentModel|null
     */
    public function getTreeModel()
    {
        return $this->tree->getModel();
    }

    /**
     * Get a tree option value.
     *
     * @param      $key
     * @param null $default
     * @return mixed
     */
    public function getTreeOption($key, $default = null)
    {
        return $this->tree->getOption($key, $default);
    }

    /**
     * Set a tree option value.
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function setTreeOption($key, $value)
    {
        $this->tree->setOption($key, $value);

        return $this;
    }

    /**
     * Get the tree options.
     *
     * @return Collection
     */
    public function getTreeOptions()
    {
        return $this->tree->getOptions();
    }

    /**
     * Set the tree entries.
     *
     * @param Collection $entries
     * @return $this
     */
    public function setTreeEntries(Collection $entries)
    {
        $this->tree->setEntries($entries);

        return $this;
    }

    /**
     * Get the tree entries.
     *
     * @return Collection
     */
    public function getTreeEntries()
    {
        return $this->tree->getEntries();
    }

    /**
     * Add a tree item to the collection.
     *
     * @param ItemInterface $item
     * @return $this
     */
    public function addTreeItem(ItemInterface $item)
    {
        $this->tree->addItem($item);

        return $this;
    }

    /**
     * Set the tree response.
     *
     * @param Response $response
     */
    public function setTreeResponse(Response $response)
    {
        $this->tree->setResponse($response);
    }

    /**
     * Get the tree response.
     *
     * @return null|Response
     */
    public function getTreeResponse()
    {
        return $this->tree->getResponse();
    }

    /**
     * Get the tree repository.
     *
     * @return TreeRepositoryInterface
     */
    public function getTreeRepository()
    {
        return $this->tree->getRepository();
    }

    /**
     * Get a request value.
     *
     * @param      $key
     * @param null $default
     * @return mixed
     */
    public function getRequestValue($key, $default = null)
    {
        return array_get($_REQUEST, $this->getOption('prefix') . $key, $default);
    }
}
