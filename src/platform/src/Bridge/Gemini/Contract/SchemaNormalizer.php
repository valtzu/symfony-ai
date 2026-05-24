<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Contract;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\SchemaNormalizer as BaseSchemaNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes a JSON Schema for Gemini compatibility.
 *
 * - Removes 'additionalProperties' (not supported by Gemini)
 * - Converts array-style nullable types ['string', 'null'] to ['type' => 'string', 'nullable' => true]
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class SchemaNormalizer extends ModelContractNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    public function __construct(private readonly BaseSchemaNormalizer $inner = new BaseSchemaNormalizer())
    {
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->inner->setNormalizer($normalizer);
    }

    /**
     * @param Schema $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $result = $this->inner->normalize($data, $format, $context);

        unset($result['additionalProperties']);

        if (isset($result['type']) && \is_array($result['type'])) {
            $nullIndex = array_search('null', $result['type'], true);
            if (false !== $nullIndex) {
                $types = $result['type'];
                unset($types[$nullIndex]);
                $types = array_values($types);

                if (1 === \count($types)) {
                    $result['type'] = $types[0];
                    $result['nullable'] = true;
                }
            }
        }

        return $result;
    }

    protected function supportedDataClass(): string
    {
        return Schema::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }
}
