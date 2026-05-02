{block name='element'}
    {if $element->disabled() || $element->readOnly()}
        <span {$element->attrs()}></span>
    {else}
        <input type="hidden" {$element->attrs()} />
    {/if}
{/block}
