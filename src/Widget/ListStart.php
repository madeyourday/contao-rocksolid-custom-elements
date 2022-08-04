<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Widget;

use Contao\System;
use Contao\Widget;

/**
 * List start widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class ListStart extends Widget
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
		$toolbar .= '<a class="header_new" href="" onclick="rsceNewElement(this);return false;">' . $GLOBALS['TL_LANG']['rocksolid_custom_elements']['new_list_item'] . '</a> ';
		$toolbar .= '</div>';

		$classes = [$this->arrConfiguration['tl_class'] ?? '', 'tl_box', 'rsce_list'];
		$fs = System::getContainer()->get('session')->getBag('contao_backend')->get('fieldset_states');

		if (
			(isset($fs[$this->strTable][$this->strId]) && !$fs[$this->strTable][$this->strId])
			|| (!isset($fs[$this->strTable][$this->strId]) && !empty($this->arrConfiguration['collapsed']))
		) {
			$classes[] = 'collapsed';
		}

		$config = array(
			'minItems' => $this->minItems,
			'maxItems' => $this->maxItems,
		);

		return '</fieldset>'
			. '<div class="clear"></div>'
			. '<fieldset'
			. ' id="pal_' . $this->strId . '"'
			. ' class="' . implode(' ', $classes) . '"'
			. ' data-config="' . htmlspecialchars(json_encode($config), ENT_QUOTES) . '"'
			. $this->getAttributes()
			. '>'
			. '<legend'
			. ' onclick="AjaxRequest.toggleFieldset(this, &quot;' . $this->strId . '&quot;, &quot;' . $this->strTable . '&quot;)"'
			. '>' . $this->strLabel
			. '</legend>'
			. $toolbar
			. ($this->description ? '<p class="rsce_list_description">' . $this->description . '</p>' : '');
	}
}
