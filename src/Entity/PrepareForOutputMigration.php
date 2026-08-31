<?php

declare(strict_types=1);

/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

#[Table(name: 'rsce_prepare_for_output_encoding')]
#[Entity]
#[Index(name: 'performed_migration', columns: ['performed_migration'])]
class PrepareForOutputEncoding
{
    #[Id]
    #[Column(name: 'type_name')]
    public string $typeName;

    #[Id]
    #[Column(name: 'field_name', length: 512)]
    public string $fieldName;

    #[Column(name: 'encoding_options')]
    public array $encodingOptions;

    #[Column(name: 'performed_migration')]
    public bool $performedMigration;
}
