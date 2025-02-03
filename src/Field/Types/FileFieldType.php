<?php

namespace Streams\Core\Field\Types;

use Illuminate\Support\Str;
use Streams\Core\Field\Field;
use Streams\Core\Field\Decorator\FileDecorator;

class FileFieldType extends Field
{
    public $rules = [
        'nullable',
    ];

    public function cast($value)
    {
        if (is_string($value)) {
            return $value;
        }

        throw new \Exception("Could not determine file type.");
    }

    public function generator()
    {
        return function () {

            $file = Str::uuid();
            $directory = 'tmp/';
            $extension = fake()->randomElement([
                'md', 'html', 'json', 'pdf', 'doc'
            ]);

            return "$directory/$file.$extension";
        };
    }

    public function getDecoratorName()
    {
        return FileDecorator::class;
    }
}
