<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Widget;

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
		$toolbar .= '<a href="" class="rsce_list_toolbar_up" onclick="rsceMoveElement(this, -1);return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_up'] . '" data-rsce-title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_up'] . '">' . \Image::getHtml('up.svg', $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_up']) . '</a> ';
		$toolbar .= '<a href="" class="rsce_list_toolbar_down" onclick="rsceMoveElement(this, 1);return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_down'] . '" data-rsce-title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_down'] . '">' . \Image::getHtml('down.svg', $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_down']) . '</a> ';
		$toolbar .= \Image::getHtml('drag.svg', sprintf($GLOBALS['TL_LANG']['MSC']['move']), 'class="drag-handle rsce_list_toolbar_drag" title="' . sprintf($GLOBALS['TL_LANG']['MSC']['move']) . '"') . ' ';
		$toolbar .= '<a href="" class="rsce_list_toolbar_delete" onclick="rsceDeleteElement(this);return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_delete'] . '" data-rsce-title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_delete'] . '">' . \Image::getHtml('delete.svg', $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_delete']) . '</a> ';
		$toolbar .= '<a href="" class="rsce_list_toolbar_new" onclick="rsceNewElementAfter(this);return false;" title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_new'] . '" data-rsce-title="' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_new'] . '">' . \Image::getHtml('new.svg', $GLOBALS['TL_LANG']['rocksolid_custom_elements']['list_item_new']) . '</a> ';
		$toolbar .= '</div>';

		return '<div class="rsce_list_item'
			. (empty($this->arrConfiguration['tl_class']) ? '' : ' ' . $this->arrConfiguration['tl_class'])
			. '" data-rsce-name="' . $fieldName . '">'
			. ($this->strLabel ? '<h2 class="rsce_list_item_title" data-rsce-label="' . $this->arrConfiguration['label_template'] . '">' . $this->strLabel . '</h2>' : '')
			. $toolbar
			. '<fieldset class="tl_box rsce_group rsce_group_no_legend">';
	}
}
