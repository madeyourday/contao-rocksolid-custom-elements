<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Widget;

use Contao\CoreBundle\ContaoCoreBundle;
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
	 * @var boolean Widget group wrapper
	 */
	protected $widgetGroup = false;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'be_rsce_data';

	public function __construct($arrAttributes = null)
	{
		parent::__construct($arrAttributes);

		$this->widgetGroup = version_compare(ContaoCoreBundle::getVersion(), '5.4', '>=');
	}

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		return '</fieldset><fieldset class="tl_box" style="border: 0; margin: 0; padding: 0">'
			. '<input type="hidden" name="'.$this->strName.'" value="">'
			. ($this->rsceScript ? '<script>'.$this->rsceScript.'</script>' : '');
	}
}
