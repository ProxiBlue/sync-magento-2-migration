<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Combine;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;

class OrderTaxFeed implements Feed
{
    const ORDERS_FIELDS = [
        'increment_id', 'tax_exemption_id'
    ];

    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var RowMapper[]
     */
    private $rowMappers;

    public function __construct(Sql $sql, array $rowMappers)
    {
        $this->sql = $sql;
        $this->rowMappers = $rowMappers;
    }

    public function fetchOrders(): iterable
    {

        $mainSelect = $this->sql->select(['payment' => 'sales_flat_order_payment'])
            ->columns(['tax_exemption_id' => 'tax_relief_code'])
            ->join(
                ['order' => 'sales_flat_order'],
                'order.entity_id = payment.parent_id',
                ['increment_id']
            )
            ->where('tax_relief_code IS NOT NULL');

        foreach ($this->sql->prepareStatementForSqlObject($mainSelect)->execute() as $row) {
            yield $row;
        }

    }
}
