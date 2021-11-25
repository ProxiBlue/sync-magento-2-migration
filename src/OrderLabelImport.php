<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use EcomDev\MagentoMigration\Sql\InsertOnDuplicate;
use EcomDev\MagentoMigration\Sql\TableResolverFactory;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;


class OrderLabelImport
{
    /**
     * @var Sql
     */
    private $sql;

    const ORDERS_FIELDS = [
        'increment_id', 'name', 'background_color'
    ];

    const ORDER_FIELDS_DEFAULTS = [
        'select_column' => '_1553758927600_600',
        'enabled' => 1,
        'display_type' => 'text',
        'text_color' => '#000'
    ];

    const ORDER_MAP_FIELDS = [
        'order_id', 'label_id', 'is_manual'
    ];


    /**
     * @var TableResolverFactory
     */
    private $resolverFactory;

    public function __construct(Sql $sql, TableResolverFactory $resolverFactory)
    {
        $this->sql = $sql;
        $this->resolverFactory = $resolverFactory;
    }

    public static function createFromAdapter(Adapter $connection)
    {
        return new self(
            new Sql($connection),
            TableResolverFactory::createFromAdapter($connection)
        );
    }

    public function import(iterable $orderLabels): void
    {
        $this->transactional(function () use ($orderLabels) {

            $defaultColumns = array_keys(self::ORDER_FIELDS_DEFAULTS);
            $columns = array_merge(['name', 'background_color'], $defaultColumns);

            $insert = InsertOnDuplicate::create('mageplaza_orderlabels_label', $columns);

            // The destination table don't have unique indexes, so onDuplicate will not work
            // use code to prevent duplciate inserts
            $select = $this->sql->select('mageplaza_orderlabels_label')->columns(['name']);
            $labelsExisting = array_unique(array_column(iterator_to_array($this->sql->prepareStatementForSqlObject($select)->execute(), false), 'name'));
            foreach ($orderLabels as $label) {
                if (in_array($label['name'], $labelsExisting)) {
                    continue;
                }
                $labelsExisting[] = $label['name'];
                unset($label['increment_id']);
                $addDefaults = array_merge($label, self::ORDER_FIELDS_DEFAULTS);
                $insert->withAssocRow(
                    $addDefaults
                );
                $insert = $insert->flushIfLimitReached($this->sql);
            }

            $insert->executeIfNotEmpty($this->sql);
        });
    }

    public function importOrderLabelMaps(iterable $orderLabels): void
    {
        $this->transactional(function () use ($orderLabels) {
            $knownLabelIds = [];
            $select = $this->sql->select('mageplaza_orderlabels_label')
                ->columns(['rule_id', 'name']);
            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $labelRow) {
                $knownLabelIds[$labelRow['name']] = $labelRow['rule_id'];
            }

            $insert = InsertOnDuplicate::create('mageplaza_orderlabels_map_order', ['order_id', 'label_id', 'is_manual'])
                ->onDuplicate(['order_id', 'label_id', 'is_manual']);

            $select = $this->sql->select('sales_order')->columns(['increment_id', 'entity_id']);
            $orderData = [];
            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $orderData[$row['increment_id']] = $row['entity_id'];
            }

            foreach ($orderLabels as $label) {
                $mappedLabel = $knownLabelIds[$label['name']];
                if(isset($orderData[$label['increment_id']])) {
                    $insert->withAssocRow(
                        ['order_id' => $orderData[$label['increment_id']], 'label_id' => $mappedLabel, 'is_manual' => 1]
                    );
                    $insert = $insert->flushIfLimitReached($this->sql);
                }
            }
            $insert->executeIfNotEmpty($this->sql);
        });
    }

    private function transactional(callable $codeBlock)
    {
        $connection = $this->sql->getAdapter()->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $codeBlock();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }


}
