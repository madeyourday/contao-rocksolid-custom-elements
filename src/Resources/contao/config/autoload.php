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
	'MadeYourDay\\RockSolidCustomElements\\CustomElements' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/CustomElements.php',
	'MadeYourDay\\RockSolidCustomElements\\CustomElementsConvert' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/CustomElementsConvert.php',
	'MadeYourDay\\RockSolidCustomElements\\Module\\CustomModule' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Module/CustomModule.php',
	'MadeYourDay\\RockSolidCustomElements\\Element\\CustomElement' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Element/CustomElement.php',
	'MadeYourDay\\RockSolidCustomElements\\Form\\CustomWidget' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Form/CustomWidget.php',
	'MadeYourDay\\RockSolidCustomElements\\Model\\DummyModel' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Model/DummyModel.php',
	'MadeYourDay\\RockSolidCustomElements\\Template\\CustomTemplate' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Template/CustomTemplate.php',
	'MadeYourDay\\RockSolidCustomElements\\Widget\\ListStart' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListStart.php',
	'MadeYourDay\\RockSolidCustomElements\\Widget\\ListStop' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListStop.php',
	'MadeYourDay\\RockSolidCustomElements\\Widget\\ListItemStart' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListItemStart.php',
	'MadeYourDay\\RockSolidCustomElements\\Widget\\ListItemStop' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/ListItemStop.php',
	'MadeYourDay\\RockSolidCustomElements\\Widget\\GroupStart' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/GroupStart.php',
	'MadeYourDay\\RockSolidCustomElements\\Widget\\Hidden' => 'system/modules/rocksolid-custom-elements/src/MadeYourDay/Contao/Widget/Hidden.php',
));

$templatesFolder = version_compare(VERSION, '4.0', '>=')
	? 'vendor/madeyourday/contao-rocksolid-custom-elements/templates'
	: 'system/modules/rocksolid-custom-elements/templates';

TemplateLoader::addFiles(array(
	'form_rsce_plain' => $templatesFolder,
	'be_rsce_list' => $templatesFolder,
	'be_rsce_group' => $templatesFolder,
	'be_rsce_hidden' => $templatesFolder,
	'be_rsce_convert' => $templatesFolder,
));
