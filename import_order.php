<?php

//make sure file place under var/scripts/ directoery 
//you can run command time php import_order.php > import_orders_output.php
//make sure to replace you store view ids here below and total conditions and column values used with your required values 

use \Magento\Framework\App\Bootstrap;
require __DIR__ . '/../../app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$url = \Magento\Framework\App\ObjectManager::getInstance();
$state = $objectManager->get('\Magento\Framework\App\State');
$state->setAreaCode('frontend');
$orderFactory = $objectManager->create('Magento\Sales\Model\OrderFactory');
$orderPaymentRepository = $objectManager->get('Magento\Sales\Api\OrderPaymentRepositoryInterface');
$orderAddressRepository = $objectManager->create('Magento\Sales\Api\Data\OrderAddressInterface');
$productFactory = $objectManager->create('Magento\Catalog\Model\ProductFactory');
$orderItemFactory = $objectManager->create('Magento\Sales\Model\Order\ItemFactory');
$allPaymentMethod = $objectManager->create('Magento\Payment\Helper\Data');
$scopeConfig  = $objectManager->create('Magento\Framework\App\Config\ScopeConfigInterface');
$shippingConfig  = $objectManager->create('Magento\Shipping\Model\Config');
$region = $objectManager->create('Magento\Directory\Model\Region');
$orderFactory = $objectManager->create('Magento\Sales\Model\OrderFactory');

$open = fopen("shopify_orders_eufr.csv", "r");
$head = fgetcsv($open, 4096, ",");
$listOfPaymentMethods =  $allPaymentMethod->getPaymentMethodList();

$carriers = $shippingConfig->getAllCarriers(4);
foreach ($carriers as $carrierCode => $carrierModel) {
    $carrierMethods = $carrierModel->getAllowedMethods();
    if (!$carrierMethods) {
        continue;
    }
    $carrierTitle = $scopeConfig->getValue(
        'carriers/' . $carrierCode . '/title',
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 4
    );
    foreach ($carrierMethods as $methodCode => $methodTitle) {

        /** Check it $carrierMethods array was well formed */
        if (!$methodCode) {
            continue;
        }
        $methods[$carrierCode . '_' . $methodCode] = $carrierTitle .' - '. $methodTitle;
    }
}


$ordersData = [];

while (($data = fgetcsv($open, 4096, ",")) !== FALSE) {
    $ordersData[$data[0]][] = $data;
}
foreach ($ordersData as $id => $val) {

    $orderInfo = $orderFactory->create()->loadByIncrementId($id);
    $orderId = $orderInfo->getId();
    if ($orderId){
        echo "Order IncrementId $id Already Exist with Order Id $orderId".PHP_EOL;
        continue;
    }
    $rate = 0;
    /* @var \Magento\Sales\Model\OrderFactory $orderFactory */
    $order = $orderFactory->create()->setStoreId(4);
    $order->setIncrementId($id);
    //$orderData = $val[$key];
    foreach ($val as $key => $orderData) {
        // This is a great trick, to get an associative row by combining the headrow with the content-rows.
        $column = array_combine($head, $orderData);
        if ($key == 0) {
            $grandTotalExcTax = (int)$column['Subtotal'] + (int)$column['Shipping'];
            $grandTotalIncTax = $column['Total'];
            $rate = 0;
            if ($column['Taxes'] > 0)
                $rate = (($column['Taxes'] * 100) / $grandTotalExcTax);
            $order
                ->setGlobalCurrencyCode($column['Currency'])
                ->setBaseCurrencyCode($column['Currency'])
                ->setStoreCurrencyCode($column['Currency'])
                ->setOrderCurrencyCode($column['Currency']);

            /* @var \Magento\Sales\Api\OrderPaymentRepositoryInterface $orderPaymentRepository */
            $orderPayment = $orderPaymentRepository->create();
            $method = 'checkmo';
            if (in_array($column['Payment Method'], $listOfPaymentMethods)) {
                $method = array_search($column['Payment Method'], $listOfPaymentMethods);
            }
            $orderPayment->setMethod($method);
            $order->setPayment($orderPayment);

            /* @var \Magento\Customer\Model\Customer $customer */
            $order
                ->setCustomerId(null)
                ->setCustomerEmail($column['Email'])
                ->setCustomerFirstname(strtok($column['Shipping Name'], " "))
                ->setCustomerLastname(substr(strstr($column['Shipping Name'], " "), 1))
                ->setCustomerGroupId(1)
                ->setCustomerIsGuest(1);

            $billingStreet =  $column['Billing Street'];
            if($column['Billing Address1'] && $column['Billing Address2'] ) {
                $billingStreet = [
                    $column['Billing Address1'],
                    $column['Billing Address2']
                ];
            }
            $regionBillingId = $region->loadByCode($column['Billing Province'], $column['Billing Country']);

            /* @var \Magento\Sales\Api\Data\OrderAddressInterface $orderAddressRepository */
            $orderBillingAddress = $objectManager->create('Magento\Sales\Api\Data\OrderAddressInterface')
                ->setStoreId(4)
                ->setAddressType('billing') // \Magento\Sales\Model\Order\Address::TYPE_BILLING and then \Magento\Sales\Model\Order\Address::TYPE_SHIPPING
                ->setCustomerId(null)
                ->setPrefix(null)
                ->setFirstname(strtok($column['Billing Name'], " "))
                ->setMiddlename(null)
                ->setLastname(substr(strstr($column['Billing Name'], " "), 1))
                ->setCompany($column['Billing Company'])
                ->setStreet($billingStreet)
                ->setCity($column['Billing City'])
                ->setPostcode($column['Billing Zip'])
                ->setTelephone($column['Billing Phone'])
                ->setFax(null)
                ->setCountryId($column['Billing Country'])
                ->setRegion($regionBillingId->getName())
                ->setRegionId($regionBillingId->getId());
            $order->setBillingAddress($orderBillingAddress);

            $shippingStreet =  $column['Shipping Street'];
            if($column['Shipping Address1'] && $column['Shipping Address2'] ) {
                $shippingStreet = [
                    $column['Shipping Address1'],
                    $column['Shipping Address2']
                ];
            }

            $regionShippingId = $region->loadByCode($column['Shipping Province'], $column['Shipping Country']);
            /* @var \Magento\Sales\Api\Data\OrderAddressInterface $orderAddressRepository */
            $orderShippingAddress = $objectManager->create('Magento\Sales\Api\Data\OrderAddressInterface');
            $orderShippingAddress
                ->setStoreId(4)
                ->setAddressType(\Magento\Sales\Model\Order\Address::TYPE_SHIPPING) // \Magento\Sales\Model\Order\Address::TYPE_BILLING and then \Magento\Sales\Model\Order\Address::TYPE_SHIPPING
                ->setCustomerId(null)
                ->setPrefix(null)
                ->setFirstname(strtok($column['Shipping Name'], " "))
                ->setMiddlename(null)
                ->setLastname(substr(strstr($column['Shipping Name'], " "), 1))
                ->setCompany($column['Shipping Company'])
                ->setStreet($shippingStreet)
                ->setCity($column['Shipping City'])
                ->setPostcode($column['Shipping Zip'])
                ->setTelephone($column['Shipping Phone'])
                ->setFax(null)
                ->setCountryId($column['Shipping Country'])
                ->setRegion($regionShippingId->getName())
                ->setRegionId($regionShippingId->getId());
            $order->setShippingAddress($orderShippingAddress);
            // repeat for shipping address $order->setShippingAddress($orderAddress);

            $shippingMethod = 'flatrate_flatrate';
            $shippingDescription = 'Flat Rate';

            /*if (in_array($column['Shipping Method'], $listOfPaymentMethods)) {
                $shippingMethod = array_search($column['Shipping Method'], $listOfPaymentMethods);
                $shippingDescription = $column['Shipping Method'];
            }*/
            if (isset($column['Shipping Method'])) {
                $shippingDescription = 'Flat Rate';
            }
            $order
                ->setShippingMethod($shippingMethod)
                ->setShippingDescription($shippingDescription);
            $dateTime = $column['Created at'];
            $tz_from = 'UTC';
            $newDateTime = new DateTime($dateTime);
            $newDateTime->setTimezone(new DateTimeZone($tz_from));
            $newDateTime->modify('+5 hours');
            $dateTimeUTC = $newDateTime->format("Y-m-d H:i:s");
            $order->setCreatedAt(strtotime($dateTimeUTC));

            // set status
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING); // depends on your needs
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->addStatusHistoryComment('Good Morning America Order.');
            
            $Subtotal =  isset($column['Subtotal']) && $column['Subtotal'] != '' ? $column['Subtotal'] : 0;
            $Taxes =  isset($column['Taxes']) && $column['Taxes'] != '' ? $column['Taxes'] : 0;
            $Total =  isset($column['Total']) && $column['Total'] != '' ? $column['Total'] : 0;
            $Discount =  isset($column['Discount Amount']) && $column['Discount Amount'] != '' ? $column['Discount Amount'] : 0;

            // set totals
            $order->setBaseGrandTotal($Total );
            $order->setGrandTotal($Total);
            $order->setBaseSubtotal($Subtotal);
            $order->setSubtotal($Subtotal);
            $order->setBaseTaxAmount($Taxes);
            $order->setTaxAmount($Taxes);
            $order->setBaseDiscountAmount($Discount);
            $order->setDiscountAmount($Discount);
            $order->setBaseSubtotalInclTax((float)$Subtotal + ((float) $Subtotal * $rate) / 10);
            $order->setSubtotalInclTax((float) $Subtotal + ((float)$Subtotal * $rate) / 100);
            $order->setTotalItemCount(1);
            $order->setTotalQtyOrdered(1);

            $shippingAmount =  isset($column['Shipping']) && $column['Shipping'] != '' ? $column['Shipping'] : 0;
            // set shipping amounts
            $order->setShippingAmount($shippingAmount);
            $order->setBaseShippingAmount($shippingAmount);
            $order->setShippingTaxAmount(((float) $shippingAmount * $rate) / 100);
            $order->setBaseShippingTaxAmount(((float) $shippingAmount * $rate) / 100);
            $order->setShippingInclTax((float)$shippingAmount + ((float)$shippingAmount * $rate) / 100);
            $order->setBaseShippingInclTax((float)$shippingAmount + ((float)$shippingAmount * $rate) / 100);

            // set total paid if needed
            $order->setTotalPaid($Total);
            $order->setBaseTotalPaid($Total);
        }

        $product = $productFactory->create()->loadByAttribute('sku', $column['Lineitem sku']);

        if (!$product) {
            echo "Error while creating order for ID " . $id . ' Error => ';
            echo "Product does not exists in Magento " . $column['Lineitem sku'] . PHP_EOL;
            continue 2;
            //$product = $productFactory->create()->loadByAttribute('sku', 210000000053);
        }
        // add order item
        /* @var \Magento\Catalog\Model\Product $product */
        /* @var \Magento\Sales\Model\Order\ItemFactory $orderItemFactory */
        $orderItem = $orderItemFactory->create();
        $orderItem
            ->setStoreId(4)
            ->setQuoteItemId(0)
            ->setQuoteParentItemId(NULL)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setQtyBackordered(NULL)
            ->setName($column['Lineitem name'])
            ->setSku($product->getSku())
            ->setTotalQtyOrdered($column['Lineitem quantity'] ?? 1)
            ->setQtyOrdered($column['Lineitem quantity'] ?? 1)
            ->setPrice($column['Lineitem price'] ?? 0)
            ->setBasePrice($column['Lineitem price'] ?? 0)
            ->setOriginalPrice($column['Lineitem price'] ?? 0)
            ->setBaseOriginalPrice($column['Lineitem price'] ?? 0)
            ->setTaxAmount(($column['Lineitem price'] * $rate) / 100)
            ->setBaseTaxAmount(($column['Lineitem price'] * $rate) / 100)
            ->setTaxPercent($rate)
            ->setDiscountAmount($column['Lineitem discount'] ?? 0)
            ->setBaseDiscountAmount($column['Lineitem discount'] ?? 0)
            ->setRowTotal($column['Lineitem price'] * $column['Lineitem quantity'])
            ->setBaseRowTotal($column['Lineitem price'] * $column['Lineitem quantity'])
            ->setWeight(1)
            ->setIsVirtual(0);
        $order->addItem($orderItem);
        $order->setCanSendNewEmailFlag(false);
    }
    try {
        $order->save();
        echo "order created successfully increment ID " . $order->getIncrementId() . PHP_EOL;
    } catch (\Exception $e) {
        echo "Error while creating order for ID " . $id . ' Error => '. $e->getMessage(). PHP_EOL;
    }
}
fclose($open);
echo "script successful";
