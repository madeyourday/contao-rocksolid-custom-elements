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
	 * @var array Fields configuration
	 */
	protected $fieldsConfig = array();

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

		$createFromPost = \Input::getInstance()->post('FORM_SUBMIT') === $dc->table;

		if ((
			\Environment::get('script') === 'contao/file.php' ||
			\Environment::get('script') === 'contao/page.php'
		) && \Input::get('field')) {
			$this->createDca($dc, $type, $createFromPost, \Input::get('field'));
		}
		else {
			$this->createDca($dc, $type, $createFromPost);
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
		return $this->getNestedValue($dc->field);
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

			$this->createDcaItemListDummy($fieldPrefix, $fieldName, $fieldConfig, &$paletteFields, $dc, $createFromPost);

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
		else {

			// remap page and file picker to get them work without a database field
			if (version_compare(VERSION, '3.1', '>=')) {
				if ($fieldConfig['inputType'] === 'fileTree') {
					$fieldConfig['inputType'] = 'rsce_file_tree';
				}
				if ($fieldConfig['inputType'] === 'pageTree') {
					$fieldConfig['inputType'] = 'rsce_page_tree';
				}
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
		$filePath = TL_ROOT . '/system/cache/rocksolid_custom_elements_config.php';
		if (file_exists($filePath)) {
			unlink($filePath);
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

		if (! file_exists($fileFullPath)) {

			$contents = array();
			$contents[] = '<?php' . "\n";

			$templates = \Controller::getTemplateGroup('rsce_');
			foreach ($templates as $template => $label) {

				if (substr($template, -7) === '_config') {
					continue;
				}

				try {
					$templatePaths = CustomTemplate::getTemplates($template);
					if (empty($templatePaths[0])) {
						continue;
					}
					$configPath = substr($templatePaths[0], 0, -6) . '_config.php';
					if (!file_exists($configPath)) {
						continue;
					}
				}
				catch (\Exception $e) {
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
