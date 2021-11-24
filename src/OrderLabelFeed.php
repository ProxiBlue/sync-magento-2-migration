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

class OrderLabelFeed implements Feed
{
    const ORDERS_FIELDS = [
        'increment_id', 'name', 'background_color'
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

        $mainSelect = $this->sql->select(['order' => 'sales_flat_order'])
            ->columns(['increment_id'])
            ->join(
                ['order_flag' => 'magestyapps_orderflags_flag'],
                'order.ma_order_flag = order_flag.flag_id',
                ['name' => 'title','background_color' => 'color']
            )
            ->where('order.ma_order_flag IS NOT NULL');

        foreach ($this->sql->prepareStatementForSqlObject($mainSelect)->execute() as $row) {
            yield $row;
        }

    }
}
