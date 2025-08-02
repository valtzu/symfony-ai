<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\AudioStream;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('audio_stream')]
readonly class TwigComponent
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(param: 'env(GEMINI_API_KEY)')]
        private string $apiKey,
    ) {
    }

    public function getApiKey(): string
    {
        return $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1alpha/auth_tokens', [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
            ],
            'json' => [
                'bidiGenerateContentSetup' => [
                    'model' => 'models/gemini-2.5-flash-preview-native-audio-dialog',
//                    'model' => 'models/gemini-2.0-flash-live-001',
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'responseModalities' => ['AUDIO'],
                    ],
                ],
            ],
        ])->toArray()['name'];
    }
}
