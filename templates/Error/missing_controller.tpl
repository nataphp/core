{block name='heading'}
    <h2>Missing Controller</h2>
{/block}

{block name='solution'}
    <hr>

    <h4>Solution</h4>
    <p class="text-muted"><small>Create controller class <strong>{$class}</strong>{if !empty($plugin)} in plugin <strong>{$plugin}</strong>{/if}</small>.</p>

    <hr>

    <h4>Example code</h4>

<code><pre>&lt;?php

namespace {$namespace};

class {$controller} extends App {ldelim}

/**
 * Main action.
 */
    function index() {ldelim}
        // Action code here
    {rdelim}

{rdelim}
?&gt;</pre></code>
{/block}