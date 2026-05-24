<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolOptionalParam;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithBackedEnums;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithObjectAccessors;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithToolParameterAttribute;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ExampleDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SchemaAttributeValuesDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Step;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;

final class FactoryTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Factory();
    }

    protected function tearDown(): void
    {
        unset($this->factory);
    }

    public function testBuildParametersDefinitionRequired()
    {
        $actual = $this->factory->buildParameters(ToolRequiredParams::class, 'bar');

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'text' => new Schema(type: 'string', description: 'The text given to the tool'),
                    'number' => new Schema(type: 'integer', description: 'A number given to the tool'),
                ],
                required: ['text', 'number'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildParametersDefinitionRequiredWithAdditionalToolParameterAttribute()
    {
        $actual = $this->factory->buildParameters(ToolWithToolParameterAttribute::class, '__invoke');

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'animal' => new Schema(type: 'string', description: 'The animal given to the tool', enum: ['dog', 'cat', 'bird']),
                    'numberOfArticles' => new Schema(type: 'integer', description: 'The number of articles given to the tool', const: 42),
                    'infoEmail' => new Schema(type: 'string', description: 'The info email given to the tool', const: 'info@example.de'),
                    'locales' => new Schema(type: 'string', description: 'The locales given to the tool', const: ['de', 'en']),
                    'text' => new Schema(type: 'string', description: 'The text given to the tool', pattern: '^[a-zA-Z]+$', minLength: 1, maxLength: 10),
                    'number' => new Schema(type: 'integer', description: 'The number given to the tool', minimum: 1, maximum: 10, multipleOf: 2, exclusiveMinimum: 1, exclusiveMaximum: 10),
                    'products' => new Schema(type: 'array', description: 'The products given to the tool', minItems: 1, maxItems: 10, uniqueItems: true, minContains: 1, maxContains: 10),
                    'shippingAddress' => new Schema(type: 'string', description: 'The shipping address given to the tool', minProperties: 1, maxProperties: 10, dependentRequired: true),
                ],
                required: ['animal', 'numberOfArticles', 'infoEmail', 'locales', 'text', 'number', 'products', 'shippingAddress'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildParametersDefinitionOptional()
    {
        $actual = $this->factory->buildParameters(ToolOptionalParam::class, 'bar');

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'text' => new Schema(type: 'string', description: 'The text given to the tool'),
                    'number' => new Schema(type: 'integer', description: 'A number given to the tool'),
                ],
                required: ['text'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildParametersDefinitionNone()
    {
        $actual = $this->factory->buildParameters(ToolNoParams::class, '__invoke');

        $this->assertNull($actual);
    }

    public function testBuildPropertiesForUserClass()
    {
        $actual = $this->factory->buildProperties(User::class);

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'id' => new Schema(type: 'integer'),
                    'name' => new Schema(type: 'string', description: 'The name of the user in lowercase'),
                    'createdAt' => new Schema(type: 'string', format: 'date-time'),
                    'isActive' => new Schema(type: 'boolean'),
                    'age' => new Schema(type: ['integer', 'null']),
                ],
                required: ['id', 'name', 'createdAt', 'isActive', 'age'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildPropertiesForMathReasoningClass()
    {
        $actual = $this->factory->buildProperties(MathReasoning::class);

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'steps' => new Schema(
                        type: 'array',
                        items: new Schema(
                            type: 'object',
                            properties: [
                                'explanation' => new Schema(type: 'string'),
                                'output' => new Schema(type: 'string'),
                            ],
                            required: ['explanation', 'output'],
                            additionalProperties: false,
                        ),
                    ),
                    'finalAnswer' => new Schema(type: 'string'),
                    'result' => new Schema(type: 'number'),
                ],
                required: ['steps', 'finalAnswer', 'result'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildPropertiesForListOfPolymorphicTypesDto()
    {
        $actual = $this->factory->buildProperties(ListOfPolymorphicTypesDto::class);

        $expected = new Schema(
            type: 'object',
            properties: [
                'items' => new Schema(
                    type: 'array',
                    items: new Schema(
                        anyOf: [
                            new Schema(
                                type: 'object',
                                properties: [
                                    'name' => new Schema(type: 'string'),
                                    'type' => new Schema(type: 'string', pattern: '^name$', enum: ['name']),
                                ],
                                required: ['name', 'type'],
                                additionalProperties: false,
                            ),
                            new Schema(
                                type: 'object',
                                properties: [
                                    'age' => new Schema(type: 'integer'),
                                    'type' => new Schema(type: 'string', pattern: '^age$', enum: ['age']),
                                ],
                                required: ['age', 'type'],
                                additionalProperties: false,
                            ),
                        ],
                    ),
                ),
            ],
            required: ['items'],
            additionalProperties: false,
        );

        $this->assertEquals($expected, $actual);
        $this->assertSame($expected->type, $actual->type);
        $this->assertSame($expected->required, $actual->required);
    }

    public function testBuildPropertiesForUnionTypeDto()
    {
        $actual = $this->factory->buildProperties(UnionTypeDto::class);

        $expected = new Schema(
            type: 'object',
            properties: [
                'time' => new Schema(
                    anyOf: [
                        new Schema(
                            type: 'object',
                            properties: ['readableTime' => new Schema(type: 'string')],
                            required: ['readableTime'],
                            additionalProperties: false,
                        ),
                        new Schema(
                            type: 'object',
                            properties: ['timestamp' => new Schema(type: 'integer')],
                            required: ['timestamp'],
                            additionalProperties: false,
                        ),
                        new Schema(type: 'null'),
                    ],
                ),
            ],
            required: ['time'],
            additionalProperties: false,
        );

        $this->assertEquals($expected, $actual);
        $this->assertSame($expected->type, $actual->type);
        $this->assertSame($expected->required, $actual->required);
    }

    public function testBuildPropertiesForStepClass()
    {
        $actual = $this->factory->buildProperties(Step::class);

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'explanation' => new Schema(type: 'string'),
                    'output' => new Schema(type: 'string'),
                ],
                required: ['explanation', 'output'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildPropertiesForExampleDto()
    {
        $actual = $this->factory->buildProperties(ExampleDto::class);

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'name' => new Schema(type: 'string'),
                    'taxRate' => new Schema(type: 'integer', enum: [7, 19]),
                    'category' => new Schema(type: ['string', 'null'], enum: ['Foo', 'Bar', null]),
                    'quantity' => new Schema(type: ['string', 'null'], description: 'The quantity of the ingredient', example: '2 cups'),
                ],
                required: ['name', 'taxRate', 'category', 'quantity'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildPropertiesAttributeValuesWin()
    {
        $actual = $this->factory->buildProperties(SchemaAttributeValuesDto::class);

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'name' => new Schema(type: 'string', description: 'This is the attribute description.', example: 'Attribute example'),
                ],
                required: ['name'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildParametersWithBackedEnums()
    {
        $actual = $this->factory->buildParameters(ToolWithBackedEnums::class, '__invoke');

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'searchTerms' => new Schema(type: 'array', description: 'The search terms', items: new Schema(type: 'string')),
                    'mode' => new Schema(type: 'string', description: 'The search mode', enum: ['and', 'or', 'not']),
                    'priority' => new Schema(type: 'integer', description: 'The search priority', enum: [1, 5, 10]),
                    'fallback' => new Schema(type: ['string', 'null'], description: 'Optional fallback mode', enum: ['and', 'or', 'not']),
                ],
                required: ['searchTerms', 'mode', 'priority'],
                additionalProperties: false,
            ),
            $actual,
        );
    }

    public function testBuildParametersWithObjectAccessors()
    {
        $actual = $this->factory->buildParameters(ToolWithObjectAccessors::class, '__invoke');

        $this->assertEquals(
            new Schema(
                type: 'object',
                properties: [
                    'object' => new Schema(
                        type: 'object',
                        properties: [
                            'value1' => new Schema(type: 'integer', minimum: 1),
                            'value2' => new Schema(type: 'number', const: 42),
                            'value3' => new Schema(type: 'string', pattern: '^foo$'),
                        ],
                        required: ['value1', 'value2', 'value3'],
                        additionalProperties: false,
                    ),
                ],
                required: ['object'],
                additionalProperties: false,
            ),
            $actual,
        );
    }
}
