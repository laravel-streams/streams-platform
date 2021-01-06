<?php

namespace Streams\Core\Tests\Field\Type;

use Streams\Core\Field\Value\ColorValue;
use Tests\TestCase;
use Streams\Core\Support\Facades\Streams;

class ColorTest extends TestCase
{

    public function setUp(): void
    {
        $this->createApplication();

        Streams::load(base_path('vendor/streams/core/tests/litmus.json'));
    }

    public function testCasting()
    {
        $test = Streams::repository('testing.litmus')->find('field_types');
        
        $this->assertSame('#111111', $test->color);
    }

    public function testValue()
    {
        $test = Streams::repository('testing.litmus')->find('field_types');
        
        $this->assertInstanceOf(ColorValue::class, $test->expand('color'));
    }
}
