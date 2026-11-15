<?php
/**
 * Saved cards: the Cardknox token (xToken) of a card a customer chose to keep, with what is
 * needed to show it (type, last four digits, expiry). No card numbers are stored.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();
$connection = $installer->getConnection();
$tableName = $installer->getTable('solapayments/card');

if (!$connection->isTableExists($tableName)) {
    $table = $connection->newTable($tableName)
        ->addColumn('card_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
        ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false])
        ->addColumn('token', Varien_Db_Ddl_Table::TYPE_TEXT, 255, ['nullable' => false], 'Cardknox xToken')
        ->addColumn('card_type', Varien_Db_Ddl_Table::TYPE_TEXT, 32, ['nullable' => true], 'Visa, MasterCard, Amex, Discover...')
        ->addColumn('last4', Varien_Db_Ddl_Table::TYPE_TEXT, 4, ['nullable' => true])
        ->addColumn('exp_month', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => true])
        ->addColumn('exp_year', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => true])
        ->addColumn('fingerprint', Varien_Db_Ddl_Table::TYPE_TEXT, 64, ['nullable' => true], 'type|last4|expiry, to avoid saving the same card twice')
        ->addColumn('is_default', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
        ->addColumn('last_used_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => true])
        ->addIndex($installer->getIdxName('solapayments/card', ['customer_id']), ['customer_id'])
        ->addForeignKey(
            $installer->getFkName('solapayments/card', 'customer_id', 'customer/entity', 'entity_id'),
            'customer_id', $installer->getTable('customer/entity'), 'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('Sola Payments saved cards');
    $connection->createTable($table);
}
$installer->endSetup();
