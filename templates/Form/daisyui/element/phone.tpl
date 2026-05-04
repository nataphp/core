{block name='element'}
    {assign var=input_group value=$element->prefix() || $element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}

    {if $input_group}
        <div class="join w-full">
    {/if}

    {if $element->prefix()}
        <select class="select select-bordered join-item" {$element->prefix()->attrs()}>
            <optgroup disabled hidden></optgroup>
            {foreach $element->prefix()->options()->get() as $option}
                <option value="{$option->value()}"{if $option->description()} title="{$option->description()}"{/if}{if $option->disabled()} disabled="disabled"{/if}{if $option->selected()} selected="selected"{/if} {$option->attributes()->toHtml()}>{$option->label()}</option>
            {/foreach}
        </select>
    {/if}

    {block name='input'}
        <input type="tel" class="input input-bordered w-full join-item {$element->attrs()->consumeClass()}" {$element->attrs()} />
    {/block}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$appendButton}</span>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
