<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Widget;

/**
 * List item start widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class ListItemStart extends \Widget
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
		$fieldName = substr($this->strId, 0, -21);
		$fieldIndex = explode('__', $fieldName);
		$fieldIndex = (int) $fieldIndex[count($fieldIndex) - 1];

		$toolbar = '<div class="rsce_list_toolbar">';
		$toolbar .= '<a href="" onclick="Backend.getScrollOffset();new Element(\'input\', {type: \'hidden\', name: \'rsce_move_list_item\', value: \'' . $fieldName . ',' . ($fieldIndex - 1) . '\'}).inject($(\'' . $this->strTable . '\'));$(\'' . $this->strTable . '\').submit();return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_up'] . '"><img width="13" height="16" alt="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_up'] . '" src="system/themes/default/images/up.gif"></a> ';
		$toolbar .= '<a href="" onclick="Backend.getScrollOffset();new Element(\'input\', {type: \'hidden\', name: \'rsce_move_list_item\', value: \'' . $fieldName . ',' . ($fieldIndex + 1) . '\'}).inject($(\'' . $this->strTable . '\'));$(\'' . $this->strTable . '\').submit();return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_down'] . '"><img width="13" height="16" alt="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_down'] . '" src="system/themes/default/images/down.gif"></a> ';
		$toolbar .= '<a href="" onclick="Backend.getScrollOffset();new Element(\'input\', {type: \'hidden\', name: \'rsce_delete_list_item\', value: \'' . $fieldName . '\'}).inject($(\'' . $this->strTable . '\'));$(\'' . $this->strTable . '\').submit();return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_delete'] . '"><img width="14" height="16" alt="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_delete'] . '" src="system/themes/default/images/delete.gif"></a> ';
		$toolbar .= '<a href="" onclick="Backend.getScrollOffset();new Element(\'input\', {type: \'hidden\', name: \'rsce_insert_list_item\', value: \'' . $fieldName . '\'}).inject($(\'' . $this->strTable . '\'));$(\'' . $this->strTable . '\').submit();return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_new'] . '"><img width="12" height="16" alt="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_new'] . '" src="system/themes/default/images/new.gif"></a> ';
		$toolbar .= '</div>';

		return '<div class="rsce_list_item">'
			. ($this->strLabel ? '<div class="rsce_list_item_title">' . $this->strLabel . '</div>' : '')
			. $toolbar;
	}
}
