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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Platform\Bridge\Gemini\Contract\SchemaNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Serializer;

final class ToolNormalizerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer([new SchemaNormalizer(), new ToolNormalizer()]);
    }

    public function testSupportsNormalization()
    {
        $normalizer = new ToolNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Tool(new ExecutionReference(ToolNoParams::class), 'test', 'test'), context: [
            Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a tool'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new ToolNormalizer();

        $expected = [
            Tool::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{name: string, description: string, parameters: array<string, mixed>|null} $expected
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(Tool $tool, array $expected)
    {
        $context = [Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash')];
        $normalized = $this->serializer->normalize($tool, null, $context);

        $this->assertEquals($expected, $normalized);
    }

    /**
     * @return iterable<array{0: Tool, 1: array}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'call with params' => [
            new Tool(
                new ExecutionReference(ToolRequiredParams::class, 'bar'),
                'tool_required_params',
                'A tool with required parameters',
                new Schema(
                    type: 'object',
                    properties: [
                        'text' => new Schema(type: 'string', description: 'Text parameter'),
                        'number' => new Schema(type: 'integer', description: 'Number parameter'),
                        'nestedObject' => new Schema(type: 'object', description: 'bar', additionalProperties: false),
                    ],
                    required: ['text', 'number'],
                    additionalProperties: false,
                ),
            ),
            [
                'description' => 'A tool with required parameters',
                'name' => 'tool_required_params',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'Text parameter',
                        ],
                        'number' => [
                            'type' => 'integer',
                            'description' => 'Number parameter',
                        ],
                        'nestedObject' => [
                            'type' => 'object',
                            'description' => 'bar',
                        ],
                    ],
                    'required' => ['text', 'number'],
                ],
            ],
        ];

        yield 'call without params' => [
            new Tool(
                new ExecutionReference(ToolNoParams::class, 'bar'),
                'tool_no_params',
                'A tool without parameters',
                null,
            ),
            [
                'description' => 'A tool without parameters',
                'name' => 'tool_no_params',
                'parameters' => null,
            ],
        ];

        yield 'call with nullable parameter' => [
            new Tool(
                new ExecutionReference(ToolRequiredParams::class, 'bar'),
                'tool_nullable_param',
                'A tool with nullable parameter',
                new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: ['string', 'null'], description: 'A nullable name'),
                    ],
                    additionalProperties: false,
                ),
            ),
            [
                'description' => 'A tool with nullable parameter',
                'name' => 'tool_nullable_param',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'nullable' => true,
                            'description' => 'A nullable name',
                        ],
                    ],
                ],
            ],
        ];

        yield 'call with $schema meta-key' => [
            new Tool(
                new ExecutionReference(ToolRequiredParams::class, 'bar'),
                'tool_with_schema_meta_key',
                'A tool whose schema carries the JSON-Schema $schema meta-key (e.g. produced by an MCP server)',
                new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: 'string', description: 'Name parameter'),
                        'nested' => new Schema(
                            type: 'object',
                            properties: [
                                'inner' => new Schema(type: 'string'),
                            ],
                        ),
                    ],
                    required: ['name'],
                    additionalProperties: false,
                ),
            ),
            [
                'description' => 'A tool whose schema carries the JSON-Schema $schema meta-key (e.g. produced by an MCP server)',
                'name' => 'tool_with_schema_meta_key',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name parameter',
                        ],
                        'nested' => [
                            'type' => 'object',
                            'properties' => [
                                'inner' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];

        yield 'call with nested nullable parameter' => [
            new Tool(
                new ExecutionReference(ToolRequiredParams::class, 'bar'),
                'tool_nested_nullable',
                'A tool with nested nullable parameter',
                new Schema(
                    type: 'object',
                    properties: [
                        'user' => new Schema(
                            type: 'object',
                            properties: [
                                'age' => new Schema(type: ['integer', 'null'], description: 'User age'),
                            ],
                            additionalProperties: false,
                        ),
                    ],
                    additionalProperties: false,
                ),
            ),
            [
                'description' => 'A tool with nested nullable parameter',
                'name' => 'tool_nested_nullable',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user' => [
                            'type' => 'object',
                            'properties' => [
                                'age' => [
                                    'type' => 'integer',
                                    'nullable' => true,
                                    'description' => 'User age',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
