<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{$name}</title>

        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
        <!-- Optional theme -->
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">
    </head>

    <body>
        <div class="container">
            {block name='heading'}
                <h2>{$name}</h2>
            {/block}

            {block name='error'}
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
                {if !empty($plugin)}
                    <p class="error">
                        <strong>Plugin: </strong>
                        {$plugin}
                    </p>
                {/if}
                <p class="error">
                    <strong>Trace: </strong>
                    {$trace}
                </p>
            {/block}

            {block name='solution'}
                <hr>

                <h4>Solution</h4>
                {block name='solution'}
                    <p class="text-muted"><small><em>No suggestion for this problem at the moment.</em></small></p>
                {/block}

                <hr>
            {/block}

            {block name='footer'}
                <p class="text-muted"><small>PHP {phpversion} - NataPHP Framework</small></p>
            {/block}
        </div>
        <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
        <!-- Latest compiled and minified JavaScript -->
        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
    </body>
</html>