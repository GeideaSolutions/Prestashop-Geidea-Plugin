{extends file='checkout/checkout.tpl'}
{block name="content"}
    <section id="content">
        <div class="row">
            <div class="col-md-12">
                <section id="checkout-payment-step" class="checkout-step -current -reachable js-current-step">
                    <h1 class="text-xs-center">{l s='Payment' mod='geideapay'}</h1>
                    {include file="module:geideapay/views/templates/front/error.tpl"}

                    <a href="{$link->getPageLink('order', true, NULL, "step=3")}"
                       class="btn btn-default">
                        {l s='Other payment methods' mod='geideapay'}
                    </a>
                </section>
            </div>
        </div>
    </section>
{/block}
