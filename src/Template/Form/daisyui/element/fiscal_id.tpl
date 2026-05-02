{block name='input'}
    <input type="text" class="input input-bordered w-full{if $element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()} join-item{/if}" {$element->attrs()} />
{/block}
