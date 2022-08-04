<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Widget;

use Contao\Widget;

/**
 * Data widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class Data extends Widget
{
	/**
	 * @var boolean Submit user input
	 */
	protected $blnSubmitInput = true;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'be_rsce_data';

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		return '<input type="hidden" name="'.$this->strName.'" value="">'
			. ($this->rsceScript ? '<script>'.$this->rsceScript.'</script>' : '');
	}
}
