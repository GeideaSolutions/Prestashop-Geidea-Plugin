
{if (isset($status) == true) && ($status == 'ok')}
<h3>{l s='Your order is confirmed.' mod='begateway'}</h3>
<p>
	<br />{l s='Amount' mod='begateway'}: <span class="price"><strong>{$total}</strong></span>
	<br />{l s='Reference' mod='begateway'}: <span class="reference"><strong>{$reference}</strong></span>
	<br /><br />{l s='An email has been sent with this information.' mod='begateway'}
	<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='begateway'} <a href="{$link->getPageLink('contact', true)}">{l s='expert customer support team.' mod='begateway'}</a>
</p>
{else}
<h3>{l s='Your order has not been accepted.' mod='begateway'}</h3>
<p>
	<br />- {l s='Reference' mod='begateway'} <span class="reference"> <strong>{$reference}</strong></span>
	<br /><br />{l s='Please, try to order again.' mod='begateway'}
	<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='begateway'} <a href="{$link->getPageLink('contact', true)}">{l s='expert customer support team.' mod='begateway'}</a>
</p>
{/if}
<hr />