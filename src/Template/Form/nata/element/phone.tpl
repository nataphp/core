{block name='element'}
    {assign var=input_group value=$element->prefix() || $element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}

    {if $input_group}
        <div class="flex w-full [&>*]:rounded-none [&>:first-child]:rounded-l-lg [&>:last-child]:rounded-r-lg [&>*:not(:first-child)]:-ml-px">
    {/if}

    {if $element->prefix()}
        <select class="shrink-0 px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition appearance-none cursor-pointer" {$element->prefix()->attrs()}>
            <optgroup disabled hidden></optgroup>
            {foreach $element->prefix()->options()->get() as $option}
                <option value="{$option->value()}"{if $option->description()} title="{$option->description()}"{/if}{if $option->disabled()} disabled="disabled"{/if}{if $option->selected()} selected="selected"{/if} {$option->attributes()->toHtml()}>{$option->label()}</option>
            {/foreach}
        </select>
    {/if}

    {block name='input'}
        <input type="tel" class="{if $input_group}flex-1 min-w-0{else}w-full{/if} px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 relative focus:z-10 transition placeholder:text-gray-400 disabled:opacity-50 disabled:bg-gray-50 disabled:cursor-not-allowed {$element->attrs()->consumeClass()}" {$element->attrs()} />
    {/block}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm text-gray-500 bg-gray-100 border border-gray-300">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <span class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm text-gray-500 bg-gray-100 border border-gray-300">{$appendButton}</span>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
