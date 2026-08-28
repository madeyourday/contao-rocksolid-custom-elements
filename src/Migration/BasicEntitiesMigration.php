<?php

declare(strict_types=1);

/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
