<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\Normalizer\SchemaNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SchemaNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new SchemaNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Schema()));
        $this->assertFalse($normalizer->supportsNormalization('not a schema'));
        $this->assertFalse($normalizer->supportsNormalization([]));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Schema::class => true], (new SchemaNormalizer())->getSupportedTypes(null));
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(Schema $schema, array $expected)
    {
        $this->assertEquals($expected, (new SchemaNormalizer())->normalize($schema));
    }

    public static function normalizeProvider(): \Generator
    {
        yield 'empty schema' => [new Schema(), []];

        yield 'type only' => [new Schema(type: 'string'), ['type' => 'string']];

        yield 'array type' => [new Schema(type: ['string', 'null']), ['type' => ['string', 'null']]];

        yield 'string constraints' => [
            new Schema(type: 'string', minLength: 2, maxLength: 10, pattern: '^[a-z]+$'),
            ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'pattern' => '^[a-z]+$'],
        ];

        yield 'number constraints' => [
            new Schema(type: 'number', minimum: 0.0, maximum: 100.0, multipleOf: 0.5),
            ['type' => 'number', 'minimum' => 0.0, 'maximum' => 100.0, 'multipleOf' => 0.5],
        ];

        yield 'enum' => [
            new Schema(type: 'string', enum: ['a', 'b', 'c']),
            ['type' => 'string', 'enum' => ['a', 'b', 'c']],
        ];

        yield 'const' => [
            new Schema(const: 42),
            ['const' => 42],
        ];

        yield 'nullable' => [
            new Schema(type: 'string', nullable: true),
            ['type' => 'string', 'nullable' => true],
        ];

        yield 'array schema' => [
            new Schema(type: 'array', items: new Schema(type: 'integer'), minItems: 1, maxItems: 5),
            ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1, 'maxItems' => 5],
        ];

        yield 'object with properties and required' => [
            new Schema(
                type: 'object',
                properties: [
                    'name' => new Schema(type: 'string', description: 'Full name'),
                    'age' => new Schema(type: 'integer'),
                ],
                required: ['name'],
                additionalProperties: false,
            ),
            [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Full name'],
                    'age' => ['type' => 'integer'],
                ],
                'required' => ['name'],
                'additionalProperties' => false,
            ],
        ];

        yield 'anyOf' => [
            new Schema(anyOf: [new Schema(type: 'string'), new Schema(type: 'integer')]),
            ['anyOf' => [['type' => 'string'], ['type' => 'integer']]],
        ];

        yield 'not' => [
            new Schema(not: new Schema(enum: ['forbidden'])),
            ['not' => ['enum' => ['forbidden']]],
        ];
    }

    public function testNormalizeNestedSchemasDispatchThroughChain()
    {
        $interceptor = new class implements \Symfony\Component\Serializer\Normalizer\NormalizerInterface {
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return $data instanceof Schema && 'string' === $data->type;
            }

            public function getSupportedTypes(?string $format): array
            {
                return [Schema::class => false];
            }

            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                return ['type' => 'intercepted'];
            }
        };

        $serializer = new Serializer([$interceptor, new SchemaNormalizer()]);
        $schema = new Schema(
            type: 'object',
            properties: [
                'a' => new Schema(type: 'string'),
                'b' => new Schema(type: 'integer'),
            ],
        );

        $result = $serializer->normalize($schema);

        $this->assertSame(['type' => 'intercepted'], $result['properties']['a']);
        $this->assertSame(['type' => 'integer'], $result['properties']['b']);
    }
}
