{assign var=grid_cols value='md:grid-cols-1'}
{if $element_count == 2}{assign var=grid_cols value='md:grid-cols-2'}{/if}
{if $element_count == 3}{assign var=grid_cols value='md:grid-cols-3'}{/if}
{if $element_count == 4}{assign var=grid_cols value='md:grid-cols-4'}{/if}
{if $element_count == 5}{assign var=grid_cols value='md:grid-cols-5'}{/if}
{if $element_count == 6}{assign var=grid_cols value='md:grid-cols-6'}{/if}
<div class="grid grid-cols-1 gap-4 {$grid_cols}">
    {foreach $elements as $element}
        <div>
            {$collection->get($element)->render()}
        </div>
    {/foreach}
</div>
