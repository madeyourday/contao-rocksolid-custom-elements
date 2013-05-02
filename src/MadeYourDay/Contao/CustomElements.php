<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao;

use MadeYourDay\Contao\Template\CustomTemplate;

/**
 * RockSolid Custom Elements DCA (tl_content and tl_module)
 *
 * Provide miscellaneous methods that are used by the data configuration arrays.
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomElements extends \Backend
{
	/**
	 * @var array Currently loaded data
	 */
	protected $data = array();

	/**
	 * @var array Data prepared for saving
	 */
	protected $saveData = array();

	/**
	 * tl_content DCA onload callback
	 *
	 * Reloads config and creates the DCA fields
	 *
	 * @param  \DataContainer $dc Data container
	 * @return void
	 */
	public function onloadCallback($dc)
	{
		if (\Input::get('act') !== 'edit' && \Input::get('act') !== 'show') {
			return;
		}

		$this->reloadConfig();

		$type = $this->getDcaFieldValue($dc, 'type');
		if (!$type || substr($type, 0, 5) !== 'rsce_') {
			return;
		}

		$data = $this->getDcaFieldValue($dc, 'rsce_data', true);
		if ($data && substr($data, 0, 1) === '{') {
			$this->data = json_decode($data, true);
		}
		$this->createDca($dc, $type);
	}

	/**
	 * Field load callback
	 *
	 * Finds the current value for the field
	 *
	 * @param  string         $value Current value
	 * @param  \DataContainer $dc    Data container
	 * @return string                Current value for the field
	 */
	public function loadCallback($value, $dc)
	{
		return $this->getNestedValue($dc->field);
	}

	/**
	 * Get the value of the nested data array from field name
	 *
	 * @param  string $field Field name
	 * @return mixed         Value from $this->data
	 */
	protected function getNestedValue($field)
	{
		$field = preg_split('(__([0-9]+)__)', substr($field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		if (!isset($this->data[$field[0]])) {
			return null;
		}

		$data =& $this->data[$field[0]];

		for ($i = 0; isset($field[$i]); $i += 2) {

			if (isset($field[$i + 1])) {
				if (!isset($data[$field[$i + 1]])) {
					return null;
				}
				if (!isset($data[$field[$i + 1]][$field[$i + 2]])) {
					return null;
				}
				$data =& $data[$field[$i + 1]][$field[$i + 2]];
			}
			else {
				return $data;
			}

		}
	}

	/**
	 * Get the reference to a value of the nested data array from field name
	 *
	 * @param  string $field Field name
	 * @return mixed         Value from $this->data as reference
	 */
	protected function &getNestedValueReference($field)
	{
		$field = preg_split('(__([0-9]+)__)', substr($field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		if (!isset($this->saveData[$field[0]])) {
			$this->saveData[$field[0]] = array();
		}

		$data =& $this->saveData[$field[0]];

		for ($i = 0; isset($field[$i]); $i += 2) {

			if (isset($field[$i + 1])) {
				if (!isset($data[$field[$i + 1]])) {
					$data[$field[$i + 1]] = array();
				}
				if (!isset($data[$field[$i + 1]][$field[$i + 2]])) {
					$data[$field[$i + 1]][$field[$i + 2]] = array();
				}
				$data =& $data[$field[$i + 1]][$field[$i + 2]];
			}
			else {
				return $data;
			}

		}
	}

	/**
	 * Field save callback
	 *
	 * Saves the field data to $this->saveData
	 *
	 * @param  string         $value Field value
	 * @param  \DataContainer $dc    Data container
	 * @return void
	 */
	public function saveCallback($value, $dc)
	{
		$field = preg_split('(__([0-9]+)__)', substr($dc->field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		$data =& $this->saveData[$field[0]];

		for ($i = 0; isset($field[$i]); $i += 2) {

			if (isset($field[$i + 1])) {
				if (!isset($data[$field[$i + 1]])) {
					$data[$field[$i + 1]] = array();
				}
				if (!isset($data[$field[$i + 1]][$field[$i + 2]])) {
					$data[$field[$i + 1]][$field[$i + 2]] = array();
				}
				$data =& $data[$field[$i + 1]][$field[$i + 2]];
			}
			else {
				$data = $value;
			}
		}
	}

	/**
	 * rsce_data field save callback
	 *
	 * Returns the JSON encoded $this->saveData
	 *
	 * @param  string         $value Current field value
	 * @param  \DataContainer $dc    Data container
	 * @return string                JSON encoded $this->saveData
	 */
	public function saveDataCallback($value, $dc)
	{
		if ($key = \Input::post('rsce_new_list_item')) {
			$data = &$this->getNestedValueReference($key);
			array_unshift($data, array());
		}

		if ($key = \Input::post('rsce_insert_list_item')) {
			$key = explode('__', $key);
			$index = array_pop($key) * 1;
			$key = implode('__', $key);
			$data = &$this->getNestedValueReference($key);
			array_splice($data, $index + 1, 0, array(array()));
		}

		if ($key = \Input::post('rsce_delete_list_item')) {
			$key = explode('__', $key);
			$index = array_pop($key) * 1;
			$key = implode('__', $key);
			$data = &$this->getNestedValueReference($key);
			array_splice($data, $index, 1);
		}

		if ($key = \Input::post('rsce_move_list_item')) {
			$key = explode(',', $key);
			$newIndex = (int) $key[1];
			$key = explode('__', $key[0]);
			$index = array_pop($key) * 1;
			$key = implode('__', $key);
			$data = &$this->getNestedValueReference($key);
			$item = $data[$index];
			// remove the item
			array_splice($data, $index, 1);
			// inject the item to the new position
			array_splice($data, $newIndex < 0 ? 0 : $newIndex, 0, array($item));
		}

		return json_encode($this->saveData);
	}

	/**
	 * Create all DCA fields for the specified type
	 *
	 * @param  \DataContainer $dc   Data container
	 * @param  string         $type The template name
	 * @return void
	 */
	protected function createDca($dc, $type)
	{
		try {
			$templatePaths = CustomTemplate::getTemplates($type);
			if (empty($templatePaths[0])) {
				return;
			}
			$configPath = substr($templatePaths[0], 0, -6) . '_config.php';
			if (!file_exists($configPath)) {
				return;
			}
		}
		catch (\Exception $e) {
			return;
		}

		$paletteFields = array();

		if (count($templatePaths) > 1) {
			$GLOBALS['TL_DCA'][$dc->table]['fields']['rsce_multiple_templates_warning'] = array(
				'label' => array('', ''),
				'input_field_callback' => array('MadeYourDay\\Contao\\CustomElements', 'fieldMultipleTemplatesWarning'),
			);
			$paletteFields[] = 'rsce_multiple_templates_warning';
		}

		$config = include $configPath;

		foreach ($config['fields'] as $fieldName => $fieldConfig) {
			$this->createDcaItem('rsce_field_', $fieldName, $fieldConfig, $paletteFields, $dc);
		}

		$paletteFields[] = 'rsce_data';

		if ($dc->table === 'tl_module') {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] = '{title_legend},name,type;{rsce_legend},';
		}
		else {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] = '{type_legend},type;{rsce_legend},';
		}
		$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= implode(',', $paletteFields);
		$GLOBALS['TL_LANG'][$dc->table]['rsce_legend'] = $GLOBALS['TL_LANG'][$dc->table === 'tl_content' ? 'CTE' : 'FMD'][$type][0];
	}

	/**
	 * Create one DCA field with the specified parameters
	 *
	 * This function calls itself recursively for nested data structures
	 *
	 * @param  string         $fieldPrefix   Field prefix, e.g. "rsce_field_"
	 * @param  string         $fieldName     Field name
	 * @param  array          $fieldConfig   Field configuration array
	 * @param  array          $paletteFields Reference to the list of all fields
	 * @param  \DataContainer $dc            Data container
	 * @return void
	 */
	protected function createDcaItem($fieldPrefix, $fieldName, $fieldConfig, &$paletteFields, $dc)
	{
		if ($fieldConfig['inputType'] === 'list') {

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '_rsce_list_start'] = array(
				'label' => $fieldConfig['label'],
				'inputType' => 'rsce_list_start',
			);
			$paletteFields[] = $fieldPrefix . $fieldName . '_rsce_list_start';

			$fieldData = $this->getNestedValue($fieldPrefix . $fieldName);

			if (is_array($fieldData)) {

				foreach ($fieldData as $dataKey => $dataValue) {

					$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_start'] = array(
						'inputType' => 'rsce_list_item_start',
						'label' => array(sprintf($fieldConfig['elementLabel'], $dataKey + 1)),
					);
					$paletteFields[] = $fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_start';

					foreach ($fieldConfig['fields'] as $fieldName2 => $fieldConfig2) {
						$this->createDcaItem($fieldPrefix . $fieldName . '__' . $dataKey . '__', $fieldName2, $fieldConfig2, $paletteFields, $dc);
					}

					$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . $dataKey . '_rsce_list_item_stop'] = array(
						'inputType' => 'rsce_list_item_stop',
					);
					$paletteFields[] = $fieldPrefix . $fieldName . $dataKey . '_rsce_list_item_stop';

				}

			}

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '_rsce_list_stop'] = array(
				'inputType' => 'rsce_list_stop',
			);
			$paletteFields[] = $fieldPrefix . $fieldName . '_rsce_list_stop';

		}
		else {

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName] = $fieldConfig;
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['eval']['alwaysSave'] = true;
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['eval']['doNotSaveEmpty'] = true;
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['load_callback'][] =
				array('MadeYourDay\\Contao\\CustomElements', 'loadCallback');
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['save_callback'][] =
				array('MadeYourDay\\Contao\\CustomElements', 'saveCallback');
			$paletteFields[] = $fieldPrefix . $fieldName;

		}
	}

	/**
	 * Get the value of a field (from POST data, active record or the database)
	 *
	 * @param  \DataContainer $dc        Data container
	 * @param  string         $fieldName Field name
	 * @param  boolean        $fromDb    True to ignore POST data
	 * @return string                    The value
	 */
	protected static function getDcaFieldValue($dc, $fieldName, $fromDb = false)
	{
		$value = null;

		if (\Input::getInstance()->post('FORM_SUBMIT') === $dc->table && !$fromDb) {
			$value = \Input::getInstance()->post($fieldName);
		}
		elseif ($dc->activeRecord) {
			$value = $dc->activeRecord->$fieldName;
		}
		else {
			$record = \Database::getInstance()
				->prepare("SELECT * FROM {$dc->table} WHERE id=?")
				->execute($dc->id);
			if ($record->next()) {
				$value = $record->$fieldName;
			}
		}

		return $value;
	}

	/**
	 * Callback for displaying the multiple templates warning
	 *
	 * @param  \DataContainer $dc Data container
	 * @return string             Warning HTML code
	 */
	public function fieldMultipleTemplatesWarning($dc)
	{
		$this->loadLanguageFile('rocksolid_custom_elements');
		return '<p class="tl_gerror"><strong>'
			. $GLOBALS['TL_LANG']['rocksolid_custom_elements']['multiple_templates_warning'][0] . ':</strong> '
			. sprintf($GLOBALS['TL_LANG']['rocksolid_custom_elements']['multiple_templates_warning'][1], $this->getDcaFieldValue($dc, 'type')) . '</p>';
	}

	/**
	 * Load the TL_CTE and FE_MOD configuration and use caching if possible
	 *
	 * @return void
	 */
	public static function loadConfig()
	{
		$filePath = 'system/cache/rocksolid_custom_elements_config.php';
		$fileFullPath = TL_ROOT . '/' . $filePath;

		if (! file_exists($fileFullPath)) {

			$contents = array();
			$contents[] = '<?php';

			$templates = \Controller::getTemplateGroup('rsce_');
			foreach ($templates as $template => $label) {

				if (substr($template, -7) === '_config') {
					continue;
				}

				$templatePaths = CustomTemplate::getTemplates($template);
				$configPath = substr($templatePaths[0], 0, -6) . '_config.php';
				if (!file_exists($configPath)) {
					continue;
				}
				$config = include $configPath;

				$label = isset($config['label']) ? $config['label'] : array(implode(' ', array_map('ucfirst', explode('_', substr($template, 5)))), '');
				$types = isset($config['types']) ? $config['types'] : array('content', 'module');
				$contentCategory = isset($config['contentCategory']) ? $config['contentCategory'] : 'texts';
				$moduleCategory = isset($config['moduleCategory']) ? $config['moduleCategory'] : 'miscellaneous';

				if (in_array('content', $types)) {
					$contents[] = '$GLOBALS[\'TL_CTE\'][\'' . $contentCategory . '\'][\'' . $template . '\'] = \'MadeYourDay\\\\Contao\\\\Element\\\\CustomElement\';';
					$contents[] = '$GLOBALS[\'TL_LANG\'][\'CTE\'][\'' . $template . '\'] = ' . var_export($label, true) . ';';
				}
				if (in_array('module', $types)) {
					$contents[] = '$GLOBALS[\'FE_MOD\'][\'' . $moduleCategory . '\'][\'' . $template . '\'] = \'MadeYourDay\\\\Contao\\\\Element\\\\CustomElement\';';
					$contents[] = '$GLOBALS[\'TL_LANG\'][\'FMD\'][\'' . $template . '\'] = ' . var_export($label, true) . ';';
				}

			}

			$file = new \File($filePath, true);
			$file->write(implode("\n", $contents));
			$file->close();

		}

		if (file_exists($fileFullPath)) {
			include $fileFullPath;
		}
		else {
			// was not able to create the cache file
			eval('?>' . implode("\n", $contents));
		}

	}

	/**
	 * Delete the config cache and call loadConfig
	 *
	 * @return void
	 */
	public static function reloadConfig()
	{
		$filePath = TL_ROOT . '/system/cache/rocksolid_custom_elements_config.php';

		if (file_exists($filePath)) {
			unlink($filePath);
		}

		return static::loadConfig();
	}
}
