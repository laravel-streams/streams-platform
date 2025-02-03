<?php

namespace Streams\Core\Field\Types;

use Streams\Core\Field\Field;
use Illuminate\Support\Facades\Date;
use Streams\Core\Field\Schema\DatetimeSchema;
use Streams\Core\Field\Decorator\DatetimeDecorator;

class DatetimeFieldType extends Field
{
    #[Field([
        'type' => 'object',
        'config' => [
            'wrapper' => 'array',
        ],
    ])]
    public array $config = [
        'format' => 'Y-m-d H:i:s',
    ];

    public function default($value)
    {
        return $this->toDateTime($value);
    }

    public function cast($value): \DateTime | null
    {
        $timezone = $this->config('timezone', config('app.timezone'));
        
        return $this->toDateTime($value, $timezone);
    }

    public function modify($value)
    {
        $format = $this->config('format', 'Y-m-d H:i:s');

        return $this->toDateTime($value)?->setTimezone('UTC')->format($format);
    }

    public function restore($value): \DateTime
    {
        $timezone = $this->config('timezone', config('app.timezone'));

        return $this->toDateTime($value, 'UTC')->setTimezone($timezone);
    }

    public function getSchemaName()
    {
        return DatetimeSchema::class;
    }

    public function getDecoratorName()
    {
        return DatetimeDecorator::class;
    }

    public function generator()
    {
        $min = $this->ruleParameter('min');
        $max = $this->ruleParameter('max');

        if ($min || $max) {
            return function () use ($min, $max) {
                return $this->cast(fake()->dateTimeBetween($min, $max));
            };
        }

        return function () {
            return $this->cast(fake()->dateTime());
        };
    }

    protected function toDateTime($value, string $timezone = null): \DateTime | null
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        if (!$value) {
            return null;
        }

        $timezone = $timezone ?: $this->config('timezone', config('app.timezone'));

        if (is_numeric($value)) {
            return Date::createFromTimestamp($value, $timezone);
        }

        return Date::parse($value, $timezone);
    }
}
