<?php

namespace MadeYourDay\RockSolidCustomElements\Twig;

use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Contao\Image\ImageInterface;
use Contao\ImagineSvg\Imagine;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigExtension extends AbstractExtension
{
	public function __construct(
		private readonly Imagine $imagineSvg,
		private readonly Studio $imageStudio,
	) {
	}

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

	public function getFunctions(): array
	{
		return [
			new TwigFunction(
				'inline_svg',
				function (Figure|FilesModel|ImageInterface|int|string|null $svg): string {
					if (!$svg instanceof Figure) {
						$svg = $this->imageStudio
							->createFigureBuilder()
							->from($svg)
							->buildIfResourceExists();
					}

					if (!$svg) {
						return '';
					}

					try {
						$dom = $this->imagineSvg->open($svg->getImage()->getFilePath(true))->getDomDocument();
						return $dom->saveXML($dom->documentElement);
					} catch (\Throwable $e) {
					}

					return '';
				},
				['is_safe' => ['html']],
			),
		];
	}
}
