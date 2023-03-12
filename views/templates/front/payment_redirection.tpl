<div class="text-xs-center">
    <h3 id="label">{l s='Redirecting to Geidea ...' mod='geideapay'}</h3>
</div>

<script src="https://www.merchant.geidea.net/hpp/geideapay.min.js"></script>

<script type="text/javascript">


function successCallBack(data) {ldelim}
    console.log('handle successful callback as desired, data', data);
    const parseResult = new DOMParser().parseFromString('{$paymentObject['returnUrl']}', "text/html");
    const returnUrl = parseResult.documentElement.textContent;
    document.location.href = returnUrl;
{rdelim}

function failureCallBack(data) {ldelim}
    console.log('handle failure callback as desired, data', data);
    document.location.href = '{$paymentObject['cancelUrl']}';
{rdelim}

let chargeRequest = {ldelim} {rdelim};
chargeRequest.amount = '{$paymentObject['amount']}';
chargeRequest.currency = '{$paymentObject['currency']}';
chargeRequest.callbackUrl = '{$paymentObject['callbackUrl']}';
chargeRequest.merchantReferenceId = '{$paymentObject['merchantReferenceId']}';
chargeRequest.language = '{$paymentObject['language']}';

chargeRequest.initiatedBy = 'Internet';
chargeRequest.name = 'PrestaShop';
chargeRequest.version = '{ _PS_VERSION_ }';
chargeRequest.pluginVersion = '1.1.1';
chargeRequest.type = 'PrestaShop E-Commerce Platform';
chargeRequest.IntegrationType = 'Plugin';
chargeRequest.partnerId = 'Mimocodes';

let merchantKey = '{$paymentObject['merchantKey']}';
let onSuccess = successCallBack;
let onError = failureCallBack;
let onCancel = failureCallBack;
const payment = new GeideaApi(merchantKey, onSuccess, onError, onCancel);
payment.configurePayment(chargeRequest);
payment.startPayment();

setTimeout(function() {ldelim}
    document.getElementById("label").style.visibility = 'hidden';
{rdelim}, 1000); // <-- time in milliseconds

</script>
