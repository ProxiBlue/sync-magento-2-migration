<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class OrderTaxExport
{
    /**
     * @var CsvFactory
     */
    private $csvFactory;
    /**
     * @var ordersFeed
     */
    private $ordersFeed;
    /**
     * @var SelectConditionGenerator
     */
    private $conditionGenerator;


    public function __construct(
        OrderTaxFeed $ordersFeed,
        SelectConditionGenerator $conditionGenerator,
        CsvFactory $csvFactory
    ) {
        $this->csvFactory = $csvFactory;
        $this->ordersFeed = $ordersFeed;
        $this->conditionGenerator = $conditionGenerator;
    }

    public function export(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, $this->ordersFeed::ORDERS_FIELDS);

        foreach ($this->ordersFeed->fetchOrders() as $row) {
            $writer->write($row);
        }
    }

}
