<?php

//make sure file place under var/scripts/ directoery 
//you can run command time php sync_order_grid_for_missing_orders.php > missing_orders_output.php

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/../../app/bootstrap.php';

error_reporting(E_ALL & ~E_NOTICE);

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/**
 * Get objects from object manager
 */
$gridPool = $objectManager->get(\Magento\Sales\Model\ResourceModel\GridPool::class);
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

/**
 * Get Tables
 */
$baseTable = "{$prefix}sales_order";
$gridTable = "{$prefix}sales_order_grid";

/**
 * Get orders we need to refresh
 */
$entitiesToRefresh = $connection->fetchAll("SELECT entity_id FROM {$baseTable} WHERE entity_id NOT IN (SELECT entity_id FROM {$gridTable}) AND created_at < timestamp(DATE_SUB(NOW(), INTERVAL 30 MINUTE))");

/**
 * Refresh orders
 */
foreach ($entitiesToRefresh as $entity) {
    $orderId = $order['entity_id'];
  
    echo "Refreshing Order: entity_id = $orderId", PHP_EOL;
    $gridPool->refreshByOrderId($orderId);
}
