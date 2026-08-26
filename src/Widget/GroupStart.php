<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Widget;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\System;
use Contao\Widget;

/**
 * Group start widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class GroupStart extends Widget
{
	/**
	 * @var boolean Submit user input
	 */
	protected $blnSubmitInput = false;

	/**
	 * @var boolean Widget group wrapper
	 */
	protected $widgetGroup = false;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'be_rsce_group';

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
		$this->loadLanguageFile('rocksolid_custom_elements');

		$classes = [$this->arrConfiguration['tl_class'] ?? '', 'tl_box', 'rsce_group'];
		$fs = System::getContainer()->get('request_stack')->getSession()->getBag('contao_backend')->get('fieldset_states');

		if (
			(isset($fs[$this->strTable][$this->strId]) && !$fs[$this->strTable][$this->strId])
			|| (!isset($fs[$this->strTable][$this->strId]) && !empty($this->arrConfiguration['collapsed']))
		) {
			$classes[] = 'collapsed';
		}

		if (version_compare(ContaoCoreBundle::getVersion(), '5.3', '>=')) {
			return '</fieldset>'
				. '<div class="clear"></div>'
				. '<fieldset'
				. ' id="pal_' . $this->strId . '"'
				. ' class="' . implode(' ', $classes) . '"'
				. ' data-controller="contao--toggle-fieldset" data-contao--toggle-fieldset-id-value="' . $this->strId . '"'
				. ' data-contao--toggle-fieldset-table-value="' . $this->strTable . '"'
				. ' data-contao--toggle-fieldset-collapsed-class="collapsed"'
				. ' data-contao--jump-targets-target="section"'
				. ' data-contao--jump-targets-label-value="' . $this->strLabel . '"'
				. ' data-action="contao--jump-targets:scrollto->contao--toggle-fieldset#open"'
				. '>'
				. '<legend>'
				. '<button'
				. ' type="button"'
				. ' data-action="click->contao--toggle-fieldset#toggle"'
				. '>' . $this->strLabel
				. '</button>'
				. '</legend>'
				. ($this->description ? '<p class="rsce_group_description">' . $this->description . '</p>' : '');
		} else {
			return '</fieldset>'
				. '<div class="clear"></div>'
				. '<fieldset'
				. ' id="pal_' . $this->strId . '"'
				. ' class="' . implode(' ', $classes) . '"'
				. '>'
				. '<legend'
				. ' onclick="AjaxRequest.toggleFieldset(this, &quot;' . $this->strId . '&quot;, &quot;' . $this->strTable . '&quot;)"'
				. '>' . $this->strLabel
				. '</legend>'
				. ($this->description ? '<p class="rsce_group_description">' . $this->description . '</p>' : '');
		}
	}
}
