<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Widget;

/**
 * Page tree widget without database connection
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class PageTree extends \PageTree
{
	/**
	 * construtor
	 *
	 * @param array $attributes
	 */
	public function __construct($attributes = null)
	{
		parent::__construct($attributes);

		if ($this->strOrderField) {
			throw new \Exception('"orderField" is not supported for widget rsce_page_tree');
		}
	}

	/**
	 * generate widget
	 *
	 * @return string generated html code
	 */
	public function generate()
	{
		return str_replace(
			'Backend.openModalSelector',
			'RsceBackend.openModalSelector',
			parent::generate()
		);
	}
}
