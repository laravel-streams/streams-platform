<?php

namespace Streams\Core\Field\Types;

use Carbon\Carbon;
use Streams\Core\Field\Field;
use Streams\Core\Field\Schema\TimeSchema;
use Streams\Core\Field\Decorator\DatetimeDecorator;

class TimeFieldType extends DatetimeFieldType
{
    #[Field([
        'type' => 'object',
        'config' => [
            'wrapper' => 'array',
        ],
    ])]
    public array $config = [
        'format' => 'H:i:s',
    ];

    public function modify($value): string
    {
        $format = $this->config('format', 'H:i:s');

        return $this->toDateTime($value)->format($format);
    }

    public function restore($value): Carbon
    {
        $timezone = $this->config('timezone', config('app.timezone'));

        return $this->cast($value, 'UTC')->setTimezone($timezone);
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
            return $this->cast(fake()->time());
        };
    }

    public function getSchemaName()
    {
        return TimeSchema::class;
    }
    
    public function getDecoratorName()
    {
        return DatetimeDecorator::class;
    }
}
