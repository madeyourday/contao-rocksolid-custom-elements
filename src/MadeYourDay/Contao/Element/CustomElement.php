<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Element;

use MadeYourDay\Contao\Template\CustomTemplate;

/**
 * Custom content element and frontend module
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomElement extends \ContentElement
{
	/**
	 * @var string Template
	 */
	protected $strTemplate = 'rsce_default';

	/**
	 * Find the correct template and parse it
	 *
	 * @return string Parsed template
	 */
	public function generate()
	{
		$this->strTemplate = $this->type;

		try {
			return parent::generate();
		}
		catch (\Exception $exception) {

			if (TL_MODE === 'BE') {

				// close unclosed output buffer
				ob_end_clean();

				$template = new CustomTemplate($this->strTemplate);
				$template->setData($this->Template->getData());
				$this->Template = $template;

				return $this->Template->parse();

			}

			throw $exception;
		}
	}

	/**
	 * Parse the json data and pass it to the template
	 *
	 * @return void
	 */
	public function compile()
	{
		// Add an image
		if ($this->addImage && $this->singleSRC) {
			$fileModel = \FilesModel::findByPk($this->singleSRC);
			if ($fileModel !== null && is_file(TL_ROOT . '/' . $fileModel->path)) {
				$this->singleSRC = $fileModel->path;
				$this->addImageToTemplate($this->Template, $this->arrData);
			}
		}

		$data = array();
		if ($this->rsce_data && substr($this->rsce_data, 0, 1) === '{') {
			$data = json_decode($this->rsce_data);
		}

		$data = $this->deserializeDataRecursive($data);

		foreach ($data as $key => $value) {
			$this->Template->$key = $value;
		}
	}

	/**
	 * Deserialize all data recursively
	 *
	 * @param  array|object $data data array or object
	 * @return array|object       data passed in with deserialized values
	 */
	protected function deserializeDataRecursive($data)
	{
		foreach ($data as $key => $value) {
			if (is_string($value) && trim($value)) {
				if (is_object($data)) {
					$data->$key = deserialize($value);
				}
				else {
					$data[$key] = deserialize($value);
				}
			}
			else if (is_array($value) || is_object($value)) {
				if (is_object($data)) {
					$data->$key = $this->deserializeDataRecursive($value);
				}
				else {
					$data[$key] = $this->deserializeDataRecursive($value);
				}
			}
		}

		return $data;
	}
}
