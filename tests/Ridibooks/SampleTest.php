<?php
declare(strict_types=1);

namespace Ridibooks\tests;

use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    public function testAdd()
    {
        $this->assertEquals(30, 20 + 10);
    }
}
