<?php
/**
 * Created by PhpStorm.
 * User: box
 * Date: 10/21/18
 * Time: 9:03 PM
 */

namespace Test\AdServer;

use Internals\UniqueId;
use PHPUnit\Framework\TestCase;

class UniqueIdentifierTest extends TestCase
{
    /** @test */
    public function createRandom()
    {
        $randomId = UniqueId::random();
        self::assertInstanceOf(UniqueId::class, $randomId);
    }

    /**
     * @test
     * @dataProvider fromStringProvider
     *
     * @param string $string The string representation of a UUID
     */
    public function createFromString(string $string)
    {
        $uuid = UniqueId::fromString($string);
        self::assertInstanceOf(UniqueId::class, $uuid);
        self::assertEquals(strtolower($string), "$uuid");
    }

    public function fromStringProvider()
    {
        return [
            ['b355acac-09cb-45d6-9f75-5dd298b3b862'],
            ['5CE9620E-50C6-4AB0-9445-E159D5AD9D08'],
        ];
    }

    /** @test */
    public function creationFailure()
    {
        self::expectException(\InvalidArgumentException::class);

        UniqueId::fromString('invalid uuid');
    }
}

