<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

use Symfony\AI\Platform\Contract\JsonSchema\Subject\ObjectSubject;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class CacheDescriber implements ObjectDescriberInterface
{
    /**
     * @param ServiceProviderInterface<array<string, \ArrayObject>> $objects
     */
    public function __construct(
        private readonly ServiceProviderInterface $objects,
        private readonly ObjectDescriberInterface $fallbackDescriber,
    ) {
    }

    public function describeObject(ObjectSubject $subject, ?array &$schema): iterable
    {
        if ($this->objects->has($subject->getName())) {
            $schema = $this->objects->get($subject->getName())->getArrayCopy();
        }

        return $this->fallbackDescriber->describeObject($subject, $schema);
    }
}
