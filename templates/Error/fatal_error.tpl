{block name='heading'}
    <h2>Fatal Error</h2>
    <p class="error">
        <strong>Error: </strong>
        {$message|nl2br}
    </p>
    <p class="error">
        <strong>File: </strong>
        <code>{$file}</code>
    </p>
    <p class="error">
        <strong>Line: </strong>
        <code>{$line}</code>
    </p>
    <p class="error">
        <strong>Trace: </strong>
        <pre>{$error->getTraceAsString()}</pre>
    </p>
{/block}
{block name='error'}{/block}
