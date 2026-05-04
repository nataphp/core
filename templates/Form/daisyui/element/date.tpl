{block name='input'}
    <input type="date" class="input input-bordered w-full{if $element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()} join-item{/if} {$element->attrs()->consumeClass()}" {$element->attrs()} />
{/block}
