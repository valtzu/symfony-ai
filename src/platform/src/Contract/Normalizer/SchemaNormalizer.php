<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class SchemaNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Schema;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Schema::class => true];
    }

    /**
     * @param Schema $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (null !== $data->ref) {
            /** @var array<string, mixed> */
            return json_decode((string) file_get_contents($data->ref), true, flags: \JSON_THROW_ON_ERROR);
        }

        $result = [];

        if (null !== $data->type) {
            $result['type'] = $data->type;
        }
        if (null !== $data->format) {
            $result['format'] = $data->format;
        }
        if (null !== $data->description) {
            $result['description'] = $data->description;
        }
        if (null !== $data->example) {
            $result['example'] = $data->example;
        }
        if (null !== $data->pattern) {
            $result['pattern'] = $data->pattern;
        }
        if (null !== $data->enum) {
            $result['enum'] = $data->enum;
        }
        if (null !== $data->const) {
            $result['const'] = $data->const;
        }
        if (null !== $data->minLength) {
            $result['minLength'] = $data->minLength;
        }
        if (null !== $data->maxLength) {
            $result['maxLength'] = $data->maxLength;
        }
        if (null !== $data->minimum) {
            $result['minimum'] = $data->minimum;
        }
        if (null !== $data->maximum) {
            $result['maximum'] = $data->maximum;
        }
        if (null !== $data->multipleOf) {
            $result['multipleOf'] = $data->multipleOf;
        }
        if (null !== $data->exclusiveMinimum) {
            $result['exclusiveMinimum'] = $data->exclusiveMinimum;
        }
        if (null !== $data->exclusiveMaximum) {
            $result['exclusiveMaximum'] = $data->exclusiveMaximum;
        }
        if (null !== $data->minItems) {
            $result['minItems'] = $data->minItems;
        }
        if (null !== $data->maxItems) {
            $result['maxItems'] = $data->maxItems;
        }
        if (null !== $data->uniqueItems) {
            $result['uniqueItems'] = $data->uniqueItems;
        }
        if (null !== $data->minContains) {
            $result['minContains'] = $data->minContains;
        }
        if (null !== $data->maxContains) {
            $result['maxContains'] = $data->maxContains;
        }
        if (null !== $data->minProperties) {
            $result['minProperties'] = $data->minProperties;
        }
        if (null !== $data->maxProperties) {
            $result['maxProperties'] = $data->maxProperties;
        }
        if (null !== $data->dependentRequired) {
            $result['dependentRequired'] = $data->dependentRequired;
        }
        if (null !== $data->nullable) {
            $result['nullable'] = $data->nullable;
        }
        if (null !== $data->contentMediaType) {
            $result['contentMediaType'] = $data->contentMediaType;
        }
        if (null !== $data->properties) {
            $result['properties'] = array_map(fn (Schema $p) => $this->normalizeSubSchema($p, $format, $context), $data->properties);
        }
        if (null !== $data->items) {
            $result['items'] = $this->normalizeSubSchema($data->items, $format, $context);
        }
        if (null !== $data->anyOf) {
            $result['anyOf'] = array_map(fn (Schema $s) => $this->normalizeSubSchema($s, $format, $context), $data->anyOf);
        }
        if (null !== $data->oneOf) {
            $result['oneOf'] = array_map(fn (Schema $s) => $this->normalizeSubSchema($s, $format, $context), $data->oneOf);
        }
        if (null !== $data->allOf) {
            $result['allOf'] = array_map(fn (Schema $s) => $this->normalizeSubSchema($s, $format, $context), $data->allOf);
        }
        if (null !== $data->not) {
            $result['not'] = $this->normalizeSubSchema($data->not, $format, $context);
        }
        if (null !== $data->required) {
            $result['required'] = $data->required;
        }
        if (null !== $data->additionalProperties) {
            $result['additionalProperties'] = $data->additionalProperties instanceof Schema
                ? $this->normalizeSubSchema($data->additionalProperties, $format, $context)
                : $data->additionalProperties;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeSubSchema(Schema $schema, ?string $format, array $context): array
    {
        if (isset($this->normalizer)) {
            return $this->normalizer->normalize($schema, $format, $context);
        }

        return $this->normalize($schema, $format, $context);
    }
}
