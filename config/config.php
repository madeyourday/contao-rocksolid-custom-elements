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

// Backwards compatibility for Contao < 3.5.1
if (!class_exists('StringUtil') && class_exists('String')) {
	class_alias('String', 'StringUtil');
}

$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('MadeYourDay\\Contao\\CustomElements', 'loadConfig');
$GLOBALS['TL_HOOKS']['loadLanguageFile'][] = array('MadeYourDay\\Contao\\CustomElements', 'loadLanguageFileHook');
$GLOBALS['TL_HOOKS']['exportTheme'][] = array('MadeYourDay\\Contao\\CustomElements', 'exportThemeHook');
$GLOBALS['TL_HOOKS']['extractThemeFiles'][] = array('MadeYourDay\\Contao\\CustomElements', 'extractThemeFilesHook');

$GLOBALS['BE_FFL']['rsce_list_start'] = 'MadeYourDay\\Contao\\Widget\\ListStart';
$GLOBALS['BE_FFL']['rsce_list_stop'] = 'MadeYourDay\\Contao\\Widget\\ListStop';
$GLOBALS['BE_FFL']['rsce_list_item_start'] = 'MadeYourDay\\Contao\\Widget\\ListItemStart';
$GLOBALS['BE_FFL']['rsce_list_item_stop'] = 'MadeYourDay\\Contao\\Widget\\ListItemStop';
$GLOBALS['BE_FFL']['rsce_group_start'] = 'MadeYourDay\\Contao\\Widget\\GroupStart';
$GLOBALS['BE_FFL']['rsce_list_hidden'] = 'MadeYourDay\\Contao\\Widget\\Hidden';

$GLOBALS['TL_MAINTENANCE'][] = 'MadeYourDay\\Contao\\CustomElementsConvert';

$GLOBALS['TL_PURGE']['custom']['rocksolid_custom_elements'] = array(
	'callback' => array('MadeYourDay\\Contao\\CustomElements', 'purgeCache'),
);

// Insert the custom_elements category
array_insert($GLOBALS['TL_CTE'], 1, array('custom_elements' => array()));
array_insert($GLOBALS['FE_MOD'], 0, array('custom_elements' => array()));
