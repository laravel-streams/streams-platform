<?php namespace Anomaly\Streams\Platform\Http\Middleware;

use Anomaly\Streams\Platform\Message\MessageBag;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Session\Store;

/**
 * Class HttpCache
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class HttpCache
{

    /**
     * The config repository.
     *
     * @var Repository
     */
    protected $config;

    /**
     * The session store.
     *
     * @var Store
     */
    protected $session;

    /**
     * The message bag.
     *
     * @var MessageBag
     */
    protected $messages;

    /**
     * Create a new PoweredBy instance.
     *
     * @param Store      $session
     * @param Repository $config
     * @param MessageBag $messages
     */
    public function __construct(Store $session, Repository $config, MessageBag $messages)
    {
        $this->config   = $config;
        $this->session  = $session;
        $this->messages = $messages;
    }

    /**
     * Say it loud.
     *
     * @param  Request  $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        /* @var Response $response */
        $response = $next($request);

        /* @var Route $route */
        $route = $request->route();

        /**
         * Don't cache the admin.
         * And skip the rest.
         */
        if ($request->segment(1) == 'admin') {
            return $response->setTtl(0);
        }

        /**
         * Don't cache if HTTP cache
         * is disabled in the route.
         */
        if ($route->getAction('streams::http_cache') === false) {
            $response->setTtl(0);
        }

        /**
         * Don't cache if HTTP cache
         * is disabled in the system.
         */
        if ($this->config->get('streams::httpcache.enabled', false) === false) {
            $response->setTtl(0);
        }

        /**
         * Don't let BOTs generate cache files.
         */
        if (!$this->config->get('streams::httpcache.allow_bots', false) === false) {
            $response->setTtl(0);
        }

        /**
         * Don't cache if we have session indicators!
         *
         * This could happen if a form attempts caching
         * directly after a bad submit / failed validation.
         */
        if (
            $this->session->has('_flash') ||
            $this->messages->has('info') ||
            $this->messages->has('error') ||
            $this->messages->has('success') ||
            $this->messages->has('warning')
        ) {
            $response->setTtl(0);
        }

        /**
         * Exclude these paths from caching
         * based on partial / exact URI.
         */
        $excluded = $this->config->get('streams::httpcache.excluded', []);

        if (is_string($excluded)) {
            $excluded = array_map(
                function ($line) {
                    return trim($line);
                },
                explode("\n", $excluded)
            );
        }

        foreach ((array)$excluded as $path) {
            if (str_is($path, $request->getPathInfo())) {
                $response->setTtl(0);
            }
        }

        /**
         * Define timeout rules based on
         * partial / exact URI matching.
         */
        $rules = $this->config->get('streams::httpcache.rules', []);

        if (is_string($rules)) {
            $rules = array_map(
                function ($line) {
                    return trim($line);
                },
                explode("\n", $rules)
            );
        }

        foreach ((array)$rules as $rule) {

            $parts = explode(' ', $rule);

            $path = array_shift($parts);
            $ttl  = array_shift($parts);

            if (str_is($path, $request->getPathInfo())) {
                $response->setTtl($ttl);
            }
        }

        /**
         * Set the TTL based on the original TTL or the route
         * action OR the config and lastly a default value.
         */
        if ($response->getTtl() === null) {
            $response->setTtl(
                $route->getAction('streams::http_cache') ?: $this->config->get('streams::httpcache.ttl', 3600)
            );
        }

        /**
         * If the response has a TTL then
         * let's flush the flash messages.
         */
        if ($response->getTtl()) {
            $this->messages->flush();
        }

        return $response;
    }

}
