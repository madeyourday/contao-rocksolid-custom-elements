<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Model;

/**
 * Dummy model
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class DummyModel extends \Model
{
	/**
	 * {@inheritdoc}
	 */
	public function __construct(\Database\Result $objResult = null, $data = array())
	{
		$this->arrModified = array();
		$this->setRow($data);
	}
}
