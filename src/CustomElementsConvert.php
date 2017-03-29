<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao;

use MadeYourDay\Contao\Element\CustomElement;

/**
 * RockSolid Custom Elements Convert
 *
 * This is used on the maintenance page in the backend
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomElementsConvert extends \Backend implements \executable
{
	/**
	 * @return boolean True if the module is active
	 */
	public function isActive()
	{
		return \Input::get('act') == 'rsce_convert';
	}

	/**
	 * Generate the module
	 *
	 * @return string
	 */
	public function run()
	{
		$objTemplate = new \BackendTemplate('be_rsce_convert');
		$objTemplate->isActive = $this->isActive();
		$objTemplate->action = ampersand(\Environment::get('request'));
		$objTemplate->indexHeadline = $GLOBALS['TL_LANG']['tl_maintenance']['searchIndex'];

		// Rebuild the index
		if (\Input::get('act') === 'rsce_convert') {

			// Check the request token
			if (!isset($_GET['rt']) || !\RequestToken::validate(\Input::get('rt')))
			{
				$this->Session->set('INVALID_TOKEN_URL', \Environment::get('request'));
				$this->redirect('contao/confirm.php');
			}

			$this->import('Database');

			$failedElements = array();
			$elementsCount = 0;

			$contentElements = \ContentModel::findBy(array(\ContentModel::getTable() . '.type LIKE ?'), 'rsce_%');

			while ($contentElements && $contentElements->next()) {

				$html = $this->getHtmlFromElement($contentElements);

				if (!$html) {
					$failedElements[] = array('content', $contentElements->id, $contentElements->type);
				}
				else {

					$this->createInitialVersion(\ContentModel::getTable(), $contentElements->id);

					$this->Database
						->prepare('UPDATE ' . \ContentModel::getTable() . ' SET tstamp = ?, type = \'html\', html = ? WHERE id = ?')
						->executeUncached(time(), $html, $contentElements->id);
					$elementsCount++;

					$this->createNewVersion(\ContentModel::getTable(), $contentElements->id);
					$this->log('A new version of record "' . \ContentModel::getTable() . '.id=' . $contentElements->id . '" has been created', __METHOD__, TL_GENERAL);

				}

			}

			$moduleElements = \ModuleModel::findBy(array(\ModuleModel::getTable() . '.type LIKE ?'), 'rsce_%');

			while ($moduleElements && $moduleElements->next()) {

				$html = $this->getHtmlFromElement($moduleElements);

				if (!$html) {
					$failedElements[] = array('module', $moduleElements->id, $moduleElements->type);
				}
				else {

					$this->createInitialVersion(\ModuleModel::getTable(), $moduleElements->id);

					$this->Database
						->prepare('UPDATE ' . \ModuleModel::getTable() . ' SET tstamp = ?, type = \'html\', html = ? WHERE id = ?')
						->executeUncached(time(), $html, $moduleElements->id);
					$elementsCount++;

					$this->createNewVersion(\ModuleModel::getTable(), $moduleElements->id);
					$this->log('A new version of record "' . \ModuleModel::getTable() . '.id=' . $moduleElements->id . '" has been created', __METHOD__, TL_GENERAL);

				}

			}

			$formElements = \FormFieldModel::findBy(array(\FormFieldModel::getTable() . '.type LIKE ?'), 'rsce_%');

			while ($formElements && $formElements->next()) {

				$html = $this->getHtmlFromElement($formElements);

				if (!$html) {
					$failedElements[] = array('form', $formElements->id, $formElements->type);
				}
				else {

					$this->createInitialVersion(\FormFieldModel::getTable(), $formElements->id);

					$this->Database
						->prepare('UPDATE ' . \FormFieldModel::getTable() . ' SET tstamp = ?, type = \'html\', html = ? WHERE id = ?')
						->executeUncached(time(), $html, $formElements->id);
					$elementsCount++;

					$this->createNewVersion(\FormFieldModel::getTable(), $formElements->id);
					$this->log('A new version of record "' . \FormFieldModel::getTable() . '.id=' . $formElements->id . '" has been created', __METHOD__, TL_GENERAL);

				}

			}

			foreach ($failedElements as $element) {
				$this->log('Failed to convert ' . $element[0] . ' element ID ' . $element[1] . ' (' . $element[2] . ') to a standard HTML element', __METHOD__, TL_ERROR);
			}

			$this->log('Converted ' . $elementsCount . ' RockSolid Custom Elements to standard HTML elements', __METHOD__, TL_GENERAL);

			$objTemplate->elementsCount = $elementsCount;
			$objTemplate->failedElements = $failedElements;

		}

		$this->loadLanguageFile('rocksolid_custom_elements');

		return $objTemplate->parse();
	}

	/**
	 * Parse a custom element and return the resulting HTML code
	 *
	 * @param  array $elementData The data to parse the template with
	 * @return string             HTML code
	 */
	public static function getHtmlFromElement($elementData)
	{
		try {
			$element = new CustomElement($elementData);
			return $element->generate();
		}
		catch (\Exception $exception) {
			return '';
		}
	}
}
