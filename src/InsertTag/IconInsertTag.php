<?php

declare(strict_types=1);

/**
 * @copyright rocksolidthemes.com
 * @license https://rocksolidthemes.com/license proprietary
 */

namespace MadeYourDay\RockSolidCustomElements\InsertTag;

use Contao\CoreBundle\DependencyInjection\Attribute\AsInsertTag;
use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\OutputType;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\CoreBundle\InsertTag\Resolver\InsertTagResolverNestedResolvedInterface;
use Contao\Validator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;

#[AsInsertTag('icon')]
class IconInsertTag implements InsertTagResolverNestedResolvedInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $webDir,
    ) {
    }

    public function __invoke(ResolvedInsertTag $insertTag): InsertTagResult
    {
        $icon = $insertTag->getParameters()->get(0);

        if (!Validator::isUuid($icon)) {
            if (!str_contains($icon, '.')) {
                $icon .= '.svg';
            }
            $icons = [
                "files/icons/$icon",
                "files/$icon",
            ];
        }

        $templates = ['@Contao/rsce_icon.html.twig'];

        if (
            ($templateGroup = ($GLOBALS['objPage'] ?? null)?->templateGroup)
            && str_starts_with($templateGroup, 'templates/')
        ) {
            $theme = substr($templateGroup, \strlen('templates/'));
            array_unshift($templates, "@Contao/$theme/rsce_{$theme}_icon.html.twig");

            if (!Validator::isUuid($icon)) {
                array_unshift(
                    $icons,
                    "files/$theme/icons/$icon",
                    "files/$theme/$icon",
                );
            }
        }

        if (Validator::isUuid($icon)) {
            $iconPath = null;
        } else {
            $iconPath = null;
            foreach ($icons as $icon) {
                if (file_exists($path = Path::makeAbsolute($icon, $this->webDir))) {
                    $iconPath = $path;
                    break;
                }
                if (file_exists($path = Path::makeAbsolute($icon, $this->projectDir))) {
                    $iconPath = $path;
                    break;
                }
            }
        }

        foreach ($templates as $template) {
            if ($this->twig->getLoader()->exists($template)) {
                return new InsertTagResult(
                    $this->twig->render($template, [
                        'class' => $insertTag->getParameters()->get('class'),
                        'shape' => $insertTag->getParameters()->get('shape'),
                        'color' => $insertTag->getParameters()->get('color'),
                        'background' => $insertTag->getParameters()->get('background'),
                        'icon' => $iconPath ?? $icon,
                    ]),
                    OutputType::html,
                );
            }
        }

        $this->logger->error(
            \sprintf(
                'None of the required templates for the icon insert tag exists: "%s"',
                implode(', ', $templates),
            ),
        );

        return new InsertTagResult('');
    }
}
