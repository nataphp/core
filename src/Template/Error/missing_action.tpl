{block name='heading'}
    <h2>Missing Controller Action</h2>
{/block}

{block name='solution'}                
    <hr>

    <h4>Solution</h4>
    <p class="text-muted"><small>Add action <strong>{$action}()</strong> to controller class <strong>{$class}</strong>{if !empty($plugin)} in plugin <em>{$plugin}</em>{/if}</small>.</p>

    <hr>

    <h4>Example code</h4>

<code><pre>

/**
 * Action.
 */
    function {$action}() {ldelim}
        // Action code here
    {rdelim}

</pre></code>
{/block}