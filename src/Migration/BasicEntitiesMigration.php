<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace MadeYourDay\RockSolidCustomElements\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\CoreBundle\Migration\Version500\AbstractBasicEntitiesMigration;

if (class_exists(AbstractBasicEntitiesMigration::class)) {
	class BasicEntitiesMigration extends AbstractBasicEntitiesMigration
	{
		protected function getDatabaseColumns(): array
		{
			return [
				['tl_content', 'rsce_data'],
				['tl_module', 'rsce_data'],
				['tl_form_field', 'rsce_data'],
			];
		}
	}
} else {
	class BasicEntitiesMigration extends AbstractMigration
	{
		public function shouldRun(): bool
		{
			return false;
		}

		public function run(): MigrationResult
		{
			throw new \LogicException();
		}
	}
}
