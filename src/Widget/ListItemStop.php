<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Widget;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\Widget;

/**
 * List item stop widget
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class ListItemStop extends Widget
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
	protected $strTemplate = 'be_rsce_list';

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
		return '</fieldset></'.(($this->arrAttributes['disabled'] ?? false) ? 'fieldset' : 'div').'>';
	}
}
