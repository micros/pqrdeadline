<?php

namespace Micrositios\PqrDeadline\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DummyTest extends TestCase
{
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
        $this->assertEquals(1, 1);
        $this->assertNotEquals(1, 2);
    }

    public function testStringOperations(): void
    {
        $string = 'Hello World';
        $this->assertStringContainsString('World', $string);
        $this->assertStringStartsWith('Hello', $string);
        $this->assertStringEndsWith('World', $string);
    }

    public function testArrayOperations(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertContains(3, $array);
        $this->assertCount(5, $array);
        $this->assertNotEmpty($array);
    }

    public function testPhpVersion(): void
    {
        $this->assertGreaterThanOrEqual(80100, PHP_VERSION_ID, 'PHP version should be 8.1 or higher');
    }

    public function testAutoloading(): void
    {
        // Verificar que las clases principales se pueden cargar
        $this->assertTrue(interface_exists('Micrositios\PqrDeadline\Contracts\DeadlineCalculator'));
        $this->assertTrue(class_exists('Micrositios\PqrDeadline\Strategies\BusinessDaysDeadlineCalculator'));
        $this->assertTrue(class_exists('Micrositios\PqrDeadline\Support\DeadlineWrapper'));
        $this->assertTrue(enum_exists('Micrositios\PqrDeadline\Enums\RequestType'));
    }
}
