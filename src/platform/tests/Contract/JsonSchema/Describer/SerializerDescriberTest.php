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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ObjectDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SerializerDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemAge;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemDiscriminator;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListItemName;

final class SerializerDescriberTest extends TestCase
{
    public function testDescribeDiscriminatorMapObject()
    {
        $modelDescriber = new class implements ObjectDescriberInterface {
            public function describeObject(ObjectSubject $subject, Schema $schema): iterable
            {
                if ($subject->getReflector() instanceof \ReflectionClass) {
                    $schema->description = $subject->getName();
                }

                return [];
            }
        };

        $describer = new SerializerDescriber();
        $describer->setObjectDescriber($modelDescriber);
        $schema = new Schema();

        $describer->describeObject(new ObjectSubject(ListItemDiscriminator::class, new \ReflectionClass(ListItemDiscriminator::class)), $schema);

        $expectedSchema = new Schema(anyOf: [
            new Schema(
                description: ListItemName::class,
                properties: [
                    'type' => new Schema(enum: ['name']),
                ],
            ),
            new Schema(
                description: ListItemAge::class,
                properties: [
                    'type' => new Schema(enum: ['age']),
                ],
            ),
        ]);

        $this->assertEquals($expectedSchema, $schema);
    }
}
