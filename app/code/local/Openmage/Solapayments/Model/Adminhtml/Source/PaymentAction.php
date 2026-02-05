<?php
/**
 * Sola Payments Extension for Magento 1
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @category   Openmage
 * @package    Openmage_Solapayments
 * @copyright  Copyright (c) 2024 Openmage
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Openmage_Solapayments_Model_Adminhtml_Source_PaymentAction
{
    /**
     * Return list of possible payment action values
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'authorize',
                'label' => Mage::helper('solapayments')->__('Authorize')
            ),
            array(
                'value' => 'authorize_capture',
                'label' => Mage::helper('solapayments')->__('Authorize and Capture')
            )
        );
    }
}
