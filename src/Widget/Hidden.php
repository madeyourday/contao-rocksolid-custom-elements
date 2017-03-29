<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Widget;

/**
 * Hidden widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class Hidden extends \Widget
{
	/**
	 * @var boolean Submit user input
	 */
	protected $blnSubmitInput = true;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'be_rsce_hidden';

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		return '';
	}
}
