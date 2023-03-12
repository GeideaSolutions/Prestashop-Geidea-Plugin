<?php

/**
 * GeideaPay
 *
 * Payment redirection Controller
 *
 *  @author Mimocodes
 *  @copyright  2022 MimoCodes
 */

class GeideapayPaymentRedirectionModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $this->setTemplate('module:geideapay/views/templates/front/payment_redirection.tpl');

        $this->context->smarty->assign('paymentObject',$_GET['paymentObject']);

    }
}
