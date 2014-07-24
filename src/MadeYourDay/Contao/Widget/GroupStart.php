<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Widget;

/**
 * Group start widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class GroupStart extends \Widget
{
	/**
	 * @var boolean Submit user input
	 */
	protected $blnSubmitInput = false;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'be_rsce_group';

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		$this->loadLanguageFile('rocksolid_custom_elements');

		$fs = $this->Session->get('fieldset_states');

		return '</fieldset>'
			. '<div class="clear"></div>'
			. '<fieldset'
			. ' id="pal_' . $this->strId . '"'
			. ' class="tl_box rsce_group' . ((!isset($fs[$this->strTable][$this->strId]) || $fs[$this->strTable][$this->strId]) ? '' : ' collapsed') . '"'
			. '>'
			. '<legend'
			. ' onclick="AjaxRequest.toggleFieldset(this, &quot;' . $this->strId . '&quot;, &quot;' . $this->strTable . '&quot;)"'
			. '>' . $this->strLabel
			. '</legend>'
			. ($this->description ? '<p class="rsce_group_description">' . $this->description . '</p>' : '');
	}
}
