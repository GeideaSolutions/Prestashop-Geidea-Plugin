<?php

/**
 * GeideaPay - A Sample Payment Module for PrestaShop 1.7
 *
 * This file is the declaration of the module.
 *
 *  @author Mimocodes
 *  @copyright  2022 MimoCodes
 */
if (!defined('_PS_VERSION_')) {
  exit;
}

class GeideaPay extends PaymentModule
{
  private $_html = '';
  private $_postErrors = array();

  public $address;

  /**
   * GeideaPay constructor
   *
   * Set the information about this module
   */
  public function __construct()
  {
    $this->name                   = 'geideapay';
    $this->tab                    = 'payments_gateways';
    $this->version                = '3.1.0';
    $this->author                 = 'Geidea Solutions';
    $this->controllers            = array('payment', 'validation');
    $this->currencies             = true;
    $this->currencies_mode        = 'checkbox';
    $this->bootstrap              = true;
    $this->displayName            = 'GeideaPay';
    $this->description            = 'Payment module for Geidea payment gateway.';
    $this->confirmUninstall       = 'Are you sure you want to uninstall this module?';
    $this->ps_versions_compliancy = array('min' => '1.6.0', 'max' => _PS_VERSION_);

    parent::__construct();
  }
  /**
   * Install this module and register the following Hooks:
   *
   * @return bool
   */
  public function install()
  {
    $this->preInstall();
    return parent::install()
      && $this->registerHook('paymentOptions')
      && $this->registerHook('paymentReturn');
  }

  /**
   * Uninstall this module and remove it from all hooks
   *
   * @return bool
   */
  public function uninstall()
  {
    $this->preUnInstall();
    return parent::uninstall();
  }
  /**
   * Returns a string containing the HTML necessary to
   * generate a configuration screen on the admin panel
   *
   * @return string
   */
  public function getContent()
  {
    /**
     * If values have been submitted in the form, process.
     */
    if (((bool)Tools::isSubmit('submitGeideaPayModule')) == true) {
      $this->postProcess();
    }

    $this->context->smarty->assign('module_dir', $this->_path);

    $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

    return $output . $this->renderForm();
  }

  /**
   * Display this module as a payment option during the checkout
   *
   * @param array $params
   * @return array|void
   */
  public function hookPaymentOptions($params)
  {
    /*
    * Verify if this module is active
    */
    if (!$this->active) {
      return array();
    }

    if (false == Configuration::get('GEIDEA_PAY_ACTIVE', false)) {
      return array();
    }

    $this->smarty->assign('module_dir', $this->_path);

    /**
     * Form action URL. The form data will be sent to the
     * validation controller when the user finishes
     * the order process.
     */
    $formAction = $this->context->link->getModuleLink($this->name, 'creditcard', array(), true);

    /**
     * Assign the url form action to the template var $action
     */
    $this->smarty->assign(['action' => $formAction]);

    /**
     * Create a PaymentOption object containing the necessary data
     * to display this module in the checkout
     */
    $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
    $newOption->setModuleName($this->displayName)
      ->setCallToActionText(Configuration::get('GEIDEA_PAY_TITLE'))
      ->setLogo(_MODULE_DIR_ . 'geideapay/geidea-logo.png')
      ->setAction($formAction);
    //->setForm($paymentForm);

    $payment_options = array(
      $newOption
    );

    return $payment_options;
  }

  /**
   * Display a message in the paymentReturn hook
   * 
   * @param array $params
   * @return string
   */
  public function hookPaymentReturn($params)
  {
    /**
     * Verify if this module is enabled
     */
    if (!$this->active) {
      return;
    }

    return $this->fetch('module:geideapay/views/templates/hook/payment_return.tpl');
  }


  /******************************************************************** */

  public function hookBackOfficeHeader()
  {
    if (Tools::getValue('configure') == $this->name) {
      $this->context->controller->addJS($this->_path . 'views/js/back.js');
    }
  }

  public function preInstall()
  {

    fopen(_PS_ROOT_DIR_ . '/geidea.log', 'w');
    Configuration::updateValue('GEIDEA_PAY_TITLE', 'Pay by credit card using Geidea');
    Configuration::updateValue('SANDBOX_MERCHANT_API_PASSWORD', '');
    Configuration::updateValue('LIVE_MERCHANT_API_PASSWORD', '');
    Configuration::updateValue('SANDBOX_PUBLIC_KEY', '');
    Configuration::updateValue('LIVE_PUBLIC_KEY', '');
    Configuration::updateValue('GEIDEA_PAY_ACTIVE', false);
    Configuration::updateValue('GEIDEA_PAY_SANDBOX', false);
    Configuration::updateValue('GEIDEA_PAY_ENABLE_EMAIL', false);
    Configuration::updateValue('GEIDEA_PAY_ENABLE_ADDRESS', false);
    Configuration::updateValue('GEIDEA_PAY_ENABLE_PHONE', false);
    Configuration::updateValue('GEIDEA_PAY_ENABLE_RECEIPT', false);
    Configuration::updateValue('GEIDEA_PAY_HEADER_COLOR', '');
    Configuration::updateValue('GEIDEA_PAY_HIDE_LOGO', false);
    Configuration::updateValue('GEIDEA_PAY_HPP_PROFILE', 'Simple');
    Configuration::updateValue('GEIDEA_PAY_IMAGE', false);
    Configuration::updateValue('GEIDEA_AWAITING_PAYMENT', $this->createCustomPaymentStatus());
    Configuration::updateValue('GEIDEA_ACCEPTED_PAYMENT', Configuration::get('PS_OS_PAYMENT'));
    Configuration::updateValue('GEIDEA_ENVIRONMENT', 'EGY-PROD');
  }
  private function createCustomPaymentStatus()
  {
    $status_name = 'Awaiting payment';
    $status_color = '#34209E';
    $orderStatusId = $this->getOrderStatusIdByName($status_name);
    if ($orderStatusId) {
      return $orderStatusId;
    }

    $languages = Language::getLanguages();
    $statuses = array();

    foreach ($languages as $lang) {
      $statuses[$lang['id_lang']] = $status_name;
    }

    $orderState = new OrderState();
    $orderState->name = $statuses;
    $orderState->send_email = false;
    $orderState->color = $status_color;
    $orderState->unremovable = true;
    $orderState->hidden = false;
    $orderState->logable = true;

    if (!$orderState->add()) {
      return Configuration::get('PS_OS_PREPARATION');
    }

    return (int)$orderState->id;
  }
  function getOrderStatusIdByName($statusName)
  {
    $orderStatuses = OrderState::getOrderStates($this->context->language->id);
    foreach ($orderStatuses as $orderStatus) {
      if ($orderStatus['name'] === $statusName) {
        return $orderStatus['id_order_state'];
      }
    }
    return false;
  }

  public function preUnInstall()
  {
    Configuration::deleteByName('GEIDEA_PAY_TITLE');
    Configuration::deleteByName('SANDBOX_MERCHANT_API_PASSWORD');
    Configuration::deleteByName('LIVE_MERCHANT_API_PASSWORD');
    Configuration::deleteByName('SANDBOX_PUBLIC_KEY');
    Configuration::deleteByName('LIVE_PUBLIC_KEY');
    Configuration::deleteByName('GEIDEA_PAY_ACTIVE');
    Configuration::deleteByName('GEIDEA_PAY_SANDBOX');
    Configuration::deleteByName('GEIDEA_PAY_ENABLE_EMAIL');
    Configuration::deleteByName('GEIDEA_PAY_ENABLE_ADDRESS');
    Configuration::deleteByName('GEIDEA_PAY_ENABLE_PHONE');
    Configuration::deleteByName('GEIDEA_PAY_ENABLE_RECEIPT');
    Configuration::deleteByName('GEIDEA_PAY_HEADER_COLOR');
    Configuration::deleteByName('GEIDEA_PAY_HIDE_LOGO');
    Configuration::deleteByName('GEIDEA_PAY_HPP_PROFILE');
    Configuration::deleteByName('GEIDEA_PAY_IMAGE');
    Configuration::deleteByName('GEIDEA_AWAITING_PAYMENT');
    Configuration::deleteByName('GEIDEA_ACCEPTED_PAYMENT');
    Configuration::deleteByName('GEIDEA_ENVIRONMENT');
  }
  public function renderForm()
  {

    $helper = new HelperForm();

    $helper->show_toolbar             = false;
    $helper->table                    = $this->table;
    $helper->module                   = $this;
    $helper->default_form_language    = $this->context->language->id;
    $helper->identifier               = $this->identifier;
    $helper->submit_action            = 'submitGeideaPayModule';

    $helper->currentIndex  = $this->context->link->getAdminLink('AdminModules', false)
      . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
    $helper->token         = Tools::getAdminTokenLite('AdminModules');

    $helper->tpl_vars = array(
      'fields_value' => $this->getConfigFieldsValues(),
    );

    return $helper->generateForm(array($this->getConfigForm()));
  }
  public function getConfigFieldsValues()
  {
    return array(
      'GEIDEA_PAY_ACTIVE' => Tools::getValue('GEIDEA_PAY_ACTIVE', Configuration::get('GEIDEA_PAY_ACTIVE', true)),
      'GEIDEA_PAY_TITLE' => Tools::getValue('GEIDEA_PAY_TITLE', Configuration::get('GEIDEA_PAY_TITLE', '')),
      'SANDBOX_MERCHANT_API_PASSWORD' => Tools::getValue('SANDBOX_MERCHANT_API_PASSWORD', Configuration::get('SANDBOX_MERCHANT_API_PASSWORD', '')),
      'LIVE_MERCHANT_API_PASSWORD' => Tools::getValue('LIVE_MERCHANT_API_PASSWORD', Configuration::get('LIVE_MERCHANT_API_PASSWORD', '')),
      'SANDBOX_PUBLIC_KEY' => Tools::getValue('SANDBOX_PUBLIC_KEY', Configuration::get('SANDBOX_PUBLIC_KEY', '')),
      'LIVE_PUBLIC_KEY' => Tools::getValue('LIVE_PUBLIC_KEY', Configuration::get('LIVE_PUBLIC_KEY', '')),
      'GEIDEA_PAY_SANDBOX' => Tools::getValue('GEIDEA_PAY_SANDBOX', Configuration::get('GEIDEA_PAY_SANDBOX', true)),
      'GEIDEA_PAY_ENABLE_EMAIL' => Tools::getValue('GEIDEA_PAY_ENABLE_EMAIL', Configuration::get('GEIDEA_PAY_ENABLE_EMAIL', true)),
      'GEIDEA_PAY_ENABLE_ADDRESS' => Tools::getValue('GEIDEA_PAY_ENABLE_ADDRESS', Configuration::get('GEIDEA_PAY_ENABLE_ADDRESS', true)),
      'GEIDEA_PAY_ENABLE_PHONE' => Tools::getValue('GEIDEA_PAY_ENABLE_PHONE', Configuration::get('GEIDEA_PAY_ENABLE_PHONE', true)),
      'GEIDEA_PAY_ENABLE_RECEIPT' => Tools::getValue('GEIDEA_PAY_ENABLE_RECEIPT', Configuration::get('GEIDEA_PAY_ENABLE_RECEIPT', true)),
      'GEIDEA_PAY_HEADER_COLOR' => Tools::getValue('GEIDEA_PAY_HEADER_COLOR', Configuration::get('GEIDEA_PAY_HEADER_COLOR', '')),
      'GEIDEA_PAY_HIDE_LOGO' => Tools::getValue('GEIDEA_PAY_HIDE_LOGO', Configuration::get('GEIDEA_PAY_HIDE_LOGO', true)),
      'GEIDEA_PAY_HPP_PROFILE' => Tools::getValue('GEIDEA_PAY_HPP_PROFILE', Configuration::get('GEIDEA_PAY_HPP_PROFILE', 'Simple')),
      'GEIDEA_PAY_IMAGE' => Tools::getValue('GEIDEA_PAY_IMAGE', Configuration::get('GEIDEA_PAY_IMAGE', '')),
      'GEIDEA_AWAITING_PAYMENT' => Tools::getValue('GEIDEA_AWAITING_PAYMENT', Configuration::get('GEIDEA_AWAITING_PAYMENT')),
      'GEIDEA_ACCEPTED_PAYMENT' => Tools::getValue('GEIDEA_ACCEPTED_PAYMENT', Configuration::get('GEIDEA_ACCEPTED_PAYMENT')),
      'GEIDEA_ENVIRONMENT' => Tools::getValue('GEIDEA_ENVIRONMENT', Configuration::get('GEIDEA_ENVIRONMENT', 'EGY-PROD')),
    );
  }
  private function getAwaitingPaymentOrderStatuses()
  {
    $orderStatuses = OrderState::getOrderStates($this->context->language->id);
    $statusesArray = array();

    foreach ($orderStatuses as $status) {
      if ($status['id_order_state'] === Configuration::get('GEIDEA_AWAITING_PAYMENT')) {
        $statusesArray[] = array(
          'id_option' => $status['id_order_state'],
          'name' => $status['name'],
          'selected' => true,
        );
      } else {
        $statusesArray[] = array(
          'id_option' => $status['id_order_state'],
          'name' => $status['name'],
        );
      }
    }

    return $statusesArray;
  }
  private function getAcceptedPaymentOrderStatuses()
  {
    $orderStatuses = OrderState::getOrderStates($this->context->language->id);
    $statusesArray = array();

    foreach ($orderStatuses as $status) {
      if ($status['id_order_state'] === Configuration::get('PS_OS_PAYMENT')) {
        $statusesArray[] = array(
          'id_option' => $status['id_order_state'],
          'name' => $status['name'],
          'selected' => true,
        );
      } else {
        $statusesArray[] = array(
          'id_option' => $status['id_order_state'],
          'name' => $status['name'],
        );
      }
    }

    return $statusesArray;
  }
  public function getConfigForm()
  {
    $awaitingPaymenteOrderStatus = $this->getAwaitingPaymentOrderStatuses();
    $acceptedPaymentOrderStatus = $this->getAcceptedPaymentOrderStatuses();
    return array(
      'form' => array(
        'legend' => array(
          'title' => $this->l('Settings'),
          'icon'  => 'icon-cogs'
        ),
        'input'  => array(
          array(
            'type'    => 'switch',
            'label'   => $this->l('Active'),
            'name'    => 'GEIDEA_PAY_ACTIVE',
            'is_bool' => true,
            'values'  => array(
              array(
                'id'    => 'active_on',
                'value' => true,
                'label' => $this->l('Enabled')
              ),
              array(
                'id'    => 'active_off',
                'value' => false,
                'label' => $this->l('Disabled')
              )
            ),
          ),
          array(
            'type' => 'select',
            'label' => $this->l('Environment'),
            'name' => 'GEIDEA_ENVIRONMENT',
            'options' => array(
              'query' => array(
                array(
                  'id_option' => 'EGY-PROD',
                  'name' => 'EGY-PROD',
                  'selected' => true
                ),
                array(
                  'id_option' => 'KSA-PROD',
                  'name' => 'KSA-PROD'
                ),
                array(
                  'id_option' => 'UAE-PROD',
                  'name' => 'UAE-PROD'
                )
              ),
              'id' => 'id_option',
              'name' => 'name'
            ),
            'required' => true
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Title'),
            'name' => 'GEIDEA_PAY_TITLE',
            'required' => true
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Mode'),
            'name' => 'GEIDEA_PAY_SANDBOX',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Sandbox')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Live')
              )
            )
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Enable Receipt'),
            'name' => 'GEIDEA_PAY_ENABLE_RECEIPT',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Receipt Enabled')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Receipt Disabled')
              )
            )
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Enable Email'),
            'name' => 'GEIDEA_PAY_ENABLE_EMAIL',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Email Enabled')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Email Disabled')
              )
            )
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Enable Phone Number'),
            'name' => 'GEIDEA_PAY_ENABLE_PHONE',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('PhoneNumber Enabled')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('PhoneNumber Disabled')
              )
            )
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Enable Address'),
            'name' => 'GEIDEA_PAY_ENABLE_ADDRESS',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('Address Enabled')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('Address Disabled')
              )
            )
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Hide Geidea Logo'),
            'name' => 'GEIDEA_PAY_HIDE_LOGO',
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => true,
                'label' => $this->l('HideLogo Enabled')
              ),
              array(
                'id' => 'active_off',
                'value' => false,
                'label' => $this->l('HideLogo Disabled')
              )
            )
          ),
          array(
            'type' => 'select',
            'label' => $this->l('Hosted Payment Page Style'),
            'name' => 'GEIDEA_PAY_HPP_PROFILE',
            'options' => array(
              'query' => array(
                array(
                  'id_option' => 'Simple',
                  'name' => 'Simple',
                  'selected' => true
                ),
                array(
                  'id_option' => 'Compressed',
                  'name' => 'Compressed'
                )
              ),
              'id' => 'id_option',
              'name' => 'name'
            )
          ),
          array(
            'col'  => 8,
            'type' => 'html',
            'name' => '<hr>',
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Header Color'),
            'name' => 'GEIDEA_PAY_HEADER_COLOR',
            'default' => '',
          ),
          array(
            'type' => 'file',
            'label' => $this->l('Upload Image'),
            'desc' => $this->l('Allowed file types: jpg, jpeg, png, svg'),
            'name' => 'GEIDEA_PAY_IMAGE',
            'display_image' => true,
            'allowed_file_types' => array('image/jpeg', 'image/png', 'image/svg+xml', 'image/jpg')
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Sandbox Merchant API Password'),
            'name' => 'SANDBOX_MERCHANT_API_PASSWORD',
            'required' => true
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Sandbox Merchant Public Key'),
            'name' => 'SANDBOX_PUBLIC_KEY',
            'required' => true
          ),
          array(
            'col'  => 8,
            'type' => 'html',
            'name' => '<hr>',
          ),
          array(
            'type' => 'select',
            'label' => $this->l('Order status while waiting for payment'),
            'name' => 'GEIDEA_AWAITING_PAYMENT',
            'options' => array(
              'query' => $awaitingPaymenteOrderStatus,
              'id' => 'id_option',
              'name' => 'name'
            ),
            'required' => true
          ),
          array(
            'type' => 'select',
            'label' => $this->l('Order status after success payment'),
            'name' => 'GEIDEA_ACCEPTED_PAYMENT',
            'options' => array(
              'query' => $acceptedPaymentOrderStatus,
              'id' => 'id_option',
              'name' => 'name'
            ),
            'required' => true
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Production Merchant API Password'),
            'name' => 'LIVE_MERCHANT_API_PASSWORD',
            'required' => true
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Production Merchant Public Key'),
            'name' => 'LIVE_PUBLIC_KEY',
            'required' => true
          ),
        ),
        'submit' => array(
          'title' => $this->l('Save')
        )
      )
    );
  }


  protected function postProcess()
  {
    $form_values = $this->getConfigFieldsValues();

    foreach (array_keys($form_values) as $key) {
      $value = Tools::getValue($key);
      Configuration::updateValue($key, trim($value));
    }

    // Define the upload directory
    $uploadDir =  _PS_MODULE_DIR_ . 'geideapay/image/';
    if (!file_exists($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    if (!empty($_FILES['GEIDEA_PAY_IMAGE']['name'])) {
      $file = $_FILES['GEIDEA_PAY_IMAGE'];
      $fileName = basename($file['name']);
      $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
      $allowedExtensions = array('jpg', 'jpeg', 'png', 'svg');

      if (!in_array($fileExtension, $allowedExtensions)) {
        die('Error: Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions));
      }

      // Move the file to the upload directory
      $uploadFile = $uploadDir . $fileName;
      if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
        die('Error: Failed to move uploaded file.');
      }
    }

    $files = array_diff(scandir($uploadDir), array('.', '..'));

    $allowedExtensions = array('jpg', 'jpeg', 'png', 'svg');
    $imageFiles = array_filter($files, function ($file) use ($allowedExtensions) {
      $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      return in_array($extension, $allowedExtensions);
    });

    $sortedImageFiles = array_values($imageFiles);
    usort($sortedImageFiles, function ($a, $b) use ($uploadDir) {
      $aModifiedTime = filemtime($uploadDir . $a);
      $bModifiedTime = filemtime($uploadDir . $b);
      return $bModifiedTime - $aModifiedTime;
    });

    $lastUploadedImageRelativePath = !empty($sortedImageFiles) ? $sortedImageFiles[0] : null;
    Configuration::updateValue('GEIDEA_PAY_IMAGE', $lastUploadedImageRelativePath);
  }
}
