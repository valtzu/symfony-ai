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
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\CPPWithAtVarDocFixture;

final class DescriberTest extends TestCase
{
    public function testDescribeObject()
    {
        $describer = new Describer();
        $actual = new Schema();
        $describer->describeObject(new ObjectSubject(CPPWithAtVarDocFixture::class, new \ReflectionClass(CPPWithAtVarDocFixture::class)), $actual);

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
                ],
                required: ['steps'],
                additionalProperties: false,
            ),
            $actual,
        );
    }
}
