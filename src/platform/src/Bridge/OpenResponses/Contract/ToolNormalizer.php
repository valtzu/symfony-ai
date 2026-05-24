<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Contract;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
class ToolNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param Tool $data
     *
     * @return array{
     *     type: 'function',
     *     name: string,
     *     description: string,
     *     parameters?: array<string, mixed>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $function = [
            'type' => 'function',
            'name' => $data->getName(),
            'description' => $data->getDescription(),
        ];

        if (null !== $data->getParameters()) {
            $function['parameters'] = $this->normalizer->normalize($data->getParameters(), $format, $context);
        }

        return $function;
    }

    protected function supportedDataClass(): string
    {
        return Tool::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ResponsesModel;
    }
}
