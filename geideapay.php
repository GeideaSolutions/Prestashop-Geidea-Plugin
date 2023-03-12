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
   * GeideaPay constructor.
   *
   * Set the information about this module
   */
  public function __construct()
  {
    $this->name                   = 'geideapay';
    $this->tab                    = 'payments_gateways';
    $this->version                = '1.1.1';
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
   * generate a configuration screen on the admin
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
      ->setLogo(_MODULE_DIR_.'geideapay/geidea-logo.png')
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
    Configuration::updateValue('GEIDEA_PAY_ACTIVE', true);
    Configuration::updateValue('GEIDEA_PAY_SANDBOX', true);
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
    );
  }
  public function getConfigForm()
  {
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
            'col'  => 8,
            'type' => 'html',
            'name' => '<hr>',
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
  }
}
