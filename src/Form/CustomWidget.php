<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Form;

use Contao\Widget;
use MadeYourDay\RockSolidCustomElements\Element\CustomElement;
use MadeYourDay\RockSolidCustomElements\Model\DummyModel;

/**
 * Custom form widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomWidget extends Widget
{
	protected $blnSubmitInput = true;

	/**
	 * @var string Template
	 */
	protected $strTemplate = 'form_rsce_plain';

	/**
	 * @var CustomElement
	 */
	protected $customElement;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($data = null)
	{
		if (!empty($data['class'])) {
			$data['cssID'] = serialize(array('', $data['class']));
		}

		$this->customElement = new CustomElement(new DummyModel(null, (array) $data));

		parent::__construct($data);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate()
	{
		$cssID = $this->customElement->cssID;
		$cssID[1] = (isset($cssID[1]) ? $cssID[1] . ' ' : '') . $this->class;
		$this->customElement->cssID = $cssID;

		$this->customElement->value = $this->value;

		foreach ([
			'hasErrors',
			'getErrors',
			'getErrorAsString',
			'getErrorsAsString',
			'getErrorAsHTML',
			'getAttributes',
			'getAttribute',
		] as $methodName) {
			$this->customElement->$methodName = function() use($methodName) {
				return call_user_func_array([$this, $methodName], func_get_args());
			};
		}

		return $this->customElement->generate();
	}

}
