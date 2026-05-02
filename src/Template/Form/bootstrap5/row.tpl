<div class="row g-3">
    {foreach $elements as $element}
        {assign var=col value=12/$element_count|round}
        {if $element_count == 5}
            {if $element@index > 1}
                {assign var=col value=2}
            {else}
                {assign var=col value=3}
            {/if}
        {/if}
        <div class="col-md-{$col}">
            {$collection->get($element)->render()}
        </div>
    {/foreach}
</div>