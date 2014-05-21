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
 * @todo   Create cache files with different names to be able to drop the
 *         refreshOpcodeCache method
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
	 * @var array Fields configuration
	 */
	protected $fieldsConfig = array();

	/**
	 * tl_content and tl_module DCA onload callback
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

		$createFromPost = \Input::getInstance()->post('FORM_SUBMIT') === $dc->table;

		if ((
			\Environment::get('script') === 'contao/file.php' ||
			\Environment::get('script') === 'contao/page.php'
		) && \Input::get('field')) {
			// Ensures that the fileTree oder pageTree field exists
			$this->createDca($dc, $type, $createFromPost, \Input::get('field'));
		}
		else if (
			\Environment::get('script') === 'contao/main.php'
			&& (
				\Input::post('action') === 'reloadFiletree'
				|| \Input::post('action') === 'reloadPagetree'
			)
			&& \Input::post('name')
		) {
			// Ensures that the fileTree oder pageTree field exists
			$this->createDca($dc, $type, $createFromPost, \Input::post('name'));
		}
		else {
			$this->createDca($dc, $type, $createFromPost);
		}
	}

	/**
	 * tl_content and tl_module DCA onsubmit callback
	 *
	 * Creates empty arrays for empty lists if no data is available
	 * (e.g. for new elements)
	 *
	 * @param  \DataContainer $dc Data container
	 * @return void
	 */
	public function onsubmitCallback($dc)
	{
		$type = $this->getDcaFieldValue($dc, 'type');
		if (!$type || substr($type, 0, 5) !== 'rsce_') {
			return;
		}

		$data = $this->getDcaFieldValue($dc, 'rsce_data', true);

		// Check if it is a new element with no data
		if ($data === null && !count($this->saveData)) {

			// Creates empty arrays for empty lists, see #4
			$data = $this->saveDataCallback(null, $dc);

			if ($data && substr($data, 0, 1) === '{') {
				\Database::getInstance()
					->prepare("UPDATE {$dc->table} SET rsce_data = ? WHERE id = ?")
					->execute($data, $dc->id);
			}

		}
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
		$value = $this->getNestedValue($dc->field);

		if (
			version_compare(VERSION, '3.2', '>=') &&
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fileTree' &&
			$value
		) {
			// Multiple files
			if (substr($value, 0, 2) === 'a:') {
				$value = serialize(array_map(function($value) {
					if (strlen($value) === 36) {
						$value = \String::uuidToBin($value);
					}
					else if (is_numeric($value) && $file = \FilesModel::findByPk($value)) {
						// Convert 3.1 format into 3.2 format
						$value = $file->uuid;
					}
					return $value;
				}, deserialize($value)));
			}
			// Single file
			else {
				if (strlen($value) === 36) {
					$value = \String::uuidToBin($value);
				}
				else if (is_numeric($value) && $file = \FilesModel::findByPk($value)) {
					// Convert 3.1 format into 3.2 format
					$value = $file->uuid;
				}
			}
		}

		return $value;
	}

	/**
	 * Get the value of the nested data array $this->data from field name
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
	 * Get the reference to a value of the nested data array $this->saveData from field name
	 *
	 * @param  string $field Field name
	 * @return mixed         Value from $this->saveData as reference
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
	 * Get the config from field name
	 *
	 * @param  string $field Field name
	 * @return mixed         Configuration of the field
	 */
	protected function getNestedConfig($field, $config)
	{
		$field = preg_split('(__([0-9]+)__)', substr($field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		if (!isset($config[$field[0]])) {
			return null;
		}

		$fieldConfig =& $config[$field[0]];

		for ($i = 0; isset($field[$i]); $i += 2) {

			if (isset($field[$i + 1])) {
				if (!isset($fieldConfig['fields'])) {
					return null;
				}
				if (!isset($fieldConfig['fields'][$field[$i + 2]])) {
					return null;
				}
				$fieldConfig =& $fieldConfig['fields'][$field[$i + 2]];
			}
			else {
				return $fieldConfig;
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
		if (strpos($dc->field, '__rsce_dummy__') !== false) {
			return;
		}

		if (
			version_compare(VERSION, '3.2', '>=') &&
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fileTree'
		) {
			if (trim($value)) {
				if (strlen($value) === 16) {
					$value = \String::binToUuid($value);
				}
				else {
					$value = serialize(array_map('String::binToUuid', deserialize($value)));
				}
			}
			else {
				$value = '';
			}
		}

		$field = preg_split('(__([0-9]+)__)', substr($dc->field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		$data =& $this->saveData[$field[0]];

		for ($i = 0; isset($field[$i]); $i += 2) {

			if (isset($field[$i + 1])) {
				if (!isset($data[$field[$i + 1]])) {
					$data[$field[$i + 1]] = array();
				}
				if (!isset($data[$field[$i + 1]][$field[$i + 2]])) {
					if ($field[$i + 2] === 'rsce_empty' && !isset($field[$i + 3]) && !$value) {
						// do not save the empty field
						break;
					}
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
		$this->prepareSaveData('rsce_field_', $this->fieldsConfig);

		return json_encode($this->saveData);
	}

	/**
	 * prepare the data to save and create empty arrays for empty lists
	 *
	 * @param  string $fieldPrefix  field prefix
	 * @param  array  $fieldsConfig fields configuration
	 * @return void
	 */
	protected function prepareSaveData($fieldPrefix, $fieldsConfig)
	{
		foreach ($fieldsConfig as $fieldName => $fieldConfig) {

			if ($fieldConfig['inputType'] === 'list') {

				// creates an empty array for a empty lists
				$fieldData = $this->getNestedValueReference($fieldPrefix . $fieldName);

				for ($dataKey = 0; isset($fieldData[$dataKey]); $dataKey++) {
					$this->prepareSaveData($fieldPrefix . $fieldName . '__' . $dataKey . '__', $fieldConfig['fields']);
				}

			}

		}
	}

	/**
	 * Create all DCA fields for the specified type
	 *
	 * @param  \DataContainer $dc             Data container
	 * @param  string         $type           The template name
	 * @param  boolean        $createFromPost Whether to create the field structure from post data or not
	 * @param  string         $tmpField       Field name to create temporarily for page or file tree widget ajax calls
	 * @return void
	 */
	protected function createDca($dc, $type, $createFromPost = false, $tmpField = null)
	{
		$configPath = null;

		try {
			$templatePaths = CustomTemplate::getTemplates($type);
			if (!empty($templatePaths[0])) {
				$configPath = substr($templatePaths[0], 0, -6) . '_config.php';
			}
		}
		catch (\Exception $e) {
			$configPath = null;
		}

		if ($configPath === null || !file_exists($configPath)) {
			$allConfigs = array_merge(
				glob(TL_ROOT . '/templates/' . $type . '_config.php') ?: array(),
				glob(TL_ROOT . '/templates/*/' . $type . '_config.php') ?: array()
			);
			if (count($allConfigs)) {
				$configPath = $allConfigs[0];
			}
			else {
				return;
			}
		}

		if (TL_MODE === 'BE') {
			$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/rocksolid-custom-elements/assets/js/be_main.js';
			$GLOBALS['TL_CSS'][] = 'system/modules/rocksolid-custom-elements/assets/css/be_main.css';
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
		$standardFields = is_array($config['standardFields']) ? $config['standardFields'] : array();
		$this->fieldsConfig = $config['fields'];

		foreach ($this->fieldsConfig as $fieldName => $fieldConfig) {
			$this->createDcaItem('rsce_field_', $fieldName, $fieldConfig, $paletteFields, $dc, $createFromPost);
		}
		if ($tmpField && !in_array($tmpField, $paletteFields)) {
			$fieldConfig = $this->getNestedConfig($tmpField, $this->fieldsConfig);
			if ($fieldConfig) {
				$this->createDcaItem($tmpField, '', $fieldConfig, $paletteFields, $dc, false);
			}
		}

		$paletteFields[] = 'rsce_data';

		if ($dc->table === 'tl_module') {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] = '{title_legend},name';
			if (in_array('headline', $standardFields)) {
				$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ',headline';
			}
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ',type';
		}
		else {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] = '{type_legend},type';
			if (in_array('headline', $standardFields)) {
				$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ',headline';
			}
			if (in_array('columns', $standardFields)) {
				$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ';{rs_columns_legend},rs_columns_large,rs_columns_medium,rs_columns_small';
			}
			if (in_array('text', $standardFields)) {
				$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ';{text_legend},text';
			}
		}
		$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ';{rsce_legend},';
		$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= implode(',', $paletteFields);
		if ($dc->table === 'tl_content' && in_array('image', $standardFields)) {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ';{image_legend},addImage';
		}
		$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ';{protected_legend:hide},protected;{expert_legend:hide},guests';
		if (in_array('cssID', $standardFields)) {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ',cssID';
		}
		if (in_array('space', $standardFields)) {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ',space';
		}
		if ($dc->table === 'tl_content') {
			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] .= ';{invisible_legend:hide},invisible,start,stop';
		}

		$GLOBALS['TL_LANG'][$dc->table]['rsce_legend'] = $GLOBALS['TL_LANG'][$dc->table === 'tl_content' ? 'CTE' : 'FMD'][$type][0];
	}

	/**
	 * Create one DCA field with the specified parameters
	 *
	 * This function calls itself recursively for nested data structures
	 *
	 * @param  string         $fieldPrefix    Field prefix, e.g. "rsce_field_"
	 * @param  string         $fieldName      Field name
	 * @param  array          $fieldConfig    Field configuration array
	 * @param  array          $paletteFields  Reference to the list of all fields
	 * @param  \DataContainer $dc             Data container
	 * @param  boolean        $createFromPost Whether to create the field structure from post data or not
	 * @return void
	 */
	protected function createDcaItem($fieldPrefix, $fieldName, $fieldConfig, &$paletteFields, $dc, $createFromPost)
	{
		if (strpos($fieldName, '__') !== false) {
			throw new \Exception('Field name must not include "__" (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
		}
		if (strpos($fieldName, 'rsce_field_') !== false) {
			throw new \Exception('Field name must not include "rsce_field_" (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
		}
		if (substr($fieldName, 0, 1) === '_' || substr($fieldName, -1) === '_') {
			throw new \Exception('Field name must not start or end with "_" (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
		}

		if ($fieldConfig['inputType'] === 'list') {

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '_rsce_list_start'] = array(
				'label' => $fieldConfig['label'],
				'inputType' => 'rsce_list_start',
			);
			$paletteFields[] = $fieldPrefix . $fieldName . '_rsce_list_start';

			$hasFields = false;
			foreach ($fieldConfig['fields'] as $fieldConfig2) {
				if ($fieldConfig2['inputType'] !== 'list') {
					$hasFields = true;
				}
			}
			if (!$hasFields) {
				// add an empty field
				$fieldConfig['fields']['rsce_empty'] = array(
					'inputType' => 'text',
					'eval' => array('tl_class' => 'hidden'),
				);
			}

			$this->createDcaItemListDummy($fieldPrefix, $fieldName, $fieldConfig, $paletteFields, $dc, $createFromPost);

			$fieldData = $this->getNestedValue($fieldPrefix . $fieldName);

			for (
				$dataKey = 0;
				$createFromPost ? $this->wasListFieldSubmitted($fieldPrefix . $fieldName, $dataKey) : isset($fieldData[$dataKey]);
				$dataKey++
			) {

				$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_start'] = array(
					'inputType' => 'rsce_list_item_start',
					'label' => array(sprintf($fieldConfig['elementLabel'], $dataKey + 1)),
					'eval' => array(
						'label_template' => $fieldConfig['elementLabel'],
					),
				);
				$paletteFields[] = $fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_start';

				foreach ($fieldConfig['fields'] as $fieldName2 => $fieldConfig2) {
					$this->createDcaItem($fieldPrefix . $fieldName . '__' . $dataKey . '__', $fieldName2, $fieldConfig2, $paletteFields, $dc, $createFromPost);
				}

				$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_stop'] = array(
					'inputType' => 'rsce_list_item_stop',
				);
				$paletteFields[] = $fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_stop';

			}

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '_rsce_list_stop'] = array(
				'inputType' => 'rsce_list_stop',
			);
			$paletteFields[] = $fieldPrefix . $fieldName . '_rsce_list_stop';

		}
		else if ($fieldConfig['inputType'] === 'standardField') {

			if ($fieldPrefix !== 'rsce_field_') {
				throw new \Exception('Input type "standardField" is not allowed inside lists.');
			}

			if (isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldName])) {

				if (
					isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldName]['eval'])
					&& is_array($GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldName]['eval'])
					&& isset($fieldConfig['eval'])
					&& is_array($fieldConfig['eval'])
				) {
					$fieldConfig['eval'] = array_merge(
						$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldName]['eval'],
						$fieldConfig['eval']
					);
				}

				unset($fieldConfig['inputType']);

				$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldName] = array_merge(
					$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldName],
					$fieldConfig
				);

				$paletteFields[] = $fieldName;

			}

		}
		else {

			if ($fieldConfig['inputType'] === 'url') {
				$fieldConfig['inputType'] = 'text';
				$fieldConfig['wizard'] = array(
					array('MadeYourDay\\Contao\\CustomElements', 'pagePicker')
				);
				$fieldConfig['eval']['tl_class'] =
					(isset($fieldConfig['eval']['tl_class']) ? $fieldConfig['eval']['tl_class'] . ' ' : '')
					. 'wizard';
			}

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
	 * Page picker wizard for url fields
	 *
	 * @param  \DataContainer $dc Data container
	 * @return string             Page picker button html code
	 */
	public function pagePicker($dc) {
		return ' <a'
			. ' href="contao/page.php'
				. '?do=' . \Input::get('do')
				. '&amp;table=' . $dc->table
				. '&amp;field=' . $dc->field
				. '&amp;value=' . str_replace(array('{{link_url::', '}}'), '', $dc->value)
			. '"'
			. ' title="' . specialchars($GLOBALS['TL_LANG']['MSC']['pagepicker']) . '"'
			. ' onclick="'
				. 'Backend.getScrollOffset();'
				. 'Backend.openModalSelector({'
					. '\'width\':765,'
					. '\'title\':' . specialchars(json_encode($GLOBALS['TL_LANG']['MOD']['page'][0])) . ','
					. '\'url\':this.href,'
					. '\'id\':\'' . $dc->field . '\','
					. '\'tag\':\'ctrl_'. $dc->field . ((\Input::get('act') == 'editAll') ? '_' . $dc->id : '') . '\','
					. '\'self\':this'
				. '});'
				. 'return false;'
			. '">'
			. \Image::getHtml(
				'pickpage.gif',
				$GLOBALS['TL_LANG']['MSC']['pagepicker'],
				'style="vertical-align:top;cursor:pointer"'
			)
			. '</a>';
	}

	/**
	 * Check if a field was sumitted via POST
	 *
	 * @param  string $fieldName field name to check
	 * @param  int    $dataKey   data index
	 * @return boolean           true if the field was sumitted via POST
	 */
	protected function wasListFieldSubmitted($fieldName, $dataKey)
	{
		if (!is_array(\Input::post('FORM_FIELDS'))) {
			return false;
		}

		if (strpos($fieldName, '__rsce_dummy__') !== false) {
			return false;
		}

		$formFields = array_unique(trimsplit(
			'[,;]',
			implode(',', \Input::post('FORM_FIELDS'))
		));

		$fieldPrefix = $fieldName . '__' . $dataKey . '__';

		foreach ($formFields as $field) {
			if (substr($field, 0, strlen($fieldPrefix)) === $fieldPrefix) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create one list item dummy with the specified parameters
	 *
	 * @param  string         $fieldPrefix    Field prefix, e.g. "rsce_field_"
	 * @param  string         $fieldName      Field name
	 * @param  array          $fieldConfig    Field configuration array
	 * @param  array          $paletteFields  Reference to the list of all fields
	 * @param  \DataContainer $dc             Data container
	 * @param  boolean        $createFromPost Whether to create the field structure from post data or not
	 * @return void
	 */
	protected function createDcaItemListDummy($fieldPrefix, $fieldName, $fieldConfig, &$paletteFields, $dc, $createFromPost)
	{
		$dataKey = 'rsce_dummy';

		$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_start'] = array(
			'inputType' => 'rsce_list_item_start',
			'label' => array($fieldConfig['elementLabel']),
			'eval' => array(
				'tl_class' => 'rsce_list_item_dummy',
				'label_template' => $fieldConfig['elementLabel'],
			),
		);
		$paletteFields[] = $fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_start';

		foreach ($fieldConfig['fields'] as $fieldName2 => $fieldConfig2) {
			$this->createDcaItem($fieldPrefix . $fieldName . '__' . $dataKey . '__', $fieldName2, $fieldConfig2, $paletteFields, $dc, $createFromPost);
		}

		$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_stop'] = array(
			'inputType' => 'rsce_list_item_stop',
		);
		$paletteFields[] = $fieldPrefix . $fieldName . '__' . $dataKey . '_rsce_list_item_stop';
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
	 * Purge cache file system/cache/rocksolid_custom_elements_config.php
	 *
	 * @return void
	 */
	public static function purgeCache()
	{
		$filePath = 'system/cache/rocksolid_custom_elements_config.php';
		$fileFullPath = TL_ROOT . '/' . $filePath;

		if (file_exists($fileFullPath)) {
			$file = new \File($filePath, true);
			$file->write('');
			$file->close();
			static::refreshOpcodeCache($fileFullPath);
		}
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

		$cacheHash = md5(implode(',', array_merge(
			glob(TL_ROOT . '/templates/rsce_*') ?: array(),
			glob(TL_ROOT . '/templates/*/rsce_*') ?: array()
		)));

		if (file_exists($fileFullPath)) {
			$fileCacheHash = null;
			include $fileFullPath;
			if ($fileCacheHash === $cacheHash) {
				// the cache file is valid and loaded
				return;
			}
		}

		$contents = array();
		$contents[] = '<?php' . "\n";
		$contents[] = '$fileCacheHash = ' . var_export($cacheHash, true) . ';' . "\n";

		$templates = \Controller::getTemplateGroup('rsce_');

		$allConfigs = array_merge(
			glob(TL_ROOT . '/templates/rsce_*_config.php') ?: array(),
			glob(TL_ROOT . '/templates/*/rsce_*_config.php') ?: array()
		);
		$fallbackConfigPaths = array();

		foreach ($allConfigs as $configPath) {
			$templateName = basename($configPath, '_config.php');
			if (file_exists(substr($configPath, 0, -11) . '.html5')) {
				if (!isset($templates[$templateName])) {
					$templates[$templateName] = $templateName;
				}
				if (!isset($fallbackConfigPaths[$templateName])) {
					$fallbackConfigPaths[$templateName] = $configPath;
				}
			}
		}

		$themes = \Database::getInstance()
			->prepare('SELECT name, templates FROM tl_theme')
			->execute()
			->fetchAllAssoc();
		$themeNamesByTemplateDir = array();
		foreach ($themes as $theme) {
			if ($theme['templates']) {
				$themeNamesByTemplateDir[$theme['templates']] = $theme['name'];
			}
		}

		$elements = array();

		foreach ($templates as $template => $label) {

			if (substr($template, -7) === '_config') {
				continue;
			}

			$configPath = null;

			try {
				$templatePaths = CustomTemplate::getTemplates($template);
				if (!empty($templatePaths[0])) {
					$configPath = substr($templatePaths[0], 0, -6) . '_config.php';
				}
			}
			catch (\Exception $e) {
				$configPath = null;
			}

			if ($configPath === null || !file_exists($configPath)) {
				if (isset($fallbackConfigPaths[$template])) {
					$configPath = $fallbackConfigPaths[$template];
				}
				else {
					continue;
				}
			}

			$config = include $configPath;

			$element = array(
				'config' => $config,
				'label' => isset($config['label']) ? $config['label'] : array(implode(' ', array_map('ucfirst', explode('_', substr($template, 5)))), ''),
				'types' => isset($config['types']) ? $config['types'] : array('content', 'module'),
				'contentCategory' => isset($config['contentCategory']) ? $config['contentCategory'] : 'custom_elements',
				'moduleCategory' => isset($config['moduleCategory']) ? $config['moduleCategory'] : 'custom_elements',
				'template' => $template,
				'path' => substr(dirname($configPath), strlen(TL_ROOT . '/')),
			);
			$element['plainLabel'] = $element['label'][0];

			if ($element['path'] && substr($element['path'], 10)) {
				if (isset($themeNamesByTemplateDir[$element['path']])) {
					$element['label'][0] = $themeNamesByTemplateDir[$element['path']] . ': ' . $element['label'][0];
				}
				else {
					$element['label'][0] = implode(' ', array_map('ucfirst', preg_split('(\\W)', substr($element['path'], 10)))) . ': ' . $element['label'][0];
				}
			}

			$elements[] = $element;

		}

		usort($elements, function($a, $b) {
			if ($a['path'] !== $b['path']) {
				if ($a['path'] === 'templates') {
					return -1;
				}
				if ($b['path'] === 'templates') {
					return 1;
				}
			}
			return strcmp($a['label'][0], $b['label'][0]);
		});

		$usePlainLabels = count(array_unique(array_map(function($element) {
			return $element['path'];
		}, $elements))) < 2;

		foreach ($elements as $element) {
			if ($usePlainLabels) {
				$element['label'][0] = $element['plainLabel'];
			}
			if (in_array('content', $element['types'])) {
				$contents[] = '$GLOBALS[\'TL_CTE\'][\'' . $element['contentCategory'] . '\'][\'' . $element['template'] . '\'] = \'MadeYourDay\\\\Contao\\\\Element\\\\CustomElement\';';
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'CTE\'][\'' . $element['template'] . '\'] = ' . var_export($element['label'], true) . ';';
				if (!empty($element['config']['wrapper']['type'])) {
					$contents[] = '$GLOBALS[\'TL_WRAPPERS\'][' . var_export($element['config']['wrapper']['type'], true) . '][] = ' . var_export($element['template'], true) . ';';
				}
			}
			if (in_array('module', $element['types'])) {
				$contents[] = '$GLOBALS[\'FE_MOD\'][\'' . $element['moduleCategory'] . '\'][\'' . $element['template'] . '\'] = \'MadeYourDay\\\\Contao\\\\Element\\\\CustomElement\';';
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'FMD\'][\'' . $element['template'] . '\'] = ' . var_export($element['label'], true) . ';';
			}
		}

		$file = new \File($filePath, true);
		$file->write(implode("\n", $contents));
		$file->close();
		static::refreshOpcodeCache($fileFullPath);

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
		static::purgeCache();

		return static::loadConfig();
	}

	/**
	 * Refreshes all active opcode caches for the specified file
	 *
	 * @param  string $path Path to the file
	 * @return boolean      True on success, false on failure
	 */
	protected static function refreshOpcodeCache($path)
	{
		try {

			// Zend OPcache
			if (function_exists('opcache_invalidate')) {
				opcache_invalidate($path, true);
			}

			// Zend Optimizer+
			if (function_exists('accelerator_reset')) {
				accelerator_reset();
			}

			// APC
			if (function_exists('apc_compile_file') && !ini_get('apc.stat')) {
				apc_compile_file($path);
			}

			// eAccelerator
			if (function_exists('eaccelerator_purge') && !ini_get('eaccelerator.check_mtime')) {
				@eaccelerator_purge();
			}

			// XCache
			if (function_exists('xcache_count') && !ini_get('xcache.stat')) {
				if (($count = xcache_count(XC_TYPE_PHP)) > 0) {
					for ($id = 0; $id < $count; $id++) {
						xcache_clear_cache(XC_TYPE_PHP, $id);
					}
				}
			}

			// WinCache
			if (function_exists('wincache_refresh_if_changed')) {
				wincache_refresh_if_changed(array($path));
			}

		}
		catch(\Exception $exception) {
			return false;
		}

		return true;
	}
}
