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
if (!empty($GLOBALS['TL_DCA']['tl_templates']['config']['editableFileTypes'])) {
	$GLOBALS['TL_DCA']['tl_templates']['config']['editableFileTypes'] .= ',php';
}
