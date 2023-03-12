<?php

class GeideaPayOrderCancelledModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->setTemplate('module:geideapay/views/templates/front/payment_cancel.tpl');

        $status = Configuration::get('PS_OS_CANCELED');
        $id_order =  $_GET['id_order'];
        $order = new Order($id_order); 
        $order->setCurrentState($status);
        $order->update();
        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->changeIdOrderState($status, (int)($order->id));

        $cart = new Cart();
        $cart->id_currency = $this->context->currency->id;
        $cart->id_lang = $this->context->language->id;
        $cart->save();
        $this->context->cart = $cart;

        $product_ids=$_GET['amp;product_ids']; // comma separated products id example : test.php?product_ids=1,2,3
        $product_qts=$_GET['amp;product_qts'];
        $product_attributes=$_GET['amp;product_attributes'];
        $product_ids_array=explode(",",$product_ids);
        $product_qts_array=explode(",",$product_qts);
        $product_attributes_array=explode(",",$product_attributes);

        PrestaShopLogger::addLog(
            'GeideaPay::products ids in cancel order: ' . var_export($product_ids, true),
            1,
            null,
            'GeideaPay Module',
            (int)$this->context->cart->id,
            true
          );

        if(count($product_ids_array)>0){
            for($i=0;$i<count($product_ids_array);$i++){
                $this->context->cart->updateQty($product_qts_array[$i], $product_ids_array[$i], $product_attributes_array[$i]);
            }
        }
        $this->context->cookie->id_cart = $cart->id;
    }
    public function postProcess()
    {

    }
}
