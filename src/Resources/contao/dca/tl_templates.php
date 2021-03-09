<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Custom Elements DCA
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */

use Contao\Config;

if (!empty($GLOBALS['TL_DCA']['tl_templates']['config']['validFileTypes'])) {
	$GLOBALS['TL_DCA']['tl_templates']['config']['validFileTypes'] .= ',php';
}
$GLOBALS['TL_DCA']['tl_templates']['config']['onload_callback'][] = function() {
	Config::set('editableFiles', Config::get('editableFiles') . ',php');
};
$originalButtonCallback = $GLOBALS['TL_DCA']['tl_templates']['list']['operations']['source']['button_callback'];
$GLOBALS['TL_DCA']['tl_templates']['list']['operations']['source']['button_callback'] = function($row, $href, $label, $title, $icon, $attributes) use ($originalButtonCallback) {
	if (substr(basename($row['id']), 0, 5) !== 'rsce_' || substr($row['id'], -11) !== '_config.php') {
		if (is_array($originalButtonCallback)) {
			return System::importStatic($originalButtonCallback[0])->{$originalButtonCallback[1]}($row, $href, $label, $title, $icon, $attributes);
		}
		return $originalButtonCallback($row, $href, $label, $title, $icon, $attributes);
	}
	return '<a href="' . \Contao\Backend::addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . Contao\StringUtil::specialchars($title) . '"' . $attributes . '>' . Contao\Image::getHtml($icon, $label) . '</a> ';
};
