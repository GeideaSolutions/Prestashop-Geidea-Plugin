<div class="text-xs-center">
     <h3 id="label">{l s='Redirecting to Geidea ...' mod='geideapay'}</h3>
</div>

{if Configuration::get('GEIDEA_ENVIRONMENT') == 'EGY-PROD'}
    <script src="https://www.merchant.geidea.net/hpp/geideaCheckout.min.js"></script>
{elseif Configuration::get('GEIDEA_ENVIRONMENT') == 'KSA-PROD'}
    <script src="https://www.ksamerchant.geidea.net/hpp/geideaCheckout.min.js"></script>
{elseif Configuration::get('GEIDEA_ENVIRONMENT') == 'UAE-PROD'}
    <script src="https://payments.geidea.ae/hpp/geideaCheckout.min.js"></script>
{/if}

{assign var='baseUrl' value=Context::getContext()->shop->getBaseURL(true)}
<script src="{$baseUrl nofilter}modules/geideapay/views/js/script.js"></script>

<script type="text/javascript">

let chargeRequest = { 
  amount : '{$paymentObject['amount']}', 
  currency : '{$paymentObject['currency']}',
  callbackUrl : '{$paymentObject['callbackUrl']}',
  merchantReferenceId : '{$paymentObject['merchantReferenceId']}',
  merchantKey : '{$paymentObject['merchantKey']}',
  initiatedBy : 'Internet',
  name : 'PrestaShop',
  pluginVersion : '3.3.0',
  type : 'PrestaShop E-Commerce Platform',
  IntegrationType : 'Plugin',
  partnerId : 'Mimocodes',
  email:'{$paymentObject['email']}',
  showEmail: '{$paymentObject['showEmail']}' === "1",
  addressEnabled : '{$paymentObject['addressEnabled']}' === "1",
  billingAddress : '{$paymentObject['billingAddress']}',
  shippingAddress : '{$paymentObject['shippingAddress']}',
  showPhone: '{$paymentObject['phoneEnabled']}' === "1",
  receiptEnabled : '{$paymentObject['receiptEnabled']}' === "1",
  phoneNumber : '{$paymentObject['phoneNumber']}',
  headerColor : '{$paymentObject['headerColor']}',
  hideLogoEnabled : '{$paymentObject['hideLogoEnabled']}' === "1",
  hppProfile : '{$paymentObject['hppProfile']}', 
  merchantLogo : '{$paymentObject['merchantLogo']}',
  uploadDir : '{$paymentObject['uploadDir']}',
  returnUrl: '{$paymentObject['returnUrl']}',
  cancelUrl: '{$paymentObject['cancelUrl']}',
  timestamp : '{$paymentObject['timestamp']}', 
  signature : '{$paymentObject['signature']}',
  geideaSession : '{$paymentObject['geideaSession']}',
}

window.paymentObject = {
    returnUrl: '{$paymentObject['returnUrl']}',
    cancelUrl: '{$paymentObject['cancelUrl']}',
  };

const xhr = new XMLHttpRequest();
xhr.open('POST', "{$baseUrl}modules/geideapay/views/templates/front/payment_configuration.php");
xhr.setRequestHeader('Content-Type', 'application/json');
xhr.onload = () => {
  if (xhr.status === 200) {
    const responseBody = JSON.parse(xhr.responseText);
    startV2HPP(responseBody);
  } else {
    console.error('Error:', xhr.statusText);
  }
};
xhr.send(JSON.stringify(chargeRequest));

setTimeout(function() {
  document.getElementById("label").style.visibility = 'hidden';
}, 1000); // <-- time in milliseconds

</script>
