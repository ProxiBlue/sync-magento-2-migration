<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;

class OrderLabelFeedFactory implements FeedFactory
{
    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var RowMapper[]
     */
    private $rowMappers = [];

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public static function createFromAdapter(Adapter $adapter)
    {
        return new self(
            new Sql($adapter)
        );
    }

    /**
     * @return OrderLabelFeed
     */
    public function create(): Feed
    {
        return new OrderLabelFeed($this->sql, $this->rowMappers);
    }

    /**
     * @return $this
     */
    public function withRowMapper(string $feedCode, RowMapper $rowMapper): FeedFactory
    {
        $factory = clone $this;

        $piped = $factory->rowMappers[$feedCode] ?? new CombinedRowMapper();
        $factory->rowMappers[$feedCode] = $piped->pipe($rowMapper);

        return $factory;
    }
}
