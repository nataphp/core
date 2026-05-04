
{block name='error'}
    <p class="error">
        <strong>Error: </strong>
        {$message|nl2br}
    </p>
    {if $error->getConfig()}
        <p class="error">
            <strong>Configuration: </strong>
            {*<p><pre><code>{print_r($error->getConfig(), true)}</code></pre></p>*}
        </p>
    {/if}
    {if $trace}
        <p class="error">
            <strong>Trace: </strong>
            <p><code>{$trace}</code></p>
        </p>
    {/if}
{/block}
