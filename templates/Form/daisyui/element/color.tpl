{block name='input'}
    <input type="color" class="input input-bordered w-full max-w-20 h-10 p-1 cursor-pointer{if $element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()} join-item{/if} {$element->attrs()->consumeClass()}" {$element->attrs()} />
{/block}
