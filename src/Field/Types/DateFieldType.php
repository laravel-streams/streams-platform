<?php

namespace Streams\Core\Field\Types;

use Carbon\Carbon;
use Streams\Core\Field\Field;
use Streams\Core\Field\Schema\DateSchema;
use Streams\Core\Field\Decorator\DatetimeDecorator;

class DateFieldType extends DatetimeFieldType
{
    #[Field([
        'type' => 'object',
        'config' => [
            'wrapper' => 'array',
        ],
    ])]
    public array $config = [
        'format' => 'Y-m-d',
    ];

    public function default($value): \DateTime
    {
        return $this->cast($value);
    }

    public function cast($value): \DateTime | null
    {
        return $this->toDateTime($value, 'UTC')?->startOfDay();
    }

    public function modify($value)
    {
        $format = $this->config('format', 'Y-m-d');

        return $this->toDateTime($value)?->format($format);
    }

    public function getSchemaName()
    {
        return DateSchema::class;
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

        if (is_string($value) && $this->isStandardDateFormat($value)) {
            return Carbon::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse($value);
    }

    protected function isStandardDateFormat($value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }
}
