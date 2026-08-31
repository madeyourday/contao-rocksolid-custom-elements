<?php

declare(strict_types=1);

/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidCustomElements\Migration;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

class OutputEncodingMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        if (version_compare(ContaoCoreBundle::getVersion(), '6.0', '<')) {
            return false;
        }

        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_content', 'tl_module', 'tl_form_field', 'rsce_prepare_for_output_encoding'])) {
            return false;
        }

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM rsce_prepare_for_output_encoding WHERE performed_migration = 0');

        return $count > 0;
    }

    public function run(): MigrationResult
    {
        $converted = [];

        foreach ($this->getTargets() as $type => $fields) {
            foreach (['tl_content', 'tl_module', 'tl_form_field'] as $table) {
                foreach ($this->connection->fetchAllKeyValue("SELECT id, rsce_data FROM `$table` WHERE type = :type AND rsce_data IS NOT NULL", ['type' => $type], ['type' => Types::STRING]) as $id => $dbValue) {
                    $convertedDbValue = $this->convertElement($dbValue, $fields);

                    if ($convertedDbValue !== $dbValue) {
                        $this->connection->update(
                            $table,
                            ['rsce_data' => $convertedDbValue],
                            ['id' => $id],
                            [Types::STRING, Types::INTEGER],
                        );
                        $converted[] = "$table.$id($type)";
                    }
                }
            }
            $this->connection->update('rsce_prepare_for_output_encoding', ['performed_migration' => 1], ['type_name' => $type]);
        }

        natsort($converted);

        return $this->createResult(true, "{$this->getName()} executed successfully: " . implode(', ', $converted));
    }

    private function convertElement(string $dbValue, array $fields)
    {
        if (!$data = json_decode($dbValue, true)) {
            return $dbValue;
        }

        $this->convertElementRecursive($data, $fields);

        $dbValue = json_encode($data);

        if ($dbValue === '[]') {
            $dbValue = '{}';
        }

        return $dbValue;
    }

    private function convertElementRecursive(array &$data, array $fields, string $prefix = ''): void
    {
        foreach ($data as $fieldName => $value) {
            if (isset($fields["$prefix$fieldName"])) {
                $data[$fieldName] = $this->migrateField($value, $fields["$prefix$fieldName"]);
            } elseif (is_array($value) && array_is_list($value)) {
                foreach (array_keys($value) as $index) {
                    if (is_array($data[$fieldName][$index])) {
                        $this->convertElementRecursive($data[$fieldName][$index], $fields, "$prefix{$fieldName}__");
                    }
                }
            }
        }
    }

    private function migrateField($value, $options)
    {
        $decode = match ($options) {
            ['decodeEntities'] => static fn (string $value): string => str_replace(['&#60;', '&#92;0'], ['<', '\0'], $value),
            ['fullyEncoded'] => static function (string $value): string {
                // Decoding JSON potentially breaks the JSON structure
                if (json_validate($value)) {
                    return $value;
                }

                $value = str_replace(['&#123;&#123;', '&#125;&#125;'], ['[{]', '[}]'], $value);

                return html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            },
            default => throw new \LogicException(\sprintf('Unexpected encoding options %s', json_encode($options, JSON_THROW_ON_ERROR))),
        };

        if (is_array($value)) {
            $value = array_map(fn ($v) => $this->migrateField($v, $options), $value);
        } elseif (is_string($value)) {
            $convertedValue = StringUtil::deserialize($value);

            if (\is_string($convertedValue)) {
                $value = $decode($value);
            } elseif (\is_array($convertedValue)) {
                array_walk_recursive(
                    $convertedValue,
                    static function (&$val) use ($decode): void {
                        if (\is_string($val)) {
                            $val = $decode($val);
                        }
                    },
                );
                $value = serialize($convertedValue);
            }
        }

        return $value;
    }

    private function getTargets(): array
    {
        $targets = [];
        $all = $this->connection->fetchAllNumeric('SELECT type_name, field_name, encoding_options FROM rsce_prepare_for_output_encoding WHERE performed_migration = 0');

        foreach ($all as [$type, $field, $options]) {
            $targets[$type][$field] = json_decode($options, true, flags: JSON_THROW_ON_ERROR);
        }

        return $targets;
    }
}
