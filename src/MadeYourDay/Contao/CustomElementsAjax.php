<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao;

/**
 * RockSolid Custom Elements Ajax class
 *
 * Provide miscellaneous methods to handle Ajax requests.
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomElementsAjax extends \Backend
{
	/**
	 * Execute post actions hook for page and file tree widgets
	 *
	 * @param  string         $action ajax action name
	 * @param  \DataContainer $dc     data container instance
	 * @return void
	 */
	public function executePostActionsHook($action, $dc)
	{
		if (
			$action !== 'rsceReloadPagetree' &&
			$action !== 'rsceReloadFiletree'
		) {
			return;
		}

		$value = \Input::post('value', true);
		$widgetKey = ($action === 'rsceReloadPagetree') ?
			'rsce_page_tree' :
			'rsce_file_tree';

		// Convert the selected values
		if ($value) {
			$value = trimsplit("\t", $value);
			// Automatically add resources to the DBAFS
			if ($widgetKey == 'rsce_file_tree') {
				foreach ($value as $k => $v) {
					if (version_compare(VERSION, '3.2', '<')) {
						$value[$k] = \Dbafs::addResource($v)->id;
					}
					else {
						$value[$k] = \Dbafs::addResource($v)->uuid;
					}
				}
			}
			$value = serialize($value);
		}

		$widget = new $GLOBALS['BE_FFL'][$widgetKey](array(
			'activeRecord' => null,
			'id' => \Input::post('name'),
			'name' => \Input::post('name'),
			'value' => $value,
			'strTable' => $dc->table,
			'strField' => \Input::post('name'),
		));

		echo $widget->generate();
		exit;
	}
}
