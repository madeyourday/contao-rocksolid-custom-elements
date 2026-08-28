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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use MadeYourDay\RockSolidCustomElements\CustomElements;

class PrepareForOutputEncodingMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function shouldRun(): bool
    {
        if (version_compare(ContaoCoreBundle::getVersion(), '6.0', '>=')) {
            return false;
        }

        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_content', 'tl_module', 'tl_form_field', 'rsce_prepare_for_output_encoding'])) {
            return false;
        }

        $targets = $this->getTargets();

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM rsce_prepare_for_output_encoding');

        if ($count !== \count($targets)) {
            return true;
        }

        foreach ($targets as [$type, $field, $options]) {
            $test = $this->connection->fetchOne(
                <<<'SQL'
                    SELECT TRUE FROM rsce_prepare_for_output_encoding
                    WHERE type_name = :type
                    AND field_name = :field
                    AND JSON_CONTAINS(encoding_options, :options)
                    AND JSON_CONTAINS(:options, encoding_options)
                    SQL,
                [
                    'type' => $type,
                    'field' => $field,
                    'options' => $options,
                ],
                [
                    'type' => Types::STRING,
                    'field' => Types::STRING,
                    'options' => Types::JSON,
                ],
            );

            if (false === $test) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement('TRUNCATE TABLE rsce_prepare_for_output_encoding');

        foreach ($this->getTargets() as [$type, $field, $options]) {
            $this->connection->insert(
                'rsce_prepare_for_output_encoding',
                [
                    'type_name' => $type,
                    'field_name' => $field,
                    'encoding_options' => $options,
                    'performed_migration' => false,
                ],
                [
                    'type_name' => Types::STRING,
                    'field_name' => Types::STRING,
                    'encoding_options' => Types::JSON,
                    'performed_migration' => Types::BOOLEAN,
                ],
            );
        }

        return $this->createResult(true);
    }

    /**
     * @return list<array{0: string, 1: string, 3: array}>
     */
    private function getTargets(): array
    {
        $this->framework->initialize();

        $elements = $this->connection->fetchFirstColumn("
            SELECT DISTINCT type FROM (
                SELECT DISTINCT type FROM tl_content c WHERE type LIKE 'rsce\\_%'
                UNION 
                SELECT DISTINCT type FROM tl_module m WHERE type LIKE 'rsce\\_%'
                UNION 
                SELECT DISTINCT type FROM tl_form_field ff WHERE type LIKE 'rsce\\_%'
            ) a
            ORDER BY 1
        ");

        $targets = [];

        foreach ($elements as $element) {
            $targets[] = $this->getTargetsRecursive($element, CustomElements::getConfigByType($element)['fields'] ?? []);
        }

        return array_merge(...$targets);
    }

    private function getTargetsRecursive(string $typeName, array $fields, string $prefix = ''): array
    {
        $targets = [];
        foreach ($fields as $fieldName => $fieldConfig) {
            if (!is_string($fieldName) || !is_array($fieldConfig)) {
                continue;
            }

            if (($fieldConfig['inputType'] ?? null) === 'list') {
                $targets = [...$targets, ...$this->getTargetsRecursive($typeName, $fieldConfig['fields'] ?? [], "$prefix{$fieldName}__")];
                continue;
            }

            $options = $this->getEncodingOptions($fieldConfig, false);

            if (!$options) {
                continue;
            }

            $targets[] = [$typeName, "$prefix$fieldName", $options];
        }

        return $targets;
    }

    private function getEncodingOptions(array $fieldConfig, bool $force): array
    {
        if (
            !$force
            && \in_array(
                $fieldConfig['inputType'] ?? null,
                [
                    null,
                    'select',
                    'radio',
                    'radioTable',
                    'checkbox',
                    'checkboxWizard',
                    'picker',
                    'pageTree',
                    'fileTree',
                    'fileUpload',
                    'moduleWizard',
                    'sectionWizard',
                    'chmod',
                    'cud',
                    'imageSize',
                ],
                true,
            )
        ) {
            return [];
        }

        if (
            ($fieldConfig['eval']['useRawRequestData'] ?? null)
            || ($fieldConfig['eval']['allowHtml'] ?? null)
            || ($fieldConfig['eval']['preserveTags'] ?? null)
            || 'ace|html' === ($fieldConfig['eval']['rte'] ?? null)
            || str_starts_with($fieldConfig['eval']['rte'] ?? '', 'tiny')
        ) {
            return [];
        }

        if ($fieldConfig['eval']['decodeEntities'] ?? null) {
            return ['decodeEntities'];
        }

        return ['fullyEncoded'];
    }
}
