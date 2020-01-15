<?php

namespace Anomaly\Streams\Platform\Support;

use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Class Presenter
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class Presenter
{
    /**
     * The decorated object.
     */
    protected $object;

    /**
     * Protected names.
     *
     * @var array
     */
    protected $protected = [
        'delete',
        'save',
        'update',
    ];

    /**
     * Create a new Presenter instance.
     *
     * @param object $object
     */
    public function __construct($object)
    {
        $this->object = $object;
    }

    /**
     * Get the object.
     *
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Pass any unknown variable calls to present{$variable} or fall through to the injected object.
     *
     * @param  string $var
     * @return mixed
     */
    public function __get($var)
    {
        if (in_array($var, $this->protected)) {
            return null;
        }

        if ($method = $this->getPresenterMethodFromVariable($var)) {
            return Decorator::decorate($this->$method());
        }

        // Check the presenter for a getter.
        if (method_exists($this, camel_case('get_' . $var))) {
            return Decorator::decorate(call_user_func_array([$this, camel_case('get_' . $var)], []));
        }

        // Check the presenter for a getter.
        if (method_exists($this, camel_case('is_' . $var))) {
            return Decorator::decorate(call_user_func_array([$this, camel_case('is_' . $var)], []));
        }

        // Check the presenter for a method.
        if (method_exists($this, camel_case($var))) {
            return Decorator::decorate(call_user_func_array([$this, camel_case($var)], []));
        }

        // Check the object for a getter.
        if (method_exists($this->object, camel_case('get_' . $var))) {
            return Decorator::decorate(call_user_func_array([$this->object, camel_case('get_' . $var)], []));
        }

        // Check the object for a getter.
        if (method_exists($this->object, camel_case('is_' . $var))) {
            return Decorator::decorate(call_user_func_array([$this->object, camel_case('is_' . $var)], []));
        }

        // Check the object for a method.
        if (method_exists($this->object, camel_case($var))) {
            return Decorator::decorate(call_user_func_array([$this->object, camel_case($var)], []));
        }

        // Check the for a getter style hook.
        if (method_exists($this->object, 'call') && $this->object->hasHook('get_' . $var)) {
            return Decorator::decorate($this->object->call('get_' . $var));
        }

        // Check the for a normal style hook.
        if (method_exists($this->object, 'call') && $this->object->hasHook($var)) {
            return Decorator::decorate($this->object->call($var));
        }

        try {
            // Lastly try generic property access.
            return Decorator::decorate(
                is_array($this->object) ? $this->object[$var] : $this->object->$var
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch the presenter method name for the given variable.
     *
     * @param  string      $variable
     * @return string|null
     */
    protected function getPresenterMethodFromVariable($variable)
    {
        $method = camel_case($variable);

        if (method_exists($this, $method)) {
            return $method;
        }
    }

    /**
     * Call unknown methods if safe.
     *
     * @param  string $method
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (in_array(snake_case($method), $this->protected)) {
            return null;
        }

        if (is_object($this->object)) {

            $value = call_user_func_array([$this->object, $method], $arguments);

            return Decorator::decorate($value);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * Return the objects string method.
     *
     * @return string
     */
    public function __toString()
    {
        if (method_exists($this->object, '__toString')) {
            return $this->object->__toString();
        }

        return json_encode($this->object);
    }
}
