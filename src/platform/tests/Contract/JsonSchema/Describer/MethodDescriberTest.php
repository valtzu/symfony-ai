<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\MethodDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstructor;

final class MethodDescriberTest extends TestCase
{
    #[DataProvider('propertyProvider')]
    public function testDescribeProperty(PropertySubject $property, Schema $actual, Schema $expected)
    {
        $describer = new MethodDescriber();
        $describer->describeProperty($property, $actual);

        $this->assertEquals($expected, $actual);
    }

    public static function propertyProvider(): iterable
    {
        yield 'property' => [
            new PropertySubject('name', new \ReflectionProperty(User::class, 'name')),
            new Schema(type: 'string'),
            new Schema(type: 'string'),
        ];

        yield 'constructor promoted property' => [
            new PropertySubject('name', new \ReflectionParameter([UserWithConstructor::class, '__construct'], 'name')),
            new Schema(type: 'string'),
            new Schema(type: 'string', description: 'The name of the user in lowercase'),
        ];
    }

    /**
     * @param list<string> $expectedPropertyNames
     */
    #[DataProvider('modelProvider')]
    public function testDescribeModel(ObjectSubject $model, array $expectedPropertyNames)
    {
        $describer = new MethodDescriber();
        $actualProperties = $describer->describeObject($model, new Schema());

        $this->assertSame($expectedPropertyNames, array_map(static fn (PropertySubject $property) => $property->getName(), iterator_to_array($actualProperties)));
    }

    public static function modelProvider(): iterable
    {
        yield 'user with constructor' => [
            new ObjectSubject('name', new \ReflectionMethod(UserWithConstructor::class, '__construct')),
            ['id', 'name', 'createdAt', 'isActive', 'age'],
        ];
    }
}
