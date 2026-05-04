{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="flex w-full [&>*]:rounded-none [&>:first-child]:rounded-l-lg [&>:last-child]:rounded-r-lg [&>*:not(:first-child)]:-ml-px">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="shrink-0 inline-flex items-center gap-1 px-3 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition cursor-pointer" type="button">{$prependButton}</button>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm text-gray-500 bg-gray-100 border border-gray-300">{$prepend}</span>
        {/foreach}
    {/if}

    {if $element->translate()}
        {foreach $element->languages() as $code => $l10n}
            {assign var='value' value=''}
            {foreach $element->getValueOutput() as $_code => $_value}
                {if $_code == $code}
                    {assign var='value' value=$_value}
                    {break}
                {/if}
            {/foreach}
            <div class="form-lang-container" data-lang-code="{$code}">
                <textarea class="{if $input_group}flex-1 min-w-0{else}w-full{/if} px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition resize-y placeholder:text-gray-400 disabled:opacity-50 disabled:bg-gray-50 {$element->attrs()->getClass()} form-control-translate" data-lang-code="{$code}" rows="{$element->rows()}" id="{$element->attrs()->id()}-{$code}" placeholder="{$l10n->language}" name="{$element->attrs()->name()}[{$code}]">{$value}</textarea>
            </div>
        {/foreach}
    {else}
        <textarea class="{if $input_group}flex-1 min-w-0{else}w-full{/if} px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition resize-y placeholder:text-gray-400 disabled:opacity-50 disabled:bg-gray-50 disabled:cursor-not-allowed {$element->attrs()->consumeClass()}" {$element->attrs()}>{$element->getValueOutput()}</textarea>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm text-gray-500 bg-gray-100 border border-gray-300">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="shrink-0 inline-flex items-center gap-1 px-3 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition cursor-pointer" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
