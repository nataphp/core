{block name='element'}
    <input type="hidden" name="{$element->attrs()->name()}" class="remove-me">
    <div {$element->attrs()->toHtml()}></div>
{/block}
