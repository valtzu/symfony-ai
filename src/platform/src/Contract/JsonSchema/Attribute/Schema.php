<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Attribute;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Schema
{
    /**
     * @param string|list<string>|null         $type
     * @param array<string, self>|null         $properties
     * @param list<self>|null                  $anyOf
     * @param list<self>|null                  $oneOf
     * @param list<self>|null                  $allOf
     * @param list<string>|null                $required
     * @param list<int|float|string|null>|null $enum
     * @param string|null                      $ref        A path to external schema file. This is mutually exclusive with all the other arguments.
     */
    public function __construct(
        // structural
        public string|array|null $type = null,
        public ?string $format = null,
        public ?array $properties = null,
        public ?self $items = null,
        public ?array $anyOf = null,
        public ?array $oneOf = null,
        public ?array $allOf = null,
        public ?self $not = null,
        public ?array $required = null,
        public bool|self|null $additionalProperties = null,
        public ?bool $nullable = null,
        public ?string $contentMediaType = null,

        // can be used by many types
        public ?string $description = null,
        public mixed $example = null,
        public ?array $enum = null,
        public mixed $const = null,

        // string
        public ?string $pattern = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,

        // number
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public int|float|null $multipleOf = null,
        public int|float|bool|null $exclusiveMinimum = null,
        public int|float|bool|null $exclusiveMaximum = null,

        // array
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,

        // object
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public ?bool $dependentRequired = null,

        // a reference to a schema file
        public ?string $ref = null,
    ) {
        if ($this->ref) {
            if (\count(array_filter((array) $this, static fn (mixed $value) => null !== $value)) > 1) {
                throw new InvalidArgumentException('When "ref" is defined, no other arguments are allowed.');
            }

            if (!is_readable($this->ref)) {
                throw new InvalidArgumentException(\sprintf('The provided schema file "%s" is not readable', $this->ref));
            }

            return;
        }

        if (\is_array($enum)) {
            /* @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (array_filter($enum, static fn (mixed $item) => null === $item || \is_int($item) || \is_float($item) || \is_string($item)) !== $enum) {
                throw new InvalidArgumentException('All enum values must be float, integer, strings, or null.');
            }
        }

        if (\is_string($description)) {
            if ('' === trim($description)) {
                throw new InvalidArgumentException('Description string must not be empty.');
            }
        }

        if (\is_string($const)) {
            if ('' === trim($const)) {
                throw new InvalidArgumentException('Const string must not be empty.');
            }
        }

        if (\is_string($pattern)) {
            if ('' === trim($pattern)) {
                throw new InvalidArgumentException('Pattern string must not be empty.');
            }
        }

        if (\is_int($minLength)) {
            if ($minLength < 0) {
                throw new InvalidArgumentException('MinLength must be greater than or equal to 0.');
            }

            if (\is_int($maxLength)) {
                if ($maxLength < $minLength) {
                    throw new InvalidArgumentException('MaxLength must be greater than or equal to minLength.');
                }
            }
        }

        if (\is_int($maxLength)) {
            if ($maxLength < 0) {
                throw new InvalidArgumentException('MaxLength must be greater than or equal to 0.');
            }
        }

        if (null !== $minimum && null !== $maximum && $maximum < $minimum) {
            throw new InvalidArgumentException('Maximum must be greater than or equal to minimum.');
        }

        if (null !== $multipleOf && $multipleOf < 0) {
            throw new InvalidArgumentException('MultipleOf must be greater than or equal to 0.');
        }

        if (null !== $exclusiveMinimum && null !== $exclusiveMaximum && \is_numeric($exclusiveMinimum) && \is_numeric($exclusiveMaximum) && $exclusiveMaximum < $exclusiveMinimum) {
            throw new InvalidArgumentException('ExclusiveMaximum must be greater than or equal to exclusiveMinimum.');
        }

        if (\is_int($minItems)) {
            if ($minItems < 0) {
                throw new InvalidArgumentException('MinItems must be greater than or equal to 0.');
            }

            if (\is_int($maxItems)) {
                if ($maxItems < $minItems) {
                    throw new InvalidArgumentException('MaxItems must be greater than or equal to minItems.');
                }
            }
        }

        if (\is_int($maxItems)) {
            if ($maxItems < 0) {
                throw new InvalidArgumentException('MaxItems must be greater than or equal to 0.');
            }
        }

        if (\is_bool($uniqueItems)) {
            if (true !== $uniqueItems) {
                throw new InvalidArgumentException('UniqueItems must be true when specified.');
            }
        }

        if (\is_int($minContains)) {
            if ($minContains < 0) {
                throw new InvalidArgumentException('MinContains must be greater than or equal to 0.');
            }

            if (\is_int($maxContains)) {
                if ($maxContains < $minContains) {
                    throw new InvalidArgumentException('MaxContains must be greater than or equal to minContains.');
                }
            }
        }

        if (\is_int($maxContains)) {
            if ($maxContains < 0) {
                throw new InvalidArgumentException('MaxContains must be greater than or equal to 0.');
            }
        }

        if (\is_int($minProperties)) {
            if ($minProperties < 0) {
                throw new InvalidArgumentException('MinProperties must be greater than or equal to 0.');
            }

            if (\is_int($maxProperties)) {
                if ($maxProperties < $minProperties) {
                    throw new InvalidArgumentException('MaxProperties must be greater than or equal to minProperties.');
                }
            }
        }

        if (\is_int($maxProperties)) {
            if ($maxProperties < 0) {
                throw new InvalidArgumentException('MaxProperties must be greater than or equal to 0.');
            }
        }
    }

    public function isEmpty(): bool
    {
        return array_all((array) $this, static fn ($value) => null === $value);
    }
}
