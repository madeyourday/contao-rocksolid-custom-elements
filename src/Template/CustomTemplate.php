<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao\Template;

/**
 * Custom backend template
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */
class CustomTemplate extends \FrontendTemplate
{
	/**
	 * {@inheritdoc}
	 */
	protected function getTemplatePath($template, $format = 'html5', $default = false)
	{
		if ($default) {
			return parent::getTemplatePath($template, $format, $default);
		}

		return static::getTemplate($template, $format);
	}

	/**
	 * Get the template path
	 *
	 * @param  string $template Template name
	 * @param  string $format   Format (xhtml or html5)
	 * @return string           Template path
	 */
	public static function getTemplate($template, $format = 'html5')
	{
		$templates = static::getTemplates($template, $format = 'html5');

		return isset($templates[0]) ? $templates[0] : null;
	}

	/**
	 * Get all found template paths
	 *
	 * @param  string $template Template name
	 * @param  string $format   Format (xhtml or html5)
	 * @return array            All template paths for the specified template
	 */
	public static function getTemplates($template, $format = 'html5')
	{
		$templates = array();

		try {
			$theme = \ThemeModel::findAll(array('order'=>'name'));
		}
		catch (\Exception $e) {
			$theme = null;
		}

		while ($theme && $theme->next()) {
			if ($theme->templates != '') {
				if (file_exists(TL_ROOT . '/' . $theme->templates . '/' . $template . '.' . $format)) {
					$templates[] = TL_ROOT . '/' . $theme->templates . '/' . $template . '.' . $format;
				}
			}
		}

		if (file_exists(TL_ROOT . '/templates/' . $template . '.' . $format)) {
			$templates[] = TL_ROOT . '/templates/' . $template . '.' . $format;
		}

		// Add templates of inactive themes to the bottom of the templates array
		$allFiles = glob(TL_ROOT . '/templates/*/' . $template . '.' . $format) ?: array();
		foreach ($allFiles as $file) {
			if (!in_array($file, $templates)) {
				$templates[] = $file;
			}
		}

		if (count($templates)) {
			return $templates;
		}

		return array(parent::getTemplate($template, $format));
	}
}
