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

$GLOBALS['BE_FFL']['rsce_list_start'] = 'MadeYourDay\\Contao\\Widget\\ListStart';
$GLOBALS['BE_FFL']['rsce_list_stop'] = 'MadeYourDay\\Contao\\Widget\\ListStop';
$GLOBALS['BE_FFL']['rsce_list_item_start'] = 'MadeYourDay\\Contao\\Widget\\ListItemStart';
$GLOBALS['BE_FFL']['rsce_list_item_stop'] = 'MadeYourDay\\Contao\\Widget\\ListItemStop';
$GLOBALS['BE_FFL']['rsce_list_hidden'] = 'MadeYourDay\\Contao\\Widget\\Hidden';
$GLOBALS['BE_FFL']['rsce_file_tree'] = 'MadeYourDay\\Contao\\Widget\\FileTree';
$GLOBALS['BE_FFL']['rsce_page_tree'] = 'MadeYourDay\\Contao\\Widget\\PageTree';

$GLOBALS['TL_MAINTENANCE'][] = 'MadeYourDay\\Contao\\CustomElementsConvert';

$GLOBALS['TL_HOOKS']['executePostActions'][] = array('MadeYourDay\\Contao\\CustomElementsAjax', 'executePostActionsHook');

$GLOBALS['TL_PURGE']['custom']['rocksolid_custom_elements'] = array(
	'callback' => array('MadeYourDay\\Contao\\CustomElements', 'purgeCache'),
);

// Insert the custom_elements category
array_insert($GLOBALS['TL_CTE'], 1, array('custom_elements' => array()));
array_insert($GLOBALS['FE_MOD'], 0, array('custom_elements' => array()));

// load FE_MOD and TL_CTE config from cache if possible
MadeYourDay\Contao\CustomElements::loadConfig();
