<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements;

use Contao\Backend;
use Contao\BackendUser;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\Input;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\DBALException;
use MadeYourDay\RockSolidCustomElements\Template\CustomTemplate;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * RockSolid Custom Elements DCA (tl_content, tl_module and tl_form_field)
 *
 * Provide miscellaneous methods that are used by the data configuration arrays.
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 * @todo   Create cache files with different names to be able to drop the
 *         refreshOpcodeCache method
 */
class CustomElements
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
	 * tl_content, tl_module and tl_form_field DCA onload callback
	 *
	 * Reloads config and creates the DCA fields
	 *
	 * @param  DataContainer $dc Data container
	 * @return void
	 */
	public function onloadCallback($dc)
	{
		if (Input::get('act') === 'create') {
			return;
		}

		if (Input::get('act') === 'edit') {
			$this->reloadConfig();
		}

		if ($dc->table === 'tl_content' && class_exists('CeAccess')) {
			$ceAccess = new \CeAccess;
			$ceAccess->filterContentElements($dc);
		}

		if (Input::get('act') === 'editAll') {
			return $this->createDcaMultiEdit($dc);
		}

		$type = $this->getDcaFieldValue($dc, 'type');
		if (!$type || substr($type, 0, 5) !== 'rsce_') {
			return;
		}

		$data = $this->getDcaFieldValue($dc, 'rsce_data', true);
		if ($data && substr($data, 0, 1) === '{') {
			$this->data = json_decode($data, true);
		}

		$createFromPost = Input::post('FORM_SUBMIT') === $dc->table;
		$tmpField = null;

		if (Input::get('field') && substr(Input::get('field'), 0, 11) === 'rsce_field_') {
			// Ensures that the fileTree oder pageTree field exists
			$tmpField = Input::get('field');
		}
		elseif (
			Input::get('target')
			&& ($target = explode('.', Input::get('target'), 3))
			&& $target[0] === $dc->table
			&& substr($target[1], 0, 11) === 'rsce_field_'
		) {
			// Ensures that the fileTree oder pageTree field exists
			$tmpField = $target[1];
		}
		elseif (Input::post('name') && substr(Input::post('name'), 0, 11) === 'rsce_field_') {
			// Ensures that the fileTree oder pageTree field exists
			$tmpField = Input::post('name');
		}

		$this->createDca($dc, $type, $createFromPost, $tmpField);
	}

	/**
	 * tl_content, tl_module and tl_form_field DCA onsubmit callback
	 *
	 * Creates empty arrays for empty lists if no data is available
	 * (e.g. for new elements)
	 *
	 * @param  DataContainer $dc Data container
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
				Database::getInstance()
					->prepare("UPDATE {$dc->table} SET rsce_data = ? WHERE id = ?")
					->execute($data, $dc->id);
			}

		}
	}

	/**
	 * tl_content, tl_module and tl_form_field DCA onshow callback
	 *
	 * @param  array $data
	 * @param  array $row
	 * @param  DataContainer $dc Data container
	 *
	 * @return array
	 */
	public static function onshowCallback($data, $row, $dc)
	{
		if (
			empty($row['type'])
			|| substr($row['type'], 0, 5) !== 'rsce_'
			|| !$data
			|| empty($row['rsce_data'])
			|| $row['rsce_data'] === '{}'
			|| !$rsceData = json_decode($row['rsce_data'], true)
		) {
			return $data;
		}

		$config = static::getConfigByType($row['type']);

		foreach ($data as $table => $rows) {
			foreach ($rows as $rowIndex => $rowValues) {
				foreach ($rowValues as $label => $value) {
					if (substr($label, -24) === '<small>rsce_data</small>') {
						unset($data[$table][$rowIndex][$label]);
					}
				}
			}
		}

		if (!isset($data['tl_content.rsce_data'])) {
			$data = array_merge(['tl_content.rsce_data' => []], $data);
		}

		$data['tl_content.rsce_data'][] = self::formatShowData($rsceData, $config['fields'] ?? []);

		return $data;
	}

	private static function formatShowData(array $rsceData, array $fieldsConfig, string $prefix = '', string $keyPrefix = ''): array
	{
		$data = [];
		foreach ($rsceData as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $valueIndex => $valueRow) {
					$prefixLabel = static::getLabelTranslated($fieldsConfig[$key]['elementLabel'] ?? null) ?: $key . ' %s';
					$data = array_merge(
						$data,
						self::formatShowData(
							$valueRow,
							$fieldsConfig[$key]['fields'] ?? [],
							$prefix . sprintf($prefixLabel, $valueIndex + 1) . ' › ',
							$keyPrefix . $key . '[' . $valueIndex . ']->'
						)
					);
				}
			}
			else {
				$label = $prefix;
				$label .= (static::getLabelTranslated($fieldsConfig[$key]['label'] ?? null) ?: [$key])[0];
				$label .= '<small>' . $keyPrefix . $key . '</small>';
				$value = StringUtil::deserialize($value);
				if (is_array($value)) {
					$value = self::formatShowArray($value);
				}
				$data[$label] = (string) $value;
			}
		}

		return $data;
	}

	private static function formatShowArray(array $data): string
	{
		$data = array_map(function($value) {
			if (is_array($value)) {
				return '(' . self::formatShowArray($value) . ')';
			}
			return (string) $value;
		}, $data);

		if ($data && array_keys($data) !== range(0, count($data) - 1)) {
			$data = array_map(function($key, $value) {
				return $key.': '.$value;
			}, array_keys($data), $data);
		}

		return implode(', ', $data);
	}

	/**
	 * Field load callback
	 *
	 * Finds the current value for the field
	 *
	 * @param  string         $value Current value
	 * @param  DataContainer $dc    Data container
	 * @return string                Current value for the field
	 */
	public function loadCallback($value, $dc)
	{
		if ($value !== null) {
			return $value;
		}

		$value = $this->getNestedValue($dc->field);

		if ($value === null && isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['default'])) {
			$value = $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['default'];
		}

		if ($value && (
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fileTree'
			|| $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fineUploader'
		)) {
			$value = $this->uuidTextToBin($value);
		}

		return $value;
	}

	/**
	 * Field load callback multi edit
	 *
	 * Finds the current value for the field directly from the database
	 *
	 * @param  string         $value Current value
	 * @param  DataContainer $dc    Data container
	 * @return string                Current value for the field
	 */
	public function loadCallbackMultiEdit($value, $dc)
	{
		if ($value !== null) {
			return $value;
		}

		$field = substr($dc->field, strlen($dc->activeRecord->type . '_field_'));

		$data = Database::getInstance()
			->prepare("SELECT rsce_data FROM {$dc->table} WHERE id=?")
			->execute($dc->id)
			->rsce_data;
		$data = $data ? json_decode($data, true) : [];

		if (isset($data[$field])) {
			$value = $data[$field];
		}

		if ($value === null && isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['default'])) {
			$value = $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['default'];
		}

		if ($value && (
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fileTree'
			|| $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fineUploader'
		)) {
			$value = $this->uuidTextToBin($value);
		}

		return $value;
	}

	/**
	 * Get the value of the nested data array $this->data from field name
	 *
	 * @param  string $field        Field name
	 * @param  bool   $fromSaveData True to retrieve the value from $this->saveData instead of $this->data
	 * @return mixed         Value from $this->data or $this->saveData
	 */
	protected function getNestedValue($field, $fromSaveData = false)
	{
		$field = preg_split('(__([0-9]+)__)', substr($field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		if ($fromSaveData) {
			if (!isset($this->saveData[$field[0]])) {
				return null;
			}
			$data =& $this->saveData[$field[0]];
		}
		else {
			if (!isset($this->data[$field[0]])) {
				return null;
			}
			$data =& $this->data[$field[0]];
		}

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
	 * Unset a value of the nested data array $this->saveData from field name
	 *
	 * @param  string $field Field name
	 * @return void
	 */
	protected function unsetNestedValue($field)
	{
		$field = preg_split('(__([0-9]+)__)', substr($field, 11), -1, PREG_SPLIT_DELIM_CAPTURE);

		if (!isset($this->saveData[$field[0]])) {
			return;
		}

		if (\count($field) === 1) {
			unset($this->saveData[$field[0]]);

			return;
		}

		$data =& $this->saveData[$field[0]];

		for ($i = 0; isset($field[$i + 1]); $i += 2) {
			if (!isset($data[$field[$i + 1]])) {
				return;
			}
			if (!isset($data[$field[$i + 1]][$field[$i + 2]])) {
				return;
			}
			if (isset($field[$i + 3])) {
				$data =& $data[$field[$i + 1]][$field[$i + 2]];
			}
			else {
				unset($data[$field[$i + 1]][$field[$i + 2]]);
				return;
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
	 * @param  DataContainer $dc    Data container
	 * @return void
	 */
	public function saveCallback($value, $dc)
	{
		if (strpos($dc->field, '__rsce_dummy__') !== false) {
			return;
		}

		if (
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fileTree'
			|| $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fineUploader'
		) {
			$value = $this->uuidBinToText($value);
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

		return '';
	}

	/**
	 * Field save callback multi edit
	 *
	 * Saves the field data directly into the database field rsce_data
	 *
	 * @param  string         $value Field value
	 * @param  DataContainer $dc    Data container
	 * @return void
	 */
	public function saveCallbackMultiEdit($value, $dc)
	{
		if (
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fileTree'
			|| $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['inputType'] === 'fineUploader'
		) {
			$value = $this->uuidBinToText($value);
		}

		$field = substr($dc->field, strlen($dc->activeRecord->type . '_field_'));

		$data = Database::getInstance()
			->prepare("SELECT rsce_data FROM {$dc->table} WHERE id=?")
			->execute($dc->id)
			->rsce_data;
		$data = $data ? json_decode($data, true) : [];

		$data[$field] = $value;

		$data = json_encode($data);

		Database::getInstance()
			->prepare("UPDATE {$dc->table} SET rsce_data = ? WHERE id = ?")
			->execute($data, $dc->id);

		return '';
	}

	/**
	 * rsce_data field save callback
	 *
	 * Returns the JSON encoded $this->saveData
	 *
	 * @param  string         $value Current field value
	 * @param  DataContainer $dc    Data container
	 * @return string                JSON encoded $this->saveData
	 */
	public function saveDataCallback($value, $dc)
	{
		$this->prepareSaveData('rsce_field_', $this->fieldsConfig, $dc);

		$data = json_encode($this->saveData);

		if ($data === '[]') {
			$data = '{}';
		}

		return $data;
	}

	/**
	 * prepare the data to save and create empty arrays for empty lists
	 *
	 * @param  string         $fieldPrefix  field prefix
	 * @param  array          $fieldsConfig fields configuration
	 * @param  DataContainer $dc           Data container
	 * @return void
	 */
	protected function prepareSaveData($fieldPrefix, $fieldsConfig, $dc)
	{
		foreach ($fieldsConfig as $fieldName => $fieldConfig) {

			if (!empty($fieldConfig['dependsOn'])) {
				if (\is_string($fieldConfig['dependsOn'])) {
					$fieldConfig['dependsOn'] = ['field' => $fieldConfig['dependsOn']];
				}
				if (\is_array($fieldConfig['dependsOn'])) {
					$dependingFieldName = $this->getDependingFieldName($fieldConfig['dependsOn'], $fieldPrefix);
					if (substr($dependingFieldName, 0, 11) === 'rsce_field_') {
						$actualValue = $this->getNestedValue($dependingFieldName, true);
					}
					else {
						$actualValue = $dc->activeRecord ? $dc->activeRecord->$dependingFieldName : null;
					}
					if (!$this->dependingValueMatches($fieldConfig['dependsOn']['value'] ?? true, StringUtil::deserialize($actualValue))) {
						$this->unsetNestedValue($fieldPrefix . $fieldName);
					}
				}
			}

			if (isset($fieldConfig['inputType']) && $fieldConfig['inputType'] === 'list') {

				// creates an empty array for a empty lists
				$fieldData = $this->getNestedValueReference($fieldPrefix . $fieldName);

				for ($dataKey = 0; isset($fieldData[$dataKey]); $dataKey++) {
					$this->prepareSaveData($fieldPrefix . $fieldName . '__' . $dataKey . '__', $fieldConfig['fields'], $dc);
				}

			}

		}
	}

	/**
	 * Create all DCA fields for the specified type
	 *
	 * @param  DataContainer $dc             Data container
	 * @param  string         $type           The template name
	 * @param  boolean        $createFromPost Whether to create the field structure from post data or not
	 * @param  string         $tmpField       Field name to create temporarily for page or file tree widget ajax calls
	 * @return void
	 */
	protected function createDca($dc, $type, $createFromPost = false, $tmpField = null)
	{
		$config = static::getConfigByType($type);

		if (!$config) {
			return;
		}

		$assetsDir = 'bundles/rocksolidcustomelements';

		if (System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest(System::getContainer()->get('request_stack')->getCurrentRequest() ?? Request::create(''))) {
			$GLOBALS['TL_JAVASCRIPT'][] = $assetsDir . '/js/be_main.js';
			$GLOBALS['TL_CSS'][] = $assetsDir . '/css/be_main.css';
		}

		$paletteFields = array();
		$standardFields = is_array($config['standardFields'] ?? null) ? $config['standardFields'] : array();
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

		$GLOBALS['TL_DCA'][$dc->table]['fields']['rsce_data']['eval']['rsceScript'] = 'window.rsceInit([...document.querySelectorAll("script")].pop().parentNode.parentNode.parentNode);';

		$paletteFields[] = 'rsce_data';

		$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] = static::generatePalette(
			$dc->table,
			$paletteFields,
			$standardFields
		);

		$GLOBALS['TL_DCA'][$dc->table]['fields']['customTpl']['options_callback'] = function($dc) {
			$templates = Controller::getTemplateGroup($dc->activeRecord->type.'_', [], $dc->activeRecord->type);
			foreach ($templates as $key => $label) {
				if (substr($key, -7) === '_config' || $key === $dc->activeRecord->type) {
					unset($templates[$key]);
				}
			}
			return $templates;
		};

		$GLOBALS['TL_LANG'][$dc->table]['rsce_legend'] = $GLOBALS['TL_LANG'][$dc->table === 'tl_content' ? 'CTE' : ($dc->table === 'tl_module' ? 'FMD' : 'FFL')][$type][0];

		if (!empty($config['onloadCallback']) && is_array($config['onloadCallback'])) {
			foreach ($config['onloadCallback'] as $callback) {
				if (is_array($callback)) {
					System::importStatic($callback[0])->{$callback[1]}($dc);
				}
				else if (is_callable($callback)) {
					$callback($dc);
				}
			}
		}
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
	 * @param  DataContainer $dc             Data container
	 * @param  boolean        $createFromPost Whether to create the field structure from post data or not
	 * @param  boolean        $multiEdit      Whether to create the field for the multi edit view
	 * @return void
	 */
	protected function createDcaItem($fieldPrefix, $fieldName, $fieldConfig, &$paletteFields, $dc, $createFromPost, $multiEdit = false)
	{
		if (!is_string($fieldConfig) && !is_array($fieldConfig)) {
			throw new \Exception('Field config must be of type array or string.');
		}
		if (strpos($fieldName, '__') !== false) {
			throw new \Exception('Field name must not include "__" (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
		}
		if (strpos($fieldName, 'rsce_field_') !== false) {
			throw new \Exception('Field name must not include "rsce_field_" (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
		}
		if (substr($fieldName, 0, 1) === '_' || substr($fieldName, -1) === '_') {
			throw new \Exception('Field name must not start or end with "_" (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
		}

		if (!is_string($fieldName)) {
			$fieldName = 'unnamed_' . $fieldName;
		}

		if (is_string($fieldConfig)) {
			$fieldConfig = array(
				'inputType' => 'group',
				'label' => array($fieldConfig, ''),
			);
		}

		if (
			!BackendUser::getInstance()->hasAccess($dc->table . '::rsce_data', 'alexf')
			&& $fieldConfig['inputType'] !== 'standardField'
		) {
			return;
		}

		if (isset($fieldConfig['label'])) {
			$translatedLabel = static::getLabelTranslated($fieldConfig['label']);
			// Don’t overwrite referenced variable
			unset($fieldConfig['label']);
			$fieldConfig['label'] = $translatedLabel;
		}

		if (
			isset($fieldConfig['reference'])
			&& is_array($fieldConfig['reference'])
			&& count(array_filter($fieldConfig['reference'], 'is_array'))
		) {
			$translatedReference = array_map(function($label) {
				return \MadeYourDay\RockSolidCustomElements\CustomElements::getLabelTranslated($label);
			}, $fieldConfig['reference']);
			// Don’t overwrite referenced variable
			unset($fieldConfig['reference']);
			$fieldConfig['reference'] = $translatedReference;
		}

		if (isset($fieldConfig['dependsOn'])) {
			if (\is_string($fieldConfig['dependsOn'])) {
				$fieldConfig['dependsOn'] = ['field' => $fieldConfig['dependsOn']];
			}
			if (\is_array($fieldConfig['dependsOn'])) {
				if (empty($fieldConfig['eval']['tl_class'])) {
					$fieldConfig['eval']['tl_class'] = '';
				}
				$fieldConfig['eval']['tl_class'] .= ' rsce-depends-on-'.rawurlencode(json_encode([
					'field' => $this->getDependingFieldName($fieldConfig['dependsOn'], $fieldPrefix),
					'value' => $fieldConfig['dependsOn']['value'] ?? true,
				]));
			}
		}

		if ($fieldConfig['inputType'] === 'list') {

			if (isset($fieldConfig['elementLabel'])) {
				$translatedLabel = static::getLabelTranslated($fieldConfig['elementLabel']);
				// Don’t overwrite referenced variable
				unset($fieldConfig['elementLabel']);
				$fieldConfig['elementLabel'] = $translatedLabel;
			} else {
				$fieldConfig['elementLabel'] = "%s";
			}

			$fieldConfig['minItems'] = isset($fieldConfig['minItems']) ? (int)$fieldConfig['minItems'] : 0;
			$fieldConfig['maxItems'] = isset($fieldConfig['maxItems']) ? (int)$fieldConfig['maxItems'] : null;

			if ($fieldConfig['maxItems'] && $fieldConfig['maxItems'] < $fieldConfig['minItems']) {
				throw new \Exception('maxItems must not be higher than minItems (' . $this->getDcaFieldValue($dc, 'type') . ': ' . $fieldName . ').');
			}

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName . '_rsce_list_start'] = array(
				'label' => $fieldConfig['label'],
				'inputType' => 'rsce_list_start',
				'eval' => array_merge(isset($fieldConfig['eval']) ? $fieldConfig['eval'] : array(), array(
					'minItems' => $fieldConfig['minItems'],
					'maxItems' => $fieldConfig['maxItems'],
				)),
			);
			$paletteFields[] = $fieldPrefix . $fieldName . '_rsce_list_start';

			$hasFields = false;
			foreach ($fieldConfig['fields'] as $fieldConfig2) {
				if (isset($fieldConfig2['inputType']) && $fieldConfig2['inputType'] !== 'list') {
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
				$dataKey < $fieldConfig['minItems'] || ($createFromPost ? $this->wasListFieldSubmitted($fieldPrefix . $fieldName, $dataKey) : isset($fieldData[$dataKey]));
				$dataKey++
			) {

				if (is_int($fieldConfig['maxItems']) && $dataKey > $fieldConfig['maxItems'] - 1) {
					break;
				}

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

			if (strpos($fieldPrefix, '__') !== false) {
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
		else if ($fieldConfig['inputType'] === 'group') {

			$fieldConfig['inputType'] = 'rsce_group_start';

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName] = $fieldConfig;
			$paletteFields[] = $fieldPrefix . $fieldName;

		}
		else {

			if ($fieldConfig['inputType'] === 'url') {
				$fieldConfig['inputType'] = 'text';
				$fieldConfig['eval'] = array_merge(array(
					'rgxp' => 'url',
					'decodeEntities' => true,
					'dcaPicker' => true,
					'addWizardClass' => false,
				), isset($fieldConfig['eval']) ? $fieldConfig['eval'] : []);
			}

			if ($fieldConfig['inputType'] === 'pageTree' || $fieldConfig['inputType'] === 'fileTree') {
				if (!isset($fieldConfig['eval']['fieldType'])) {
					$fieldConfig['eval']['fieldType'] = 'radio';
				}
			}

			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName] = $fieldConfig;
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['eval']['alwaysSave'] = true;
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['eval']['doNotSaveEmpty'] = true;
			if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['load_callback'])) {
				$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['load_callback'] = array();
			}
			array_unshift(
				$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['load_callback'],
				array('MadeYourDay\\RockSolidCustomElements\\CustomElements', $multiEdit ? 'loadCallbackMultiEdit' : 'loadCallback')
			);
			$GLOBALS['TL_DCA'][$dc->table]['fields'][$fieldPrefix . $fieldName]['save_callback'][] =
				array('MadeYourDay\\RockSolidCustomElements\\CustomElements', $multiEdit ? 'saveCallbackMultiEdit' : 'saveCallback');

			$paletteFields[] = $fieldPrefix . $fieldName;

		}
	}

	/**
	 * Get depending field name from dependsOn config resoving relative ../ parts
	 *
	 * @param  array  $config
	 * @param  string $prefix
	 * @return string
	 */
	protected function getDependingFieldName(array $config, string $prefix): string
	{
		if (empty($config['field'])) {
			return '';
		}

		$field = $config['field'];
		$prefixParts = explode('__', substr($prefix, 11));

		if ($field[0] === '/') {
			return substr($field, 1);
		}

		while(substr($field, 0, 3) === '../') {
			$field = substr($field, 3);

			if (!\count($prefixParts)) {
				throw new \RuntimeException(sprintf('Invalid field path "%s" for prefix "%s".', $config['field'], $prefix));
			}

			array_splice($prefixParts, -2);
		}

		if (!\count($prefixParts)) {
			return $field;
		}

		$prefixParts[\count($prefixParts) - 1] = "";

		return 'rsce_field_' . implode('__', $prefixParts) . $field;
	}

	/**
	 * Compare depending value from config with actual value
	 *
	 * @param  bool|string|array<string> $dependingValue
	 * @param  string|array<string>      $actualValue
	 * @return bool
	 */
	protected function dependingValueMatches($dependingValue, $actualValue): bool
	{
		if (\is_array($dependingValue)) {
			foreach	($dependingValue as $value) {
				if ($this->dependingValueMatches($value, $actualValue)) {
					return true;
				}
			}

			return false;
		}

		if (\is_array($actualValue)) {
			foreach	($actualValue as $value) {
				if ($this->dependingValueMatches($dependingValue, $value)) {
					return true;
				}
			}

			return false;
		}

		if ($dependingValue === true) {
			return !!$actualValue || $actualValue === '0';
		}

		return $dependingValue === $actualValue;
	}

	/**
	 * Page picker wizard for url fields
	 *
	 * @param  DataContainer $dc Data container
	 * @return string             Page picker button html code
	 */
	public function pagePicker($dc)
	{
		@trigger_error('Using pagePicker() has been deprecated and will be removed in a future version. Set the "dcaPicker" eval attribute instead.', E_USER_DEPRECATED);

		return Backend::getDcaPickerWizard(true, $dc->table, $dc->field, $dc->inputName);
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
		if (!is_array(Input::post('FORM_FIELDS'))) {
			return false;
		}

		if (strpos($fieldName, '__rsce_dummy__') !== false) {
			return false;
		}

		$formFields = array_unique(StringUtil::trimsplit(
			'[,;]',
			implode(',', Input::post('FORM_FIELDS'))
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
	 * @param  DataContainer $dc             Data container
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
	 * Create all DCA standard fields for multi edit mode
	 *
	 * @param  DataContainer $dc Data container
	 * @return void
	 */
	protected function createDcaMultiEdit($dc)
	{
		$session = System::getContainer()->get('session')->all();
		if (empty($session['CURRENT']['IDS']) || !is_array($session['CURRENT']['IDS'])) {
			return;
		}
		$ids = $session['CURRENT']['IDS'];

		$types = Database::getInstance()
			->prepare('
				SELECT type
				FROM ' . $dc->table . '
				WHERE id IN (' . implode(',', $ids) . ')
					AND type LIKE \'rsce_%\'
				GROUP BY type
			')
			->execute()
			->fetchEach('type');

		if (!$types) {
			return;
		}

		foreach ($types as $type) {

			$paletteFields = array();
			$config = static::getConfigByType($type);

			if (!$config) {
				continue;
			}

			$standardFields = is_array($config['standardFields'] ?? null) ? $config['standardFields'] : array();

			foreach ($config['fields'] as $fieldName => $fieldConfig) {
				if (isset($fieldConfig['inputType']) && $fieldConfig['inputType'] !== 'list') {
					$this->createDcaItem($type . '_field_', $fieldName, $fieldConfig, $paletteFields, $dc, false, true);
				}
			}

			$GLOBALS['TL_DCA'][$dc->table]['palettes'][$type] = static::generatePalette(
				$dc->table,
				$paletteFields,
				$standardFields
			);

		}
	}

	/**
	 * Get configuration array for the specified type
	 *
	 * @param  string     $type Element type beginning with "rsce_"
	 * @return array|null       Configuration array
	 */
	public static function getConfigByType($type)
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
				glob(System::getContainer()->getParameter('kernel.project_dir') . '/templates/' . $type . '_config.php') ?: array(),
				glob(System::getContainer()->getParameter('kernel.project_dir') . '/templates/*/' . $type . '_config.php') ?: array()
			);
			if (count($allConfigs)) {
				$configPath = $allConfigs[0];
			}
			else {
				return;
			}
		}

		$config = include $configPath;

		if ($config) {
			$config['fields'] = is_array($config['fields'] ?? null) ? $config['fields'] : array();
		}

		return $config;
	}

	/**
	 * Generates the palette definition
	 *
	 * @param  string $table          "tl_content", "tl_module" or "tl_form_field"
	 * @param  array  $paletteFields  Palette fields
	 * @param  array  $standardFields Standard fields
	 * @return string                 Palette definition
	 */
	protected static function generatePalette($table, array $paletteFields = array(), array $standardFields = array())
	{
		$palette = '';

		if ($table === 'tl_module') {
			$palette .= '{title_legend},name';
			if (in_array('headline', $standardFields)) {
				$palette .= ',headline';
			}
			$palette .= ',type';
		}
		else {
			$palette .= '{type_legend},type';
			if ($table === 'tl_content' && in_array('headline', $standardFields)) {
				$palette .= ',headline';
			}
			if (in_array('columns', $standardFields)) {
				$palette .= ';{rs_columns_legend},rs_columns_large,rs_columns_medium,rs_columns_small';
			}
			if (in_array('text', $standardFields)) {
				$palette .= ';{text_legend},text';
			}
		}

		if (
			isset($paletteFields[0])
			&& $paletteFields[0] !== 'rsce_data'
			&& isset($GLOBALS['TL_DCA'][$table]['fields'][$paletteFields[0]]['inputType'])
			&& $GLOBALS['TL_DCA'][$table]['fields'][$paletteFields[0]]['inputType'] !== 'rsce_group_start'
			&& $GLOBALS['TL_DCA'][$table]['fields'][$paletteFields[0]]['inputType'] !== 'rsce_list_start'
		) {
			$palette .= ';{rsce_legend}';
		}

		$palette .= ',' . implode(',', $paletteFields);

		if ($table === 'tl_content' && in_array('image', $standardFields)) {
			$palette .= ';{image_legend},addImage';
		}

		if ($table === 'tl_form_field') {
			$palette .= ';{expert_legend:hide},class';
		}

		$palette .= ';{template_legend:hide},customTpl';

		if ($table !== 'tl_form_field') {

			$palette .= ';{protected_legend:hide},protected;{expert_legend:hide},guests';

			if (in_array('cssID', $standardFields)) {
				$palette .= ',cssID';
			}

		}

		if ($table === 'tl_content') {
			$palette .= ';{invisible_legend:hide},invisible,start,stop';
		}

		return $palette;
	}

	/**
	 * Get the value of a field (from POST data, active record or the database)
	 *
	 * @param  DataContainer $dc        Data container
	 * @param  string         $fieldName Field name
	 * @param  boolean        $fromDb    True to ignore POST data
	 * @return string                    The value
	 */
	protected static function getDcaFieldValue($dc, $fieldName, $fromDb = false)
	{
		$value = null;

		if (Input::post('FORM_SUBMIT') === $dc->table && !$fromDb) {
			$value = Input::post($fieldName);
			if ($value !== null) {
				return $value;
			}
		}

		if ($dc->activeRecord) {
			$value = $dc->activeRecord->$fieldName;
		}
		else {

			$table = $dc->table;
			$id = $dc->id;

			if (Input::get('target')) {
				$table = explode('.', Input::get('target'), 2)[0];
				$id = (int) explode('.', Input::get('target'), 3)[2];
			}

			if ($table && $id) {
				$record = Database::getInstance()
					->prepare("SELECT * FROM {$table} WHERE id=?")
					->execute($id);
				if ($record->next()) {
					$value = $record->$fieldName;
				}
			}

		}

		return $value;
	}

	/**
	 * Purge cache file rocksolid_custom_elements_config.php
	 *
	 * @return void
	 */
	public static function purgeCache()
	{
		$filePaths = static::getCacheFilePaths();

		if (file_exists($filePaths['fullPath'])) {
			(new Filesystem())->dumpFile($filePaths['fullPath'], '');
			static::refreshOpcodeCache($filePaths['fullPath']);
		}
	}

	/**
	 * Get path and fullPath to the cache file
	 *
	 * @return string
	 */
	public static function getCacheFilePaths()
	{
		$cacheDir = System::getContainer()->getParameter('kernel.cache_dir') . '/contao';

		$filePath = $cacheDir . '/rocksolid_custom_elements_config.php';

		return array(
			'path' => StringUtil::stripRootDir($filePath),
			'fullPath' => $filePath,
		);
	}

	/**
	 * Load the TL_CTE, FE_MOD and TL_FFL configuration and use caching if possible
	 *
	 * @param  bool $bypassCache
	 * @return void
	 */
	public static function loadConfig($bypassCache = false)
	{
		$filePaths = static::getCacheFilePaths();

		$cacheHash = md5(implode(',', array_merge(
			glob(System::getContainer()->getParameter('kernel.project_dir') . '/templates/rsce_*') ?: array(),
			glob(System::getContainer()->getParameter('kernel.project_dir') . '/templates/*/rsce_*') ?: array()
		)));

		if (!$bypassCache && file_exists($filePaths['fullPath'])) {
			$fileCacheHash = null;
			include $filePaths['fullPath'];
			if ($fileCacheHash === $cacheHash) {
				// the cache file is valid and loaded
				return;
			}
		}

		System::loadLanguageFile('default');
		System::loadLanguageFile('tl_content');
		System::loadLanguageFile('tl_module');

		$contents = array();
		$contents[] = '<?php' . "\n";
		$contents[] = '$fileCacheHash = ' . var_export($cacheHash, true) . ';' . "\n";

		$templates = Controller::getTemplateGroup('rsce_');

		$allConfigs = array_merge(
			glob(System::getContainer()->getParameter('kernel.project_dir') . '/templates/rsce_*_config.php') ?: array(),
			glob(System::getContainer()->getParameter('kernel.project_dir') . '/templates/*/rsce_*_config.php') ?: array()
		);
		$fallbackConfigPaths = array();

		$duplicateConfigs = array_filter(
			array_count_values(array_map(
				function($configPath) {
					return basename($configPath, '_config.php');
				},
				$allConfigs
			)),
			function ($count) {
				return $count > 1;
			}
		);
		if (count($duplicateConfigs)) {
			System::log('Duplicate Custom Elements found: ' . implode(', ', array_keys($duplicateConfigs)), __METHOD__, TL_ERROR);
		}

		foreach ($allConfigs as $configPath) {
			$templateName = basename($configPath, '_config.php');
			if (
				file_exists(substr($configPath, 0, -11) . '.html5')
				|| file_exists(substr($configPath, 0, -11) . '.html.twig')
			) {
				if (!isset($templates[$templateName])) {
					$templates[$templateName] = $templateName;
				}
				if (!isset($fallbackConfigPaths[$templateName])) {
					$fallbackConfigPaths[$templateName] = $configPath;
				}
			}
		}

		try {
			$themes = Database::getInstance()
				->prepare('SELECT name, templates FROM tl_theme')
				->execute()
				->fetchAllAssoc();
		}
		catch (DBALException $e) {
			$themes = array();
		}
		catch (\Doctrine\DBAL\Exception $e) {
			$themes = array();
		}
		$themeNamesByTemplateDir = array();
		foreach ($themes as $theme) {
			if ($theme['templates']) {
				$themeNamesByTemplateDir[$theme['templates']] = $theme['name'];
			}
		}

		$saveToCache = true;
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

			try {
				$config = include $configPath;
			}
			catch (\Throwable $exception) {
				$request = System::getContainer()->get('request_stack')->getCurrentRequest();

				if ($request && $request->get('_route') === 'contao_install') {
					$saveToCache = false;
					continue;
				}

				throw $exception;
			}

			$element = array(
				'config' => $config,
				'label' => isset($config['label']) ? $config['label'] : array(implode(' ', array_map('ucfirst', explode('_', substr($template, 5)))), ''),
				'labelPrefix' => '',
				'types' => isset($config['types']) ? $config['types'] : array('content', 'module', 'form'),
				'contentCategory' => isset($config['contentCategory']) ? $config['contentCategory'] : 'custom_elements',
				'moduleCategory' => isset($config['moduleCategory']) ? $config['moduleCategory'] : 'custom_elements',
				'template' => $template,
				'path' => substr(dirname($configPath), strlen(System::getContainer()->getParameter('kernel.project_dir') . '/')),
			);

			if ($element['path'] && substr($element['path'], 0, 10) === 'templates/') {
				if (isset($themeNamesByTemplateDir[$element['path']])) {
					$element['labelPrefix'] = $themeNamesByTemplateDir[$element['path']] . ': ';
				}
				else {
					$element['labelPrefix'] = implode(' ', array_map('ucfirst', preg_split('(\\W)', substr($element['path'], 10)))) . ': ';
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
				return strcmp($a['labelPrefix'], $b['labelPrefix']);
			}
			return strcmp($a['template'], $b['template']);
		});

		$addLabelPrefix = count(array_unique(array_map(function($element) {
			return $element['path'];
		}, $elements))) > 1;

		foreach ($elements as $element) {

			if (in_array('content', $element['types'])) {

				$GLOBALS['TL_CTE'][$element['contentCategory']][$element['template']] = 'MadeYourDay\\RockSolidCustomElements\\Element\\CustomElement';
				$contents[] = '$GLOBALS[\'TL_CTE\'][\'' . $element['contentCategory'] . '\'][\'' . $element['template'] . '\'] = \'MadeYourDay\\\\RockSolidCustomElements\\\\Element\\\\CustomElement\';';

				$GLOBALS['TL_LANG']['CTE'][$element['template']] = static::getLabelTranslated($element['label']);
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'CTE\'][\'' . $element['template'] . '\'] = \\MadeYourDay\\RockSolidCustomElements\\CustomElements::getLabelTranslated(' . var_export($element['label'], true) . ');';

				if ($addLabelPrefix && $element['labelPrefix']) {
					$GLOBALS['TL_LANG']['CTE'][$element['template']][0] = $element['labelPrefix'] . $GLOBALS['TL_LANG']['CTE'][$element['template']][0];
					$contents[] = '$GLOBALS[\'TL_LANG\'][\'CTE\'][\'' . $element['template'] . '\'][0] = ' . var_export($element['labelPrefix'], true) . ' . $GLOBALS[\'TL_LANG\'][\'CTE\'][\'' . $element['template'] . '\'][0];';
				}

				if (!isset($GLOBALS['TL_LANG']['CTE'][$element['contentCategory']])) {
					$GLOBALS['TL_LANG']['CTE'][$element['contentCategory']] = $element['contentCategory'];
				}
				$contents[] = 'if (!isset($GLOBALS[\'TL_LANG\'][\'CTE\'][' . var_export($element['contentCategory'], true) . '])) {';
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'CTE\'][' . var_export($element['contentCategory'], true) . '] = ' . var_export($element['contentCategory'], true) . ';';
				$contents[] = '}';

			}

			if (in_array('module', $element['types'])) {

				$GLOBALS['FE_MOD'][$element['moduleCategory']][$element['template']] = 'MadeYourDay\\RockSolidCustomElements\\Element\\CustomElement';
				$contents[] = '$GLOBALS[\'FE_MOD\'][\'' . $element['moduleCategory'] . '\'][\'' . $element['template'] . '\'] = \'MadeYourDay\\\\RockSolidCustomElements\\\\Element\\\\CustomElement\';';

				$GLOBALS['TL_LANG']['FMD'][$element['template']] = static::getLabelTranslated($element['label']);
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'FMD\'][\'' . $element['template'] . '\'] = \\MadeYourDay\\RockSolidCustomElements\\CustomElements::getLabelTranslated(' . var_export($element['label'], true) . ');';

				if ($addLabelPrefix && $element['labelPrefix']) {
					$GLOBALS['TL_LANG']['FMD'][$element['template']][0] = $element['labelPrefix'] . $GLOBALS['TL_LANG']['FMD'][$element['template']][0];
					$contents[] = '$GLOBALS[\'TL_LANG\'][\'FMD\'][\'' . $element['template'] . '\'][0] = ' . var_export($element['labelPrefix'], true) . ' . $GLOBALS[\'TL_LANG\'][\'FMD\'][\'' . $element['template'] . '\'][0];';
				}

				if (!isset($GLOBALS['TL_LANG']['FMD'][$element['moduleCategory']])) {
					$GLOBALS['TL_LANG']['FMD'][$element['moduleCategory']] = $element['moduleCategory'];
				}
				$contents[] = 'if (!isset($GLOBALS[\'TL_LANG\'][\'FMD\'][' . var_export($element['moduleCategory'], true) . '])) {';
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'FMD\'][' . var_export($element['moduleCategory'], true) . '] = ' . var_export($element['moduleCategory'], true) . ';';
				$contents[] = '}';

			}

			if (in_array('form', $element['types'])) {

				$hasInput = isset($element['config']['fields']['name']['inputType']) && $element['config']['fields']['name']['inputType'] === 'standardField';

				$GLOBALS['TL_FFL'][$element['template']] = 'MadeYourDay\\RockSolidCustomElements\\Form\\CustomWidget'.($hasInput ? '' : 'NoInput');
				$contents[] = '$GLOBALS[\'TL_FFL\'][\'' . $element['template'] . '\'] = \'MadeYourDay\\\\RockSolidCustomElements\\\\Form\\\\CustomWidget'.($hasInput ? '' : 'NoInput').'\';';

				$GLOBALS['TL_LANG']['FFL'][$element['template']] = static::getLabelTranslated($element['label']);
				$contents[] = '$GLOBALS[\'TL_LANG\'][\'FFL\'][\'' . $element['template'] . '\'] = \\MadeYourDay\\RockSolidCustomElements\\CustomElements::getLabelTranslated(' . var_export($element['label'], true) . ');';

				if ($addLabelPrefix && $element['labelPrefix']) {
					$GLOBALS['TL_LANG']['FFL'][$element['template']][0] = $element['labelPrefix'] . $GLOBALS['TL_LANG']['FFL'][$element['template']][0];
					$contents[] = '$GLOBALS[\'TL_LANG\'][\'FFL\'][\'' . $element['template'] . '\'][0] = ' . var_export($element['labelPrefix'], true) . ' . $GLOBALS[\'TL_LANG\'][\'FFL\'][\'' . $element['template'] . '\'][0];';
				}

			}

			if (!empty($element['config']['wrapper']['type'])) {
				$GLOBALS['TL_WRAPPERS'][$element['config']['wrapper']['type']][] = $element['template'];
				$contents[] = '$GLOBALS[\'TL_WRAPPERS\'][' . var_export($element['config']['wrapper']['type'], true) . '][] = ' . var_export($element['template'], true) . ';';
			}

		}

		if (!$saveToCache) {
			return;
		}

		(new Filesystem())->dumpFile($filePaths['fullPath'], implode("\n", $contents));
		static::refreshOpcodeCache($filePaths['fullPath']);
	}

	/**
	 * Call loadConfig and bypass the cache
	 *
	 * @return void
	 */
	public static function reloadConfig()
	{
		return static::loadConfig(true);
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

	/**
	 * Reload translated labels if default language file gets loaded
	 *
	 * @param  string $name
	 * @param  string $language
	 * @return void
	 */
	public function loadLanguageFileHook($name, $language)
	{
		if ($name === 'default') {
			static::loadConfig();
		}
	}

	/**
	 * Return translated label if label configuration contains language keys
	 *
	 * @param  array $labelConfig
	 * @return mixed              Translated label if exists, otherwise $labelConfig
	 */
	public static function getLabelTranslated($labelConfig)
	{
		if (!is_array($labelConfig)) {
			return $labelConfig;
		}

		// Return if it isn't an associative array
		if (!count(array_filter(array_keys($labelConfig), 'is_string'))) {
			return $labelConfig;
		}

		$language = str_replace('-', '_', $GLOBALS['TL_LANGUAGE']);
		if (isset($labelConfig[$language])) {
			return $labelConfig[$language];
		}

		// Try the short language code
		$language = substr($language, 0, 2);
		if (isset($labelConfig[$language])) {
			return $labelConfig[$language];
		}

		// Fall back to english
		$language = 'en';
		if (isset($labelConfig[$language])) {
			return $labelConfig[$language];
		}

		// Return the first item that seems to be a language key
		foreach ($labelConfig as $key => $label) {
			if (strlen($key) === 2 || substr($key, 2, 1) === '_') {
				return $label;
			}
		}

		return $labelConfig;
	}

	/**
	 * Convert IDs for theme export
	 *
	 * @param  \DOMDocument $xml        theme.xml
	 * @param  \ZipWriter   $zipArchive CTO file
	 * @param  int          $themeId
	 * @return void
	 */
	public function exportThemeHook($xml, $zipArchive, $themeId)
	{
		$xpath = new \DOMXPath($xml);
		$tlModule = $xpath->query('/tables/table[@name = \'tl_module\']')->item(0);

		if (!$tlModule) {
			return;
		}

		static::reloadConfig();

		foreach ($tlModule->childNodes as $row) {

			if (strtolower($row->nodeName) !== 'row') {
				continue;
			}

			$type = $xpath->query('field[@name = \'type\']', $row)->item(0);
			if (!$type || substr($type->nodeValue, 0, 5) !== 'rsce_') {
				continue;
			}

			$rsceData = $xpath->query('field[@name = \'rsce_data\']', $row)->item(0);
			if (!$rsceData) {
				continue;
			}

			$rsceDataConverted = $this->convertDataForImportExport(
				false,
				$type->nodeValue,
				$rsceData->nodeValue
			);

			if (
				!$rsceDataConverted
				|| strtolower($rsceDataConverted) === 'null'
				|| $rsceDataConverted === '{}'
				|| $rsceDataConverted === '[]'
				|| $rsceDataConverted === $rsceData->nodeValue
			) {
				continue;
			}

			$rsceData->nodeValue = $rsceDataConverted;

		}
	}

	/**
	 * Convert IDs for theme import
	 *
	 * @param  \DOMDocument $xml           theme.xml
	 * @param  \ZipWriter   $zipArchive    CTO file
	 * @param  int          $themeId
	 * @param  array        $idMappingData ID mapping for imported database rows
	 * @return void
	 */
	public function extractThemeFilesHook($xml, $zipArchive, $themeId, $idMappingData)
	{
		$modules = ModuleModel::findBy(
			array('tl_module.pid = ? AND tl_module.type LIKE \'rsce_%\''),
			$themeId
		);

		if (!$modules || !count($modules)) {
			return;
		}

		foreach ($modules as $module) {

			if (substr($module->type, 0, 5) !== 'rsce_') {
				continue;
			}

			$rsceDataConverted = $this->convertDataForImportExport(
				true,
				$module->type,
				$module->rsce_data,
				$idMappingData
			);

			if (
				!$rsceDataConverted
				|| strtolower($rsceDataConverted) === 'null'
				|| $rsceDataConverted === '{}'
				|| $rsceDataConverted === '[]'
				|| $rsceDataConverted === $module->rsce_data
			) {
				continue;
			}

			$module->rsce_data = $rsceDataConverted;

			$module->save();

		}
	}

	/**
	 * @param  bool   $import        True for import, false for export
	 * @param  string $type          Element type
	 * @param  string $jsonData      JSON-encoded data
	 * @param  array  $idMappingData ID mapping for imported database rows
	 * @return string                Converted $jsonData
	 */
	protected function convertDataForImportExport($import, $type, $jsonData, $idMappingData = array())
	{
		$data = json_decode($jsonData, true);
		$config = static::getConfigByType($type);

		if (!$config || !$data) {
			return $jsonData;
		}

		$data = $this->convertDataForImportExportParseFields(
			$import,
			$data,
			$config['fields'],
			$idMappingData
		);

		return json_encode($data);
	}

	/**
	 * @param  bool   $import        True for import, false for export
	 * @param  array  $data          Data of element or parent list item
	 * @param  array  $config        Fields configuration
	 * @param  array  $idMappingData ID mapping for imported database rows
	 * @param  string $fieldPrefix
	 * @return array                 Converted $data
	 */
	protected function convertDataForImportExportParseFields($import, $data, $config, $idMappingData, $fieldPrefix = 'rsce_field_')
	{
		foreach ($data as $fieldName => $value) {

			$fieldConfig = $this->getNestedConfig($fieldPrefix . $fieldName, $config);

			if (empty($fieldConfig['inputType'])) {
				continue;
			}

			if ($fieldConfig['inputType'] === 'list') {

				for ($dataKey = 0; isset($value[$dataKey]); $dataKey++) {
					$data[$fieldName][$dataKey] = $this->convertDataForImportExportParseFields(
						$import,
						$value[$dataKey],
						$config,
						$idMappingData,
						$fieldPrefix . $fieldName . '__' . $dataKey . '__'
					);
				}

			}

			// UUIDs to paths and vice versa
			else if ($value && (
				$fieldConfig['inputType'] === 'fileTree'
				|| $fieldConfig['inputType'] === 'fineUploader'
			)) {

				if (empty($fieldConfig['eval']['multiple'])) {

					if ($import) {
						$file = FilesModel::findByPath(System::getContainer()->getParameter('contao.upload_path') . '/' . preg_replace('(^files/)', '', $value));
						if ($file) {
							$data[$fieldName] = StringUtil::binToUuid($file->uuid);
						}
					}
					else {
						$file = FilesModel::findById($value);
						if ($file) {
							$data[$fieldName] = 'files/' . preg_replace('(^' . preg_quote(System::getContainer()->getParameter('contao.upload_path')) . '/)', '', $file->path);
						}
					}

				}
				else {

					$data[$fieldName] = serialize(array_map(
						function($value) use($import) {
							if ($import) {
								$file = FilesModel::findByPath(System::getContainer()->getParameter('contao.upload_path') . '/' . preg_replace('(^files/)', '', $value));
								if ($file) {
									return StringUtil::binToUuid($file->uuid);
								}
							}
							else {
								$file = FilesModel::findById($value);
								if ($file) {
									return 'files/' . preg_replace('(^' . preg_quote(System::getContainer()->getParameter('contao.upload_path')) . '/)', '', $file->path);
								}
							}
							return $value;
						},
						StringUtil::deserialize($value, true)
					));

				}

			}

			// tl_image_size IDs
			else if ($fieldConfig['inputType'] === 'imageSize' && $value && $import) {

				$value = StringUtil::deserialize($value, true);

				if (
					!empty($value[2])
					&& is_numeric($value[2])
					&& !empty($idMappingData['tl_image_size'][$value[2]])
				) {
					$value[2] = $idMappingData['tl_image_size'][$value[2]];
					$data[$fieldName] = serialize($value);
				}

			}

		}

		return $data;
	}

	/**
	 * Convert binary UUIDs to text
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function uuidBinToText($value)
	{
		if (trim($value) && $value !== 'a:1:{i:0;s:0:"";}') {
			if (strlen($value) === 16) {
				return StringUtil::binToUuid($value);
			}
			return serialize(array_map('StringUtil::binToUuid', StringUtil::deserialize($value)));
		}
		return '';
	}

	/**
	 * Convert text UUIDs to binary
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function uuidTextToBin($value)
	{
		// Multiple files
		if (substr($value, 0, 2) === 'a:') {
			return serialize(array_map(function($value) {
				if (strlen($value) === 36) {
					$value = StringUtil::uuidToBin($value);
				}
				return $value;
			}, StringUtil::deserialize($value)));
		}
		// Single file
		if (strlen($value) === 36) {
			return StringUtil::uuidToBin($value);
		}

		return $value;
	}
}
