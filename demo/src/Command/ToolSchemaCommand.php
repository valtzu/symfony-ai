<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('debug:tool-schema')]
class ToolSchemaCommand
{
    public function __construct(#[Autowire('@ai.platform.json_schema_factory')] private Factory $factory)
    {
    }

    public function __invoke(OutputInterface $output)
    {
        $output->writeln(json_encode($this->factory->buildParameters(SimilaritySearch::class, '__invoke'), \JSON_PRETTY_PRINT));

        return 0;
    }
}
