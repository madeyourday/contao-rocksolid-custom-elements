<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Custom Elements configuration
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */

$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('MadeYourDay\\RockSolidCustomElements\\CustomElements', 'loadConfig');
$GLOBALS['TL_HOOKS']['loadDataContainer'][] = array('MadeYourDay\\RockSolidCustomElements\\CustomElements', 'loadDataContainerHook');
$GLOBALS['TL_HOOKS']['loadLanguageFile'][] = array('MadeYourDay\\RockSolidCustomElements\\CustomElements', 'loadLanguageFileHook');
$GLOBALS['TL_HOOKS']['exportTheme'][] = array('MadeYourDay\\RockSolidCustomElements\\CustomElements', 'exportThemeHook');
$GLOBALS['TL_HOOKS']['extractThemeFiles'][] = array('MadeYourDay\\RockSolidCustomElements\\CustomElements', 'extractThemeFilesHook');

$GLOBALS['BE_FFL']['rsce_list_start'] = 'MadeYourDay\\RockSolidCustomElements\\Widget\\ListStart';
$GLOBALS['BE_FFL']['rsce_list_stop'] = 'MadeYourDay\\RockSolidCustomElements\\Widget\\ListStop';
$GLOBALS['BE_FFL']['rsce_list_item_start'] = 'MadeYourDay\\RockSolidCustomElements\\Widget\\ListItemStart';
$GLOBALS['BE_FFL']['rsce_list_item_stop'] = 'MadeYourDay\\RockSolidCustomElements\\Widget\\ListItemStop';
$GLOBALS['BE_FFL']['rsce_group_start'] = 'MadeYourDay\\RockSolidCustomElements\\Widget\\GroupStart';
$GLOBALS['BE_FFL']['rsce_list_hidden'] = 'MadeYourDay\\RockSolidCustomElements\\Widget\\Hidden';

$GLOBALS['TL_MAINTENANCE'][] = 'MadeYourDay\\RockSolidCustomElements\\CustomElementsConvert';

$GLOBALS['TL_PURGE']['custom']['rocksolid_custom_elements'] = array(
	'callback' => array('MadeYourDay\\RockSolidCustomElements\\CustomElements', 'purgeCache'),
);

// Insert the custom_elements category
array_insert($GLOBALS['TL_CTE'], 1, array('custom_elements' => array()));
array_insert($GLOBALS['FE_MOD'], 0, array('custom_elements' => array()));
