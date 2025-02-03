<?php

namespace Streams\Core\Field\Types;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Streams\Core\Field\Field;
use Streams\Core\Field\Schema\StringSchema;
use Streams\Core\Field\Decorator\StringDecorator;

class StringFieldType extends Field
{
    public function cast($value)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        return (string) $value;
    }

    public function default($value)
    {
        if ($value == 'random') {
            
            $length = $this->ruleParameter('min', 40);

            return Str::random($length);
        }
    }

    public function modify($value)
    {
        return $this->cast($value);
    }

    public function restore($value)
    {
        return $this->cast($value);
    }

    public function generator()
    {
        $min = $this->ruleParameter('min');
        $max = $this->ruleParameter('max');

        if (Str::is('*_url', $this->handle)) {
            return function () {
                return fake()->url();
            };
        }

        // @todo is a "format" config option needed?
        // These are pretty obvious...
        $formatters = [
            'name' => 'name',
            'first_name' => 'firstName',
            'last_name' => 'lastName',

            'job_title' => 'jobTitle',
            'position' => 'jobTitle',

            'phone' => 'phoneNumber',
            'phone_number' => 'phoneNumber',
            'toll_free' => 'tollFreePhoneNumber',

            'address' => 'streetAddress',
            'address_1' => 'streetAddress',
            'street_address' => 'streetAddress',

            'city' => 'city',

            'state' => 'stateAbbr',

            'postal_code' => 'postcode',
            'post_code' => 'postcode',
            'zip_code' => 'postcode',
            'zip' => 'postcode',

            'longitude' => 'longitude',
            'latitude' => 'latitude',

            'username' => 'userName',
            'password' => 'password',
            
            'url' => 'url',
            'slug' => 'slug',
            
            'ipv4' => 'ipv4',
            'ipv6' => 'ipv6',
            'ip_address' => 'ipv4',
            
            'mac_address' => 'macAddress',
            
            'file' => 'file',
            
            'file_extension' => 'fileExtension',
            
            'mime_type' => 'mimeType',
            'mime' => 'mimeType',
        ];

        if ($this->handle && $formatter = Arr::get($formatters, $this->handle)) {
            return function () use ($formatter) {
                return fake()->{$formatter}();
            };
        };

        if (!$min && $max && $max < 50) {
            return function () use ($max) {
                return Str::truncate(fake()->sentence(), $max);
            };
        }

        return function () use ($min, $max) {
            return fake()->realTextBetween($min ?: 160, $max ?: 200);
        };
    }

    public function getSchemaName()
    {
        return StringSchema::class;
    }

    public function getDecoratorName()
    {
        return StringDecorator::class;
    }
}
