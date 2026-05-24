<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\Describer;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\ObjectDescriberInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Factory
{
    public function __construct(
        private readonly ObjectDescriberInterface $objectDescriber = new Describer(),
    ) {
    }

    public function buildParameters(string $className, string $methodName): ?Schema
    {
        $schema = new Schema();
        $this->objectDescriber->describeObject(new ObjectSubject($className.'::'.$methodName, new \ReflectionMethod($className, $methodName)), $schema);

        return $this->toSchemaOrNull($schema);
    }

    public function buildProperties(string $className): ?Schema
    {
        $schema = new Schema();
        $this->objectDescriber->describeObject(new ObjectSubject($className, new \ReflectionClass($className)), $schema);

        return $this->toSchemaOrNull($schema);
    }

    private function toSchemaOrNull(Schema $schema): ?Schema
    {
        if ($schema->isEmpty()) {
            return null;
        }

        if ('object' === $schema->type) {
            $copy = clone $schema;
            $copy->type = null;
            if ($copy->isEmpty()) {
                return null;
            }
        }

        return $schema;
    }
}
