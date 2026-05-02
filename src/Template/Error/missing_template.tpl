
{block name='heading'}
    <h2>Missing Template</h2>
{/block}
{block name='error'}
    <p class="error">
        <strong>Error:</strong> {$message}
    </p>
    <p class="error">
        <strong>Template Path:</strong> {if $template_path}<code>{$template_path}</code>{else}<em class="text-muted">not set</em>{/if}
    </p>
    <p class="error">
        <strong>Layout:</strong> {if $layout}<code>{$layout}</code>{else}<em class="text-muted">not set</em>{/if}
    </p>
    {if $plugin}
        <p class="error">
            <strong>Plugin:</strong> <code>{$plugin}</code>
        </p>
    {/if}
    <p class="error">
        <strong>Search paths:</strong><pre><code>{print_r($paths, true)}</code></pre>
    </p>
{/block}

{block name='solution'}
    <p>Create a template file <code>{$template_file}</code> on <code>{$defaultpath}</code></p>    
{/block}