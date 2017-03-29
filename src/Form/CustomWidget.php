<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Form;

use MadeYourDay\Contao\Element\CustomElement;
use MadeYourDay\Contao\Model\DummyModel;

/**
 * Custom form widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomWidget extends \Widget
{
	/**
	 * @var string Template
	 */
	protected $strTemplate = 'form_rsce_plain';

	/**
	 * @var CustomElement
	 */
	private $customElement;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($data = null)
	{
		if (!empty($data['class'])) {
			$data['cssID'] = serialize(array('', $data['class']));
		}

		$this->customElement = new CustomElement(new DummyModel(null, $data));

		parent::__construct($data);
	}

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
