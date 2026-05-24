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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\TypeInfoDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\TypeInfoFixture;

final class TypeInfoDescriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedSchema
     */
    #[TestWith([new Schema(type: ['integer', 'null']), TypeInfoFixture::class, 'nullableInt'], 'add null for nullable scalar')]
    #[TestWith([new Schema(type: 'integer', enum: [1, 5]), TypeInfoFixture::class, 'backedEnum'], 'backed enum')]
    #[TestWith([new Schema(type: ['integer', 'null'], enum: [1, 5]), TypeInfoFixture::class, 'nullableBackedEnum'], 'nullable backed enum')]
    public function testDescribeAddsNullTypeForNullableScalar(Schema $expectedSchema, string $class, string $property)
    {
        $describer = new TypeInfoDescriber();
        $schema = new Schema();

        $describer->describeProperty(new PropertySubject($property, new \ReflectionProperty($class, $property)), $schema);

        $this->assertEquals($expectedSchema, $schema);
    }

    public function testDescribeThrowsForBuiltinObjectType()
    {
        $describer = new TypeInfoDescriber();
        $schema = new Schema();

        $this->expectException(InvalidArgumentException::class);

        $describer->describeProperty(new PropertySubject('payload', new \ReflectionProperty(TypeInfoFixture::class, 'payload')), $schema);
    }
}
