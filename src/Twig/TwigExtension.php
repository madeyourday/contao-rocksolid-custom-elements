<?php

namespace MadeYourDay\RockSolidCustomElements\Twig;

use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Contao\Image\ImageInterface;
use Contao\ImagineSvg\Imagine;
use Symfony\Component\Filesystem\Path;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TwigExtension extends AbstractExtension
{
	public function __construct(
		private readonly Imagine $imagineSvg,
		private readonly Studio $imageStudio,
		private readonly string $projectDir,
	) {
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

					if (!\in_array(strtolower(pathinfo($svg->getImage()->getFilePath(true), PATHINFO_EXTENSION)), ['svg', 'svgz'], true)) {
						return '';
					}

					try {
						$path = Path::join($this->projectDir, $svg->getImage()->getImageSrc(true));
						$dom = $this->imagineSvg->open($path)->getDomDocument();
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
