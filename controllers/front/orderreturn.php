<?php

class GeideaPayOrderReturnModuleFrontController extends ModuleFrontController
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:geideapay/views/templates/front/order_return.tpl');
    }
    public function postProcess()
    {
        $cartOrderId = $_GET["cartOrderId"];
        $responseMessage = $_GET["responseMessage"];
        $cartOrderSignature = $_GET["cartOrderSignature"];
        if (empty($cartOrderId) || empty($responseMessage) || empty($cartOrderSignature)) {
            echo "Invalid Request!";
            return;
        }

        $sandbox = Configuration::get('GEIDEA_PAY_SANDBOX', false);
        $merchantApiPassword = trim($sandbox ? Configuration::get('SANDBOX_MERCHANT_API_PASSWORD', '') : Configuration::get('LIVE_MERCHANT_API_PASSWORD', ''));
        $calculatedSignature = hash_hmac('sha256', $cartOrderId, $merchantApiPassword);

        $isValidSignature = hash_equals($calculatedSignature, $cartOrderSignature);

        if (!$isValidSignature) {
            echo "Invalid Signature!";
            return;
        }

        if ($responseMessage == "Success") {
            $cart = Cart::getCartByOrderId($cartOrderId);
            $customer = new Customer((int)($cart->id_customer));
            $returnUrl = 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $cartOrderId . '&key=' . $customer->secure_key;

            Tools::redirect($returnUrl);
        } else {
            $cart = Cart::getCartByOrderId($cartOrderId);

            $products = $cart->getProducts();

            $product_ids = array();
            $product_qts = array();
            foreach ($products as $product) {
                $product_ids[] = (int)$product['id_product'];
                $product_qts[] = $product['cart_quantity'];
                $product_attributes[] = $product['id_product_attribute'];
            }

            $cancelUrl = $this->context->link->getModuleLink(
                $this->module->name,
                'ordercancelled',
                [
                    'id_order' => $cartOrderId,
                    'amp;product_ids' => implode(", ", $product_ids),
                    'amp;product_qts' => implode(", ", $product_qts),
                    'amp;product_attributes' => implode(", ", $product_attributes),
                ],
                $this->ssl
            );

            Tools::redirect($cancelUrl);
        }
    }
}
