<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements;

use MadeYourDay\RockSolidCustomElements\DependencyInjection\RockSolidCustomElementsExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the RockSolid Custom Elements bundle.
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class RockSolidCustomElementsBundle extends Bundle
{
	/**
	 * {@inheritdoc}
	 */
	public function getContainerExtension()
	{
		return new RockSolidCustomElementsExtension();
	}
}
