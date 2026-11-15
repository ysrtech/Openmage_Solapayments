<?php

/**
 * My Account > Saved cards: list, remove, make default.
 */
class YSRTech_Solapayments_CardsController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        parent::preDispatch();
        if (!$this->getRequest()->isDispatched()) {
            return;
        }
        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
    }

    public function indexAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('Saved cards'));
        if ($navigation = $this->getLayout()->getBlock('customer_account_navigation')) {
            $navigation->setActive('solapayments/cards');
        }
        $this->renderLayout();
    }

    public function deleteAction()
    {
        $card = $this->_getCard();
        if ($card) {
            $wasDefault = (int) $card->getIsDefault();
            $card->delete();
            if ($wasDefault) {
                $next = Mage::getResourceModel('solapayments/card_collection')
                    ->addCustomerFilter($card->getCustomerId())->setPageSize(1)->getFirstItem();
                if ($next->getId()) {
                    $next->setIsDefault(1)->save();
                }
            }
            Mage::getSingleton('customer/session')->addSuccess($this->__('The card was removed.'));
        }
        $this->_redirect('*/*/');
    }

    public function defaultAction()
    {
        $card = $this->_getCard();
        if ($card) {
            foreach (Mage::getResourceModel('solapayments/card_collection')->addCustomerFilter($card->getCustomerId()) as $other) {
                $other->setIsDefault((int) ($other->getId() == $card->getId()))->save();
            }
            Mage::getSingleton('customer/session')->addSuccess($this->__('Your default card was changed.'));
        }
        $this->_redirect('*/*/');
    }

    /**
     * The posted card, when it belongs to the signed-in customer and the request is valid.
     *
     * @return YSRTech_Solapayments_Model_Card|null
     */
    protected function _getCard()
    {
        if (!$this->getRequest()->isPost() || !$this->_validateFormKey()) {
            Mage::getSingleton('customer/session')->addError($this->__('Your session has expired. Please try again.'));
            return null;
        }
        $card = Mage::getModel('solapayments/card')->load((int) $this->getRequest()->getPost('id'));
        if (!$card->getId() || (int) $card->getCustomerId() !== (int) Mage::getSingleton('customer/session')->getCustomerId()) {
            return null;
        }
        return $card;
    }
}
