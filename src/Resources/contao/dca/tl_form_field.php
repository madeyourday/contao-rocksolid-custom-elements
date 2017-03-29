<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Custom Elements DCA
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */

$GLOBALS['TL_DCA']['tl_form_field']['config']['onload_callback'][] = array('MadeYourDay\Contao\CustomElements', 'onloadCallback');
$GLOBALS['TL_DCA']['tl_form_field']['config']['onsubmit_callback'][] = array('MadeYourDay\Contao\CustomElements', 'onsubmitCallback');
$GLOBALS['TL_DCA']['tl_form_field']['fields']['rsce_data'] = array(
	'label' => &$GLOBALS['TL_LANG']['tl_form_field']['rsce_data'],
	'exclude' => true,
	'inputType' => 'rsce_list_hidden',
	'sql' => "mediumblob NULL",
	'save_callback' => array(
		array('MadeYourDay\\Contao\\CustomElements', 'saveDataCallback'),
	),
);
