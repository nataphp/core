{block name='input'}
    {assign var=_ig value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}
    <input type="week" class="{if $_ig}flex-1 min-w-0{else}w-full{/if} px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 relative focus:z-10 transition placeholder:text-gray-400 disabled:opacity-50 disabled:bg-gray-50 disabled:cursor-not-allowed {$element->attrs()->consumeClass()}" {$element->attrs()} />
{/block}
