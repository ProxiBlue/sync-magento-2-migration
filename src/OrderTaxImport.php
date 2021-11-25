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


class OrderTaxImport
{
    /**
     * @var Sql
     */
    private $sql;

    const ORDERS_FIELDS = [
        'increment_id', 'tax_exemption_id'
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

            $insert = InsertOnDuplicate::create('sales_order', ['entity_id', 'tax_exemption_id'])
                ->onDuplicate(['tax_exemption_id']);

            $select = $this->sql->select('sales_order')->columns(['increment_id', 'entity_id']);
            $orderData = [];
            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $orderData[$row['increment_id']] = $row['entity_id'];
            }

            foreach ($orderLabels as $label) {
                if(isset($orderData[$label['increment_id']])) {
                    $insert->withAssocRow(
                        ['entity_id' => $orderData[$label['increment_id']], 'tax_exemption_id' => str_replace("'","",$label['tax_exemption_id'])]
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
