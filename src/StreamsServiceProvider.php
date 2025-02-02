<?php

namespace Streams\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\Factory;
use Streams\Core\Support\Parser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Streams\Core\Support\Integrator;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Translation\Translator;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Streams\Core\Support\Facades\Addons;
use Streams\Core\Support\Facades\Assets;
use Streams\Core\Support\Facades\Images;
use Streams\Core\Application\Application;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Support\Facades\Overrides;
use Streams\Core\Support\Facades\Applications;
use Streams\Core\View\ViewTemplate;

class StreamsServiceProvider extends ServiceProvider
{

    public array $aliases = [
        'Assets' => \Streams\Core\Support\Facades\Assets::class,
        'Images' => \Streams\Core\Support\Facades\Images::class,
        'Streams' => \Streams\Core\Support\Facades\Streams::class,
        'Includes' => \Streams\Core\Support\Facades\Includes::class,
        // 'Messages' => \Streams\Core\Support\Facades\Messages::class,
        'Overrides' => \Streams\Core\Support\Facades\Overrides::class,
        'Applications' => \Streams\Core\Support\Facades\Applications::class,
    ];

    public array $singletons = [
        'addons' => \Streams\Core\Addon\AddonManager::class,
        'assets' => \Streams\Core\Asset\AssetManager::class,
        'images' => \Streams\Core\Image\ImageManager::class,
        'includes' => \Streams\Core\View\ViewIncludes::class,
        'streams' => \Streams\Core\Stream\StreamManager::class,
        // 'messages' => \Streams\Core\Message\MessageManager::class,
        'applications' => \Streams\Core\Application\ApplicationManager::class,

        'hydrator'   => \Streams\Core\Support\Hydrator::class,
        'decorator'  => \Streams\Core\Support\Decorator::class,

        'overrides' => \Streams\Core\View\ViewOverrides::class,
    ];

    public array $bindings = [];

    public function register(): void
    {
        $this->registerConfig();

        $this->registerBladeDirectives();
        $this->registerComposerJson();
        $this->registerComposerLock();
        $this->registerFieldTypes();

        $this->registerAliases();
        $this->registerMacros();
        $this->extendApp();

        $this->publishes([
            dirname(__DIR__) . '/resources/public'
            => public_path('vendor/streams/core'),
        ], ['public']);
    }

    public function boot(): void
    {
        $this->app->singleton('streams.parser_data', fn () => Parser::data());

        $this->registerStreams();

        $this->registerApplications();

        $this->bootApplication();

        $this->addAssetPaths();
        $this->registerAddons();
        $this->addViewNamespaces();
        $this->addImageNamespaces();
        $this->loadTranslations();

        $this->app->singleton('faker', fn () => \Faker\Factory::create());

        // if ($this->app->runningInConsole()) {
        //     $this->commands([
        //         //
        //     ]);
        // }
    }

    protected function registerComposerJson(): void
    {
        $this->app->singleton(
            'composer.json',
            function () {
                return json_decode(file_get_contents(base_path('composer.json')), true);
            }
        );
    }

    protected function registerComposerLock(): void
    {
        $this->app->singleton(
            'composer.lock',
            function () {
                return json_decode(file_get_contents(base_path('composer.lock')), true);
            }
        );
    }

    // @todo move to a deferred application service provider
    protected function registerApplications(): void
    {
        $directory  = dirname(__DIR__) . '/resources/streams/';

        $id = Config::get('streams.core.applications_id');

        if (!Streams::exists($id)) {
            Streams::load($directory . $id . '.json');
        }

        $url = Request::fullUrl();
        $applications = Applications::collection();

        /**
         * Mark the active application
         * according to it's match.
         */
        $active = $applications->first(function ($application) use ($url) {
            return $application->match && collect((array)$application->match)->filter(function ($match) use ($url) {
                return Str::is($match, $url);
            })->isNotEmpty();
        });

        if (!$active) {
            $active = $applications->first(function ($application) {
                return !$application->match;
            });
        }

        if (!$active) {

            $active = new Application([
                'stream' => Streams::make($id),
                'id'    => 'default',
                'match' => '*',
            ]);
        }

        if ($active) {
            Applications::activate($active);
        }
    }

    protected function bootApplication(): void
    {
        // Get the active application.
        $active = Applications::active();

        Integrator::integrate(array_filter([
            'locale'     => $active->locale,
            'config'     => $active->config,
            'aliases'    => $active->aliases,
            'streams'    => $active->streams,
            'bindings'   => $active->bindings,
            'singletons' => $active->singletons,
        ]));
    }

    protected function registerFieldTypes(): void
    {
        foreach (Config::get('streams.core.field_types', []) as $type => $class) {
            $this->app->bind('streams.core.field_type.' . $type, $class);
        }
    }

    protected function registerAliases(): void
    {
        AliasLoader::getInstance($this->aliases)->register();
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../resources/config/core.php', 'streams.core');

        $this->publishes([
            __DIR__ . '/../resources/config/core.php' => config_path('streams/core.php'),
        ], 'config');
    }

    protected function registerStreams(): void
    {
        $directory  = dirname(__DIR__) . '/resources/streams/';

        $this->publishes([
            $directory => base_path('streams/'),
        ], 'streams');

        $id = Config::get('streams.core.streams_id');

        if (!Streams::exists($id)) {
            Streams::load($directory . $id . '.json');
        }

        $streams = Streams::repository($id)->all();

        $base = $streams->where('extends', null)->keyBy('id');
        $extending = $streams->where('extends', '!=', null)->keyBy('id'); // Deferred

        foreach ((new Collection)->merge($base)->merge($extending) as $stream) {
            Streams::register(Arr::parse($stream->toArray()));
        }
    }

    protected function registerAddons(): void
    {
        $composer = $this->app['composer.json'];
        $lock = $this->app['composer.lock'];

        $directory = base_path('vendor');

        if ($configured = Arr::get($composer, 'config.vendor-dir')) {
            $directory = base_path($configured);
        }

        $addons = array_filter(
            array_merge($lock['packages'], $lock['packages-dev']),
            function (array $package) {
                return Arr::get($package, 'type') === 'streams-addon';
            }
        );

        ksort($addons);

        $addons = array_map(function ($addon) use ($directory) {
            if (file_exists($directory . '/' . $addon['name'] . '/composer.json')) {
                $addon = Addons::load($directory . '/' . $addon['name']);
            }
        }, $addons);
    }

    protected function addAssetPaths(): void
    {
        Assets::addPath('public', public_path());
        Assets::addPath('core', dirname(__DIR__));
        Assets::addPath('resources', resource_path());
        Assets::addPath('storage', storage_path('streams/' . Applications::active()->id));

        Assets::register('core::js/core.js');
    }

    protected function addImageNamespaces(): void
    {
        Images::addPath('public', public_path());
        Images::addPath('resources', resource_path());
        // @todo core or streams
        Images::addPath('streams', dirname(__DIR__) . '/resources');
    }

    protected function addViewNamespaces(): void
    {
        // @todo core or streams
        View::addNamespace('core', dirname(__DIR__) . '/resources/views');
        View::addNamespace('storage', storage_path('streams/' . Applications::active()->id));
    }

    protected function loadTranslations(): void
    {
        // @todo core or streams
        Lang::addNamespace('streams', dirname(__DIR__) . '/resources/lang');
    }

    protected function registerMacros(): void
    {
        Arr::macro('make', $this->app[\Streams\Core\Support\Macros\ArrMake::class]());
        Arr::macro('parse', $this->app[\Streams\Core\Support\Macros\ArrParse::class]());
        Arr::macro('export', $this->app[\Streams\Core\Support\Macros\ArrExport::class]());
        Arr::macro('htmlAttributes', $this->app[\Streams\Core\Support\Macros\ArrHtmlAttributes::class]());

        Factory::macro('parse', $this->app[\Streams\Core\Support\Macros\FactoryParse::class]());
        Factory::macro('include', $this->app[\Streams\Core\Support\Macros\FactoryInclude::class]());
        Factory::macro('includes', $this->app[\Streams\Core\Support\Macros\FactoryIncludes::class]());
        Factory::macro('override', $this->app[\Streams\Core\Support\Macros\FactoryOverride::class]());

        Str::macro('parse', $this->app[\Streams\Core\Support\Macros\StrParse::class]());
        Str::macro('purify', $this->app[\Streams\Core\Support\Macros\StrPurify::class]());
        Str::macro('humanize', $this->app[\Streams\Core\Support\Macros\StrHumanize::class]());
        Str::macro('truncate', $this->app[\Streams\Core\Support\Macros\StrTruncate::class]());
        Str::macro('isSerialized', $this->app[\Streams\Core\Support\Macros\StrIsSerialized::class]());

        Route::macro('streams', $this->app[\Streams\Core\Support\Macros\RouteStreams::class]());

        URL::macro('streams', $this->app[\Streams\Core\Support\Macros\UrlStreams::class]());

        Translator::macro('translate', $this->app[\Streams\Core\Support\Macros\TranslatorTranslate::class]());
    }

    protected function extendApp(): void
    {
        $composer = $this->app['composer.json'];

        $path = (string)base_path(Arr::get($composer, 'config.vendor-dir', 'vendor'));

        $this->app['vendor.path'] = $path;
    }

    protected function registerBladeDirectives(): void
    {
        $persisted = [];

        Factory::macro('assetsStart', function ($expression) use (&$persisted) {
            $persisted = explode(',', str_replace(['"', ' '], '', $expression));
        });

        Factory::macro('assetsFinish', function ($content) use (&$persisted) {

            $collection = array_shift($persisted);
            
            $path = ViewTemplate::path($content) . '.blade.php';

            if ($name = array_shift($persisted)) {
                Assets::register($name, $path);
            }

            Assets::add($collection, $name ?: $path);
        });


        Blade::directive('assets', function ($expression) {
            return "<?php app('view')->assetsStart('".$expression."'); ob_start(); ?>";
        });
    
        Blade::directive('endassets', function ($expression) {
            return "<?php app('view')->assetsFinish(ob_get_clean()); ?>";
        });


        Blade::directive('markdown', function () {
            return "<?php echo (new \League\CommonMark\GithubFlavoredMarkdownConverter([
                // Configuration
            ]))->convert('";
        });
        
        Blade::directive('endmarkdown', function () {
            return "'); ?>";
        });
    }
}
