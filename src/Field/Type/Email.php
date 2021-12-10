<?php

namespace Streams\Core\Field\Type;

use Streams\Core\Field\FieldType;
use Streams\Core\Field\Value\EmailValue;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class Email extends FieldType
{
    public function modify($value)
    {
        return $this->cast($value);
    }

    public function cast($value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return (string) $value;
    }

    public function schema()
    {
        return Schema::string($this->field->handle)->format('email');
    }

    public function expand($value)
    {
        return new EmailValue($value);
    }

    public function generate()
    {
        return $this->generator()->email();
    }
}
