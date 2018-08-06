<?php
declare(strict_types=1);

namespace RidiPay\Tests\Pg\Kcp;

use PHPUnit\Framework\TestCase;
use RidiPay\Library\Pg\Kcp\Util;

class KcpUtilTest extends TestCase
{
    public function testFlattenAssocArray()
    {
        $this->assertSame('key1=value1,key2=value2', Util::flattenAssocArray([
            'key1' => 'value1',
            'key2' => 'value2',
        ]));

        $this->assertSame("key1=value1\x1fkey2=value2\x1f", Util::flattenAssocArray([
            'key1' => 'value1',
            'key2' => 'value2',
        ], "\x1f", true));
    }

    public function testParsePayPlusCliOutput()
    {
        $this->assertSame(
            [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
            Util::parsePayPlusCliOutput("key1=value1\x1fkey2=value2")
        );

        $this->assertSame([], Util::parsePayPlusCliOutput(""));
    }
}
