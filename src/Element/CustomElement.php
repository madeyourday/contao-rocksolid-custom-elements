<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Element;

use Contao\ContentElement;
use Contao\ContentModel;
use Contao\Image\PictureConfiguration;
use Contao\Input;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use MadeYourDay\RockSolidColumns\Element\ColumnsStart;
use MadeYourDay\RockSolidCustomElements\Template\CustomTemplate;
use MadeYourDay\RockSolidCustomElements\CustomElements;
use Symfony\Component\HttpFoundation\Request;

/**
 * Custom content element and frontend module
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomElement extends ContentElement
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
		$this->strTemplate = $this->customTpl ?: $this->type;

		// Return output for the backend if in BE mode
		if (($output = $this->rsceGetBackendOutput()) !== null) {
			return $output;
		}

		try {
			return parent::generate();
		}
		catch (\Exception $exception) {

			if (System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest(System::getContainer()->get('request_stack')->getCurrentRequest() ?? Request::create(''))) {

				$template = new CustomTemplate($this->strTemplate);
				$template->setData($this->Template->getData());
				$this->Template = $template;

				return $this->Template->parse();

			}

			throw $exception;
		}
	}

	/**
	 * Generate backend output if TL_MODE is set to BE
	 *
	 * @return string|null Backend output or null
	 */
	public function rsceGetBackendOutput()
	{
		if (!System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest(System::getContainer()->get('request_stack')->getCurrentRequest() ?? Request::create(''))) {
			return null;
		}

		$config = CustomElements::getConfigByType($this->type) ?: array();

		// Handle newsletter output the same way as the frontend
		if (!empty($config['isNewsletter'])) {

			if (Input::get('do') === 'newsletter') {
				return null;
			}

			foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $entry) {
				$method = $entry['class'] . '::' . $entry['function'];
				if (
					$entry['file'] === System::getContainer()->getParameter('kernel.project_dir') . '/system/modules/newsletter/classes/Newsletter.php'
					|| $entry['file'] === System::getContainer()->getParameter('kernel.project_dir') . '/vendor/contao/newsletter-bundle/src/Resources/contao/classes/Newsletter.php'
					|| $entry['file'] === System::getContainer()->getParameter('kernel.project_dir') . '/vendor/contao/newsletter-bundle/contao/classes/Newsletter.php'
					|| $method === 'Contao\\Newsletter::send'
					|| $method === 'tl_newsletter::listNewsletters'
				) {
					return null;
				}
			}

		}

		if (!empty($config['beTemplate'])) {

			if (!isset($this->arrData['wildcard'])) {
				$label = CustomElements::getLabelTranslated($config['label']);
				$this->arrData['wildcard'] = '### ' . mb_strtoupper(is_array($label) ? $label[0] : $label) . ' ###';
			}

			if (!isset($this->arrData['title'])) {
				$this->arrData['title'] = $this->headline;
			}

			if (
				!isset($this->arrData['link'])
				&& !isset($this->arrData['href'])
				&& $this->objModel instanceof ModuleModel
			) {
				$this->arrData['link'] = $this->name;
				$this->arrData['href'] = 'contao?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;
			}

			$this->strTemplate = $config['beTemplate'];

			return null;
		}

		if (
			in_array($this->type, $GLOBALS['TL_WRAPPERS']['start'])
			|| in_array($this->type, $GLOBALS['TL_WRAPPERS']['stop'])
			|| in_array($this->type, $GLOBALS['TL_WRAPPERS']['separator'])
		) {
			return '';
		}

		return null;
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
			$figure = System::getContainer()
				->get('contao.image.studio')
				->createFigureBuilder()
				->from($this->singleSRC)
				->setSize(StringUtil::deserialize($this->arrData['size'] ?? null) ?: null)
				->enableLightbox((bool) ($this->arrData['fullsize'] ?? false))
				->setLightboxSize(StringUtil::deserialize($this->arrData['lightboxSize'] ?? null) ?: null)
				->setMetadata((new ContentModel())->setRow($this->arrData)->getOverwriteMetadata())
				->buildIfResourceExists();

			if ($figure) {
				$figure->applyLegacyTemplateData($this->Template, null, $this->arrData['floating'] ?? null);
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

		$this->addFragmentControllerDefaults();
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
					$data->$key = StringUtil::deserialize($value);
				}
				else {
					$data[$key] = StringUtil::deserialize($value);
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

		if ($data instanceof \stdClass) {
			$return = new class extends \stdClass{
				public function __get($name) {
					return null;
				}
			};
			foreach ($data as $key => $value) {
				$return->$key = $value;
			}

			$data = $return;
		}

		return $data;
	}

	/**
	 * Get an image object from id/uuid and an optional size configuration
	 *
	 * @param  int|string                        $id         ID, UUID string or binary
	 * @param  string|array|PictureConfiguration $size       [width, height, mode] optionally serialized or a config object
	 * @param  int                               $maxSize    Gets passed to addImageToTemplate as $intMaxWidth
	 * @param  string                            $lightboxId Gets passed to addImageToTemplate as $strLightboxId
	 * @param  array                             $item       Gets merged and passed to addImageToTemplate as $arrItem
	 * @return object                                        Image object (similar as addImageToTemplate)
	 */
	public function getImageObject($id, $size = null, $deprecated = null, $lightboxId = null, $item = array())
	{
		if (!$id) {
			return null;
		}

		$figure = System::getContainer()
			->get('contao.image.studio')
			->createFigureBuilder()
			->from($id)
			->setSize($size)
			->enableLightbox((bool) ($item['fullsize'] ?? false))
			->setLightboxGroupIdentifier($lightboxId)
			->setLightboxSize(StringUtil::deserialize($item['lightboxSize'] ?? null) ?: null)
			->setMetadata((new ContentModel())->setRow($item)->getOverwriteMetadata())
			->buildIfResourceExists();

		if (null === $figure) {
			return null;
		}

		return (object) array_merge($figure->getLegacyTemplateData(), ['figure' => $figure]);
	}

	/**
	 * Get the column class name for the specified index
	 *
	 * @param  int    $index Index of the column
	 * @return string        Class name(s)
	 */
	public function getColumnClassName($index)
	{
		if (!class_exists(ColumnsStart::class)) {
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

	private function addFragmentControllerDefaults()
	{
		$this->Template->template ??= $this->Template->getName();
		$this->Template->as_editor_view ??= System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest(System::getContainer()->get('request_stack')->getCurrentRequest() ?? Request::create(''));
		$this->Template->data ??= $this->objModel ? $this->objModel->row() : $this->arrData;
		$this->Template->nested_fragments ??= [];
		$this->Template->section ??= $this->strColumn;
		$this->Template->properties ??= [];
		$this->Template->element_html_id ??= $this->Template->cssID[0] ?? null;
		$this->Template->element_css_classes ??= $this->Template->cssID[1] ?? '';

		// Legacy templates access the text using `$this->headline`, twig templates use `headline.text`
		$this->Template->headline = new class($this->Template->headline, $this->Template->hl) implements \Stringable
		{
			public ?string $text;
			public ?string $tag_name;

			public function __construct(?string $text, ?string $tag_name)
			{
				$this->text = $text;
				$this->tag_name = $tag_name;
			}

			public function __toString(): string
			{
				return $this->text ?? '';
			}
		};
	}
}
