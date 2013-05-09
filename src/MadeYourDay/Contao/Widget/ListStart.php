<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Widget;

/**
 * List start widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class ListStart extends \Widget
{
	/**
	 * @var boolean Submit user input
	 */
	protected $blnSubmitInput = false;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'be_rsce_list';

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		$this->loadLanguageFile('rocksolid_custom_elements');

		$toolbar = '<div class="rsce_list_toolbar">';
		$toolbar .= '<a class="header_new" href="" onclick="Backend.getScrollOffset();new Element(\'input\', {type: \'hidden\', name: \'rsce_new_list_item\', value: \'' . substr($this->strId, 0, -16) . '\'}).inject($(\'' . $this->strTable . '\'));$(\'' . $this->strTable . '\').submit();return false;">' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['new_list_item'] . '</a> ';
		$toolbar .= '</div>';


		$fs = $this->Session->get('fieldset_states');
		return '<fieldset'
			. ' id="pal_' . $this->strId . '"'
			. ' class="tl_box rsce_list' . ($fs[$this->strTable][$this->strId] ? '' : ' collapsed') . '"'
			. '>'
			. '<legend'
			. ' onclick="AjaxRequest.toggleFieldset(this, &quot;' . $this->strId . '&quot;, &quot;' . $this->strTable . '&quot;)"'
			. '>' . $this->strLabel
			. '</legend>'
			. $toolbar
			. ($this->description ? '<p class="rsce_list_description">' . $this->description . '</p>' : '');
	}
}
