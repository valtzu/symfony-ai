<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Contract\SchemaNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\Normalizer\SchemaNormalizer as BaseSchemaNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Serializer;

final class SchemaNormalizerTest extends TestCase
{
    public function testSupportsNormalizationWithGeminiModel()
    {
        $normalizer = new SchemaNormalizer();
        $context = [Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash')];

        $this->assertTrue($normalizer->supportsNormalization(new Schema(), context: $context));
    }

    public function testDoesNotSupportNormalizationWithoutModel()
    {
        $normalizer = new SchemaNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Schema()));
    }

    public function testDoesNotSupportNormalizationWithNonGeminiModel()
    {
        $normalizer = new SchemaNormalizer();
        $context = [Contract::CONTEXT_MODEL => new Model('other-model')];

        $this->assertFalse($normalizer->supportsNormalization(new Schema(), context: $context));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Schema::class => false], (new SchemaNormalizer())->getSupportedTypes('json'));
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(Schema $schema, array $expected)
    {
        $serializer = new Serializer([new SchemaNormalizer(), new BaseSchemaNormalizer()]);
        $context = [Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash')];

        $this->assertSame($expected, $serializer->normalize($schema, 'json', $context));
    }

    public static function normalizeProvider(): \Generator
    {
        yield 'removes additionalProperties' => [
            new Schema(type: 'object', properties: ['name' => new Schema(type: 'string')], additionalProperties: false),
            ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ];

        yield 'converts nullable array type to nullable flag' => [
            new Schema(type: ['string', 'null']),
            ['type' => 'string', 'nullable' => true],
        ];

        yield 'preserves multi-type array without null' => [
            new Schema(type: ['string', 'integer']),
            ['type' => ['string', 'integer']],
        ];

        yield 'nested additionalProperties removed via chain' => [
            new Schema(
                type: 'object',
                properties: [
                    'child' => new Schema(type: 'object', additionalProperties: false),
                ],
                additionalProperties: false,
            ),
            [
                'type' => 'object',
                'properties' => [
                    'child' => ['type' => 'object'],
                ],
            ],
        ];

        yield 'nested nullable converted via chain' => [
            new Schema(
                type: 'object',
                properties: [
                    'value' => new Schema(type: ['integer', 'null']),
                ],
            ),
            [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'integer', 'nullable' => true],
                ],
            ],
        ];
    }
}
