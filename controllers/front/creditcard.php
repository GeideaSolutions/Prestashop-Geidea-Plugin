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
  protected $merchantApiPassword;

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

    $merchantApiPassword = $sandbox ? Configuration::get('SANDBOX_MERCHANT_API_PASSWORD', '') : Configuration::get('LIVE_MERCHANT_API_PASSWORD', '');
    $merchantApiPassword = trim($merchantApiPassword);
    $this->merchantApiPassword = $sandbox ? Configuration::get('SANDBOX_MERCHANT_API_PASSWORD', '') : Configuration::get('LIVE_MERCHANT_API_PASSWORD', '');
    $this->merchantApiPassword = trim($this->merchantApiPassword);
    if (session_status() == PHP_SESSION_NONE) {
      session_start();
    }

    // Set the merchantApiPassword in session
    $_SESSION['merchantApiPassword'] = $this->merchantApiPassword;
    $_SESSION['geideaEnvironment'] = Configuration::get('GEIDEA_ENVIRONMENT');

    // Include payment_configuration.php
    $this->includePaymentConfiguration();


    $emailEnabled = Configuration::get('GEIDEA_PAY_ENABLE_EMAIL', false);
    $receiptEnabled = Configuration::get('GEIDEA_PAY_ENABLE_RECEIPT', false);
    $phoneEnabled = Configuration::get('GEIDEA_PAY_ENABLE_PHONE', false);
    $addressEnabled = Configuration::get('GEIDEA_PAY_ENABLE_ADDRESS', false);
    $headerColor = Configuration::get('GEIDEA_PAY_HEADER_COLOR', '');
    $hideLogoEnabled = Configuration::get('GEIDEA_PAY_HIDE_LOGO', false);
    $hppProfile = Configuration::get('GEIDEA_PAY_HPP_PROFILE', 'Simple');
    //for merchantlogo 
    $baseUrl = Context::getContext()->shop->getBaseURL(true);
    $uploadDir = $baseUrl . 'modules/geideapay/image/';
    $merchantLogoUrl = Configuration::get('GEIDEA_PAY_IMAGE');
    $merchantLogo =  $uploadDir . $merchantLogoUrl;

    if (isset($publicKey) === true && $publicKey === '') {
      // It's empty
      $this->setErrorTemplate($this->module->l('This payment method is not available.'));
      return;
    }

    $customer = new Customer((int)($cart->id_customer));
    $billingEmail = $customer->email;

    // Get the current context
    $context = Context::getContext();
    // Get the customer's billing address
    $billingAddress = new Address($context->cart->id_address_invoice);
    $billing_address_formatted = array(
      'first_name' => $billingAddress->firstname,
      'last_name' => $billingAddress->lastname,
      'billing_address' => $billingAddress->address1,
      'city' => $billingAddress->city,
      'postcode' => $billingAddress->postcode,
      'phone' => $billingAddress->phone,
    );

    // Get the customer's shipping address
    $shippingAddress = new Address($context->cart->id_address_delivery);
    $shipping_address_formatted = array(
      'first_name' => $shippingAddress->firstname,
      'last_name' => $shippingAddress->lastname,
      'billing_address' => $shippingAddress->address1,
      'city' => $shippingAddress->city,
      'postcode' => $shippingAddress->postcode,
      'phone' => $shippingAddress->phone,
    );

    $billing_Address = json_encode($billing_address_formatted);
    $shipping_Address = json_encode($shipping_address_formatted);
    $phoneNumber = $billing_address_formatted['phone'];

    $amount = $cart->getOrderTotal(true, 3);
    $orderPrice  = (float)$cart->getOrderTotal(true, Cart::BOTH);

    $module_name = $this->module->displayName;
    $payment_status = Configuration::get('GEIDEA_AWAITING_PAYMENT');
    $currency_id = (int) Context::getContext()->currency->id;


    $products = $cart->getProducts();
    $product_ids = array();
    $product_qts = array();
    foreach ($products as $product) {
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

    $returnUrl = 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $orderId . '&key=' . $customer->secure_key;

    $paymentObject = $this->getPaymentObject($product_ids, $product_qts, $product_attributes, $orderId, $orderPrice, $publicKey, $returnUrl, $emailEnabled, $billingEmail, $merchantApiPassword, $addressEnabled, $billing_Address, $shipping_Address, $phoneNumber, $phoneEnabled, $receiptEnabled, $headerColor, $hideLogoEnabled, $hppProfile, $merchantLogo, $uploadDir);

    PrestaShopLogger::addLog(
      'GeideaPay::initContent::Token request data: ' . var_export($paymentObject, true),
      1,
      null,
      'GeideaPay',
      (int)$this->context->cart->id,
      true
    );

    $redirectionURL = $this->context->link->getModuleLink($this->module->name, 'paymentredirection', array('paymentObject' => $paymentObject));
    Tools::redirect($redirectionURL);
  }

  protected function includePaymentConfiguration()
  {
    $baseUrl = Context::getContext()->shop->getBaseURL(true);
    $paymentConfigUrl = $baseUrl . 'module:geideapay/views/templates/front/payment_configuration.php';
    include($paymentConfigUrl);
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


  public function getPaymentObject($product_ids, $product_qts, $product_attributes, $orderId, $orderPrice, $publicKey, $returnUrl, $emailEnabled, $billingEmail, $merchantApiPassword, $addressEnabled, $billing_Address, $shipping_Address, $phoneNumber, $phoneEnabled, $receiptEnabled, $headerColor, $hideLogoEnabled, $hppProfile, $merchantLogo, $uploadDir)
  {
    global $cookie;
    $currency = new CurrencyCore($cookie->id_currency);
    $currency_iso_code = $currency->iso_code;

    $cancelUrl = $this->context->link->getModuleLink(
      $this->module->name,
      'ordercancelled',
      [
        'id_order' => $orderId,
        'product_ids' => implode(", ", $product_ids),
        'product_qts' => implode(", ", $product_qts),
        'product_attributes' => implode(", ", $product_attributes),
      ],
      $this->ssl
    );
    $callbackUrl = $this->context->link->getModuleLink($this->module->name, 'ordercompleted', array(), $this->ssl);
    //Order info
    $price = round((float)$orderPrice, 2);
    // Language
    $lang = $this->context->language->iso_code;

    $timestamp =  date("n/d/Y g:i:s A");
    $signature = $this->generateSignature($publicKey, $price, $currency_iso_code, $orderId === '' ? null : $orderId, $merchantApiPassword, $timestamp);

    $getGeideaSession = $this->createSession($publicKey, $merchantApiPassword, $callbackUrl, $price, $currency_iso_code, $timestamp, $orderId, $billingEmail, $phoneNumber, $billing_Address, $shipping_Address, $merchantLogo, $uploadDir, $addressEnabled, $emailEnabled, $phoneEnabled, $receiptEnabled, $hideLogoEnabled, $headerColor, $hppProfile, $signature);
    $geideaSession =   urlencode($getGeideaSession);

    //building body
    $paymentObject = [
      "amount" => $price,
      "currency" => $currency_iso_code,
      "merchantReferenceId" => $orderId,
      'returnUrl' => $returnUrl,
      'callbackUrl' => $callbackUrl,
      'cancelUrl' => $cancelUrl,
      'merchantKey' => $publicKey,
      'showEmail' => $emailEnabled,
      'email' => $billingEmail,
      'addressEnabled' => $addressEnabled,
      'billingAddress' => $billing_Address,
      'shippingAddress' => $shipping_Address,
      'phoneNumber' => $phoneNumber,
      'phoneEnabled' => $phoneEnabled,
      'receiptEnabled' => $receiptEnabled,
      'headerColor' => $headerColor,
      'hideLogoEnabled' => $hideLogoEnabled,
      'hppProfile' => $hppProfile,
      'merchantLogo' => $merchantLogo,
      'uploadDir' => $uploadDir,
      'timestamp' => $timestamp,
      'signature' => $signature,
      'geideaSession' => $geideaSession,
    ];

    return $paymentObject;
  }

  public function generateSignature($merchantPublicKey, $orderAmount, $orderCurrency, $orderMerchantReferenceId, $apiPassword, $timestamp)
  {
    $amountStr = number_format($orderAmount, 2, '.', '');
    $data = "{$merchantPublicKey}{$amountStr}{$orderCurrency}{$orderMerchantReferenceId}{$timestamp}";
    $hash = hash_hmac('sha256', $data, $apiPassword, true);
    return base64_encode($hash);
  }

  public function createSession($publicKey, $merchantApiPassword, $callbackUrl, $price, $currency_iso_code, $timestamp, $orderId, $billingEmail, $phoneNumber, $billing_Address, $shipping_Address, $merchantLogo, $uploadDir, $addressEnabled, $emailEnabled, $phoneEnabled, $receiptEnabled, $hideLogoEnabled, $headerColor, $hppProfile, $signature)
  {
    $iframeConfiguration = array(
      'merchantPublicKey' => $publicKey,
      'apiPassword' =>  $merchantApiPassword,
      'callbackUrl' => $callbackUrl,
      'amount' => number_format($price, 2, '.', ''),
      'currency' => $currency_iso_code,
      'language' => 'en',
      'timestamp' => $timestamp,
      'merchantReferenceId' => ((string)$orderId === '') ? null : (string)$orderId,
      'paymentIntentId' => null,
      'paymentOperation' => 'Pay',
      'cardOnFile' => false,
      'initiatedBy' => 'Internet',
      'customer' => array(
        'create' => false,
        'setDefaultMethod' => false,
        'email' => ($billingEmail === '') ? null : $billingEmail,
        'phoneNumber' => ($phoneNumber === '') ? null : $phoneNumber,
        'address' => array(
          'billing' => json_decode(str_replace('&quot;', '"', $billing_Address), true),
          'shipping' => json_decode(str_replace('&quot;', '"',  $shipping_Address), true),
        ),
      ),
      'appearance' => array(
        'merchant' => array(
          'logoUrl' => ($merchantLogo === $uploadDir) ? null : $merchantLogo,
        ),
        'showAddress' => $addressEnabled == '1' ? true : false,
        'showEmail' => $emailEnabled == '1' ? true : false,
        'showPhone' => $phoneEnabled == '1' ? true : false,
        'receiptPage' => $receiptEnabled == '1' ? true : false,
        'styles' => array(
          'hideGeideaLogo' => $hideLogoEnabled == '1' ? true : false,
          'headerColor' => ($headerColor === '') ? null : $headerColor,

          'hppProfile' => $hppProfile,
        ),
        'uiMode' => 'modal',
      ),
      'order' => array(
        'integrationType' => 'Plugin',
      ),
      'platform' => array(
        'name' => 'Prestashop',
        'pluginVersion' => '3.2.0',
        'partnerId' => 'Mimocodes',
      ),
      'signature' => $signature,
    );
    $geideaEnvironment = Configuration::get('GEIDEA_ENVIRONMENT');
    if ($geideaEnvironment === 'EGY-PROD') {
      $createSessionUrl = 'https://api.merchant.geidea.net/payment-intent/api/v2/direct/session';
    } elseif ($geideaEnvironment === 'KSA-PROD') {
      $createSessionUrl = 'https://api.ksamerchant.geidea.net/payment-intent/api/v2/direct/session';
    } elseif ($geideaEnvironment === 'UAE-PROD') {
      $createSessionUrl = 'https://api.geidea.ae/payment-intent/api/v2/direct/session';
    }
    $iframeConfigurationJson = $iframeConfiguration;
    $response = $this->sendGiRequest(
      $createSessionUrl,
      $publicKey,
      $merchantApiPassword,
      $iframeConfigurationJson
    );
    return $response;
  }

  public function sendGiRequest($gatewayUrl, $merchantKey, $password, $values, $method = 'POST')
  {
    $origString = $merchantKey . ":" . $password;
    $authKey = base64_encode($origString);
    $postParams = $values;

    $headers = array(
      'Authorization: Basic ' . $authKey,
      'Content-Type: application/json',
      'Accept: application/json',
    );
    $curl = curl_init($gatewayUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postParams));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
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
