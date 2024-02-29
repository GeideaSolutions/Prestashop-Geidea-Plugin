
{if not empty($errors)}
    <div class="text-xs-center">
        <h3>{l s='An error occurred' mod='geideapay'}:</h3>
        <ul class="alert alert-danger">
            {foreach from=$errors item='error'}
                <li>{$error}</li>
            {/foreach}
        </ul>
    </div>
{/if}
