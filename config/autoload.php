<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Custom Elements autload configuration
 *
 * @author Martin Auswöger <martin@madeyourday.net>
 */

ClassLoader::addClasses(array(
	'MadeYourDay\\Contao\\CustomElements' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/CustomElements.php',
	'MadeYourDay\\Contao\\CustomElementsConvert' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/CustomElementsConvert.php',
	'MadeYourDay\\Contao\\Module\\CustomModule' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Module/CustomModule.php',
	'MadeYourDay\\Contao\\Element\\CustomElement' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Element/CustomElement.php',
	'MadeYourDay\\Contao\\Template\\CustomTemplate' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Template/CustomTemplate.php',
	'MadeYourDay\\Contao\\Widget\\ListStart' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListStart.php',
	'MadeYourDay\\Contao\\Widget\\ListStop' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListStop.php',
	'MadeYourDay\\Contao\\Widget\\ListItemStart' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListItemStart.php',
	'MadeYourDay\\Contao\\Widget\\ListItemStop' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListItemStop.php',
	'MadeYourDay\\Contao\\Widget\\GroupStart' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/GroupStart.php',
	'MadeYourDay\\Contao\\Widget\\Hidden' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/Hidden.php',
));

TemplateLoader::addFiles(array(
	'be_rsce_list' => 'system/modules/rocksolid-custom-elements/templates',
	'be_rsce_group' => 'system/modules/rocksolid-custom-elements/templates',
	'be_rsce_hidden' => 'system/modules/rocksolid-custom-elements/templates',
	'be_rsce_convert' => 'system/modules/rocksolid-custom-elements/templates',
));
