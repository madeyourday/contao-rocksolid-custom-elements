<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Form;

/**
 * Custom form widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomWidgetNoInput extends CustomWidget
{
	protected $blnSubmitInput = false;

	/**
	 * {@inheritdoc}
	 */
	public function generate()
	{
		return $this->customElement->generate();
	}

	/**
	 * Do not validate
	 */
	public function validate()
	{
		return;
	}
}
