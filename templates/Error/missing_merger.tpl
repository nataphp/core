
{block name='heading'}{/block}
{block name='error'}
    {if isset($file)}
        <h2>Merger File Missing</h2>
        <p class="error">
            <strong>Error: </strong> {$message}
        </p>
        <p class="error">
            <strong>File:</strong> {$file}
        </p>
        <p class="error">
            <strong>Path:</strong> {$path}
        </p>
    {else}
        <h2>Merger Parser Missing</h2>
        <p class="error">
            <strong>Error: </strong> {$message}
        </p>
    {/if}
{/block}