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
		if ($this->addImage && trim($this->singleSRC)) {
			if (version_compare(VERSION, '3.2', '<')) {
				$fileModel = \FilesModel::findByPk($this->singleSRC);
			}
			else {
				$fileModel = \FilesModel::findByUuid($this->singleSRC);
			}
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

		$self = $this;

		$this->Template->getImageObject = function() use($self) {
			return call_user_func_array(array($self, 'getImageObject'), func_get_args());
		};
		$this->Template->getColumnClassName = function() use($self) {
			return call_user_func_array(array($self, 'getColumnClassName'), func_get_args());
		};
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

	/**
	 * Get an image object from id/uuid and an optional size configuration
	 *
	 * @param  int|string   $id   ID, UUID string or binary
	 * @param  string|array $size [width, height, mode] optionally serialized
	 * @return object             Image object (similar as addImageToTemplate)
	 */
	public function getImageObject($id, $size = null)
	{
		global $objPage;

		if (!$id) {
			return null;
		}

		if (strlen($id) === 36) {
			$id = \String::uuidToBin($id);
		}
		if (strlen($id) === 16) {
			$image = \FilesModel::findByUuid($id);
		}
		else {
			$image = \FilesModel::findByPk($id);
		}
		if (!$image) {
			return null;
		}

		$file = new \File($image->path, true);
		if (!$file->isGdImage) {
			return null;
		}

		$imageMeta = $this->getMetaData($image->meta, $objPage->language);

		if (is_string($size) && trim($size)) {
			$size = deserialize($size);
		}
		if (!is_array($size)) {
			$size = array(0, 0, 'center_center');
		}
		$size[0] = isset($size[0]) ? $size[0] : 0;
		$size[1] = isset($size[1]) ? $size[1] : 0;
		$size[2] = isset($size[2]) ? $size[2] : 'center_center';

		$image = array(
			'id' => $image->id,
			'uuid' => isset($image->uuid) ? $image->uuid : null,
			'name' => $file->basename,
			'singleSRC' => $image->path,
			'size' => serialize($size),
			'alt' => $imageMeta['title'],
			'imageUrl' => $imageMeta['link'],
			'caption' => $imageMeta['caption'],
		);

		$imageObject = new \stdClass();
		$this->addImageToTemplate($imageObject, $image);
		return $imageObject;
	}

	/**
	 * Get the column class name for the specified index
	 *
	 * @param  int    $index Index of the column
	 * @return string        Class name(s)
	 */
	public function getColumnClassName($index)
	{
		if (!class_exists('MadeYourDay\\Contao\\Element\\ColumnsStart')) {
			return '';
		}

		$config = ColumnsStart::getColumnsConfiguration($this->arrData);

		$classes = array('rs-column');
		foreach ($config as $name => $media) {
			$classes = array_merge($classes, $media[$index % count($media)]);
			if ($index < count($media)) {
				$classes[] = '-' . $name . '-first-row';
			}
		}

		return implode(' ', $classes);
	}
}
