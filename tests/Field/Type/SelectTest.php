<?php

namespace Streams\Core\Tests\Field\Type;

use Tests\TestCase;
use Streams\Core\Field\Value\SelectValue;
use Streams\Core\Support\Facades\Streams;

class SelectTest extends TestCase
{

    public function setUp(): void
    {
        $this->createApplication();

        Streams::load(base_path('vendor/streams/core/tests/litmus.json'));
        Streams::load(base_path('vendor/streams/core/tests/fakers.json'));
    }

    public function test_configured_options()
    {
        $type = Streams::make('testing.litmus')->fields->select->type();

        $this->assertSame(['foo' => 'Foo', 'bar' => 'Bar'], $type->options());
    }

    public function test_callable_options()
    {
        $type = Streams::make('testing.litmus')->fields->select_callable_options->type();

        $this->assertSame(['foo' => 'Bar'], $type->options());
    }

    public function test_expanded_value()
    {
        $test = Streams::repository('testing.litmus')->find('field_types');

        $this->assertInstanceOf(SelectValue::class, $test->expand('select'));
    }

    public function test_can_generate_value()
    {
        $stream = Streams::make('testing.fakers');

        $this->assertTrue(in_array(
            $stream->fields->select->type()->generate(),
            array_keys($stream->fields->select->type()->options())
        ));
    }
}

class SelectHandler
{

    public function handle(): array
    {
        return ['foo' => 'Bar'];
    }
}
