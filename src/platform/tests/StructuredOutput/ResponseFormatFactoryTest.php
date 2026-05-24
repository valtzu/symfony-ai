<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithAccessors;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

final class ResponseFormatFactoryTest extends TestCase
{
    #[TestWith(['User', User::class])]
    #[TestWith(['UserWithConstructor', UserWithConstructor::class])]
    #[TestWith(['UserWithAccessors', UserWithAccessors::class])]
    public function testCreate(string $expectedName, string $class)
    {
        $result = (new ResponseFormatFactory())->create($class);

        $this->assertSame('json_schema', $result['type']);
        $this->assertSame($expectedName, $result['json_schema']['name']);
        $this->assertTrue($result['json_schema']['strict']);
        $this->assertInstanceOf(Schema::class, $result['json_schema']['schema']);
    }
}
