<?php
/*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author Mimocodes
*  @copyright  2022 MimoCodes
*/

class GeideaPayCreditcardModuleFrontController extends ModuleFrontController
{
  public $ssl = true;
  public $display_column_left = false;
  public $display_column_right = false;
  public $currentTemplate = 'module:geideapay/views/templates/front/errorpage.tpl';
  public $page_name = 'checkout';
  protected $_transaction;

  public function initHeader()
  {
    $this->context->smarty->assign(array('errors' => array()));
    parent::initHeader();

    $this->setTemplate($this->currentTemplate);
  }

  public function initContent()
  {
    parent::initContent();

    $cart = $this->context->cart;

    if ($cart->nbProducts() <= 0) {
      $this->setErrorTemplate($this->module->l('Your shopping cart is empty'));
    }

    if ($cart->id_customer == 0 || !$this->module->active || Configuration::get('GEIDEA_PAY_ACTIVE', false) === false) {
      Tools::redirect('index.php?controller=order&step=1');
    }

    // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
    $authorized = false;
    foreach (Module::getPaymentModules() as $module)
      if ($module['name'] == 'geideapay') {
        $authorized = true;
        break;
      }

    if (!$authorized) {
      $this->setErrorTemplate($this->module->l('This payment method is not available.'));
      return;
    }

    $sandbox = Configuration::get('GEIDEA_PAY_SANDBOX', false);
    $publicKey = $sandbox ? Configuration::get('SANDBOX_PUBLIC_KEY', '') : Configuration::get('LIVE_PUBLIC_KEY', '');
    $publicKey = trim($publicKey);
    if (isset($publicKey) === true && $publicKey === '') {
      // It's empty
      $this->setErrorTemplate($this->module->l('This payment method is not available.'));
      return;
    }

    $customer      = new Customer((int)($cart->id_customer));
    $amount        = $cart->getOrderTotal(true, 3);
    $orderPrice         = (float)$cart->getOrderTotal(true, Cart::BOTH);

    $module_name = $this->module->displayName;
    $payment_status = Configuration::get('PS_OS_PREPARATION');
    $currency_id = (int) Context::getContext()->currency->id;

    $products = $cart->getProducts();
    $product_ids = array();
    $product_qts = array();
    foreach ($products as $product) 
    {
      $product_ids[] = (int)$product['id_product'];
      $product_qts[] = $product['cart_quantity'];
      $product_attributes[] = $product['id_product_attribute'];
    }

    $orderAdded = $this->module->validateOrder(
      (int) $cart->id,
      $payment_status,
      $amount,
      $module_name,
      "pending till getaway confirmation",
      array(),
      $currency_id,
      false,
      $customer->secure_key
    );

    if (!$orderAdded) {
      echo 'Error in adding order';
      exit;
    }

    $orderId = (int)Order::getIdByCartId((int)$cart->id);

    $returnUrl = 'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module=' .$this->module->id.'&id_order='.$orderId.'&key='.$customer->secure_key;

    $paymentObject = $this->getPaymentObject($product_ids, $product_qts, $product_attributes, $orderId, $orderPrice, $publicKey, $returnUrl);

    PrestaShopLogger::addLog(
      'GeideaPay::initContent::Token request data: ' . var_export($paymentObject, true),
      1,
      null,
      'GeideaPay Module',
      (int)$this->context->cart->id,
      true
    );

    $redirectionURL = $this->context->link->getModuleLink($this->module->name, 'paymentredirection', array('paymentObject' => $paymentObject));
    Tools::redirect($redirectionURL);
  }

  /**
   * @param      $processingReturnCode
   * @param bool $setTemplate
   */
  protected function setErrorTemplate($processingReturnCode, $setTemplate = true)
  {
    if ($setTemplate) {
      $this->setTemplate("module:geideapay/views/templates/front/errorpage.tpl");
    }
    $translation = $this->module->l($processingReturnCode);
    if ($translation === $processingReturnCode) {
      $translation = $this->module->l(
        'An error occurred while processing payment code: ' . $translation
      );
    }
    $this->context->smarty->assign(
      array(
        'errors' => array($translation)
      )
    );
  }


  public function getPaymentObject($product_ids, $product_qts, $product_attributes, $orderId, $orderPrice, $publicKey, $returnUrl)
  {
    global $cookie;
    $currency = new CurrencyCore($cookie->id_currency);
    $currency_iso_code = $currency->iso_code;

    $cancelUrl = $this->context->link->getModuleLink($this->module->name, 'ordercancelled', 
    [
      'id_order' => $orderId, 
      'product_ids' => implode (", ", $product_ids),
      'product_qts' => implode (", ", $product_qts),
      'product_attributes' => implode (", ", $product_attributes),
    ], $this->ssl);
    $callbackUrl = $this->context->link->getModuleLink($this->module->name, 'ordercompleted', array(), $this->ssl);
    //Order info
    $price = round((float)$orderPrice, 2);
    // Language
    $lang = $this->context->language->iso_code;
    //building body
    $paymentObject = [
      "amount" => $price,
      "currency" => $currency_iso_code,
      "merchantReferenceId" => $orderId,
      'returnUrl' => $returnUrl,
      'callbackUrl' => $callbackUrl,
      'cancelUrl' => $cancelUrl,
      'merchantKey' => $publicKey,
      'language' => $lang,
    ];

    return $paymentObject;
  }

  public function getPaymentUrlTest()
  {

    $merchantID = "f7bdf1db-f67e-409b-8fe7-f7ecf9634f70";
    $merchantReferenceId = "123";
    //Order info
    $orderId = "456";
    $price = 115.53;
    $returnUrl  = "?type=return&orderNo=" . $orderId;
    $callbackUrl  = '';
    $cancelUrl  = "?type=cancel&orderNo=" . $orderId;

    $paymentObject = [
      "amount" => $price,
      "currency" => 'EGP',
      "merchantReferenceId" => $merchantReferenceId,
      'returnUrl' => $returnUrl,
      'agreementId' => $orderId,
      'callbackUrl' => $callbackUrl,
      'cancelUrl' => $cancelUrl,
      'merchantKey' => $merchantID,
    ];

    return $paymentObject;
  }
}
