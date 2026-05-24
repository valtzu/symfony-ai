<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\ToolNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\Normalizer\SchemaNormalizer;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Serializer;

class ToolNormalizerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer([new SchemaNormalizer(), new ToolNormalizer()]);
    }

    /**
     * @param array{type: 'function', name: string, description: string, parameters?: array<string, mixed>} $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(array $expected, Tool $tool)
    {
        $actual = $this->serializer->normalize($tool, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);
        $this->assertEquals($expected, $actual);
    }

    public static function normalizeProvider(): \Generator
    {
        $tool = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description');

        $expected = [
            'type' => 'function',
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
        ];

        $parameters = new Schema(
            type: 'object',
            properties: [
                'text' => new Schema(type: 'string', description: 'The text given to the tool'),
            ],
            required: ['text'],
            additionalProperties: false,
        );

        yield 'no parameters' => [$expected, $tool];
        yield 'with parameters' => [
            array_merge($expected, [
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string', 'description' => 'The text given to the tool'],
                    ],
                    'required' => ['text'],
                    'additionalProperties' => false,
                ],
            ]),
            new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description', $parameters),
        ];
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new ToolNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $tool = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description');
        $gpt = new Gpt('o3');

        yield 'supported' => [$tool, $gpt, true];
        yield 'unsupported model' => [$tool, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
