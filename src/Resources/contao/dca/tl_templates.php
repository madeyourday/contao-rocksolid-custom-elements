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
use Contao\CoreBundle\Util\PackageUtil;

if (
	is_callable([PackageUtil::class, 'getContaoVersion'])
	&& version_compare(PackageUtil::getContaoVersion(), '4.7', '>=')
    	&& version_compare(PackageUtil::getContaoVersion(), '4.9.2', '<')
) {
	$GLOBALS['TL_DCA']['tl_templates']['config']['validFileTypes'] .= ',php';
	$GLOBALS['TL_DCA']['tl_templates']['config']['onload_callback'][] = function() {
		Config::set('editableFiles', Config::get('editableFiles') . ',php');
	};
}
