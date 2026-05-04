{if $element->multiple()}
    {foreach $element->getValueOutput() as $index => $value}
        <input type="hidden" id="{$element->attrs()->id()}-{$index}" name="{$element->attrs()->name()}[]" value='{$value}' />
    {/foreach}
{else}
    <input type="hidden" {$element->attrs()} />
{/if}
