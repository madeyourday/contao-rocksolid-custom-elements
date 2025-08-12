<?php

namespace MadeYourDay\RockSolidCustomElements\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'preg_replace',
                static function (string|array $subject, string|array $pattern, string|array $replacement, int $limit = -1): string|array {
                    return preg_replace($pattern, $replacement, $subject, $limit)
                        ?? throw new \RuntimeException(\sprintf('Error in regular expression "%s".', $pattern))
                    ;
                },
            ),
        ];
    }
}
