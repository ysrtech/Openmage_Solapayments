<?php
/**
 * Sola gift cards: the applied card and its amounts on quotes, quote addresses, orders,
 * invoices and credit memos (base and store currency; invoiced / refunded totals on orders).
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();
$connection = $installer->getConnection();
$money = ['type' => Varien_Db_Ddl_Table::TYPE_DECIMAL, 'length' => '12,4', 'nullable' => true];
$text = ['type' => Varien_Db_Ddl_Table::TYPE_TEXT, 'length' => 255, 'nullable' => true];

$columns = [
    'sales/quote' => [
        'solapayments_giftcard_code'         => $text + ['comment' => 'Sola gift card number (masked after redemption)'],
        'base_solapayments_giftcard_balance' => $money + ['comment' => 'Balance of the gift card when applied'],
        'base_solapayments_giftcard_amount'  => $money,
        'solapayments_giftcard_amount'       => $money,
    ],
    'sales/quote_address' => [
        'base_solapayments_giftcard_amount' => $money,
        'solapayments_giftcard_amount'      => $money,
    ],
    'sales/order' => [
        'solapayments_giftcard_code'          => $text,
        'solapayments_giftcard_refnum'        => $text + ['comment' => 'gift:redeem transaction'],
        'base_solapayments_giftcard_amount'   => $money,
        'solapayments_giftcard_amount'        => $money,
        'base_solapayments_giftcard_invoiced' => $money,
        'solapayments_giftcard_invoiced'      => $money,
        'base_solapayments_giftcard_refunded' => $money,
        'solapayments_giftcard_refunded'      => $money,
    ],
    'sales/invoice' => [
        'base_solapayments_giftcard_amount' => $money,
        'solapayments_giftcard_amount'      => $money,
    ],
    'sales/creditmemo' => [
        'base_solapayments_giftcard_amount' => $money,
        'solapayments_giftcard_amount'      => $money,
    ],
];
foreach ($columns as $entity => $fields) {
    $table = $installer->getTable($entity);
    foreach ($fields as $name => $definition) {
        if (!$connection->tableColumnExists($table, $name)) {
            $connection->addColumn($table, $name, $definition + ['comment' => $name]);
        }
    }
}
$installer->endSetup();
