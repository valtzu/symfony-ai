<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface ResponseFormatFactoryInterface
{
    /**
     * @param class-string $responseClass
     *
     * @return array{
     *     type: 'json_schema',
     *     json_schema: array{
     *         name: string,
     *         schema: Schema|null,
     *         strict: true,
     *     }
     * }
     */
    public function create(string $responseClass): array;
}
