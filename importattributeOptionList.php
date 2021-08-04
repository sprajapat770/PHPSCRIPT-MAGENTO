<?php
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$entityType = 'catalog_product';
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('adminhtml');
$registry = $objectManager->get('Magento\Framework\Registry');
$registry->register('isSecureArea', true);
$eavConfig = $objectManager->get('\Magento\Eav\Model\ConfigFactory');
$eavSetup = $objectManager->get('\Magento\Eav\Setup\EavSetupFactory');

$attributeValueList = fopen(__DIR__ . "/range.csv", "r");

while (($row = fgetcsv($attributeValueList))!==false) {

    $attributeCode = "range";

    $attribute = $eavConfig->create()->getAttribute($entityType, $attributeCode)->setStoreId(0);
    $option = [];
    $option['attribute_id'] = $attribute->getAttributeId();
    $options = $attribute->getSource()->getAllOptions();
    $optval = [];
    $str = '"' . trim($row[0]) . '"';
    $optionsToRemove = [];

    foreach($options as $opt)
    {
        //insert options
        $optval[] = $opt['label'];

        //delete all options
        /*$optionsToRemove['delete'][$opt['value']] = true;
          $optionsToRemove['value'][$opt['value']] = true;*/
    }

    if (!in_array(trim($row[0]),$optval)) {
        $option['value'][$str][0] = str_replace('"', '', $str);
    }
    //insert options
    $eavSetup->create()->addAttributeOption($option);
    //delete options
    //$eavSetup->create()->addAttributeOption($optionsToRemove);
}

echo "Attribute option values has been updated successfully";
fclose($attributeValueList);
