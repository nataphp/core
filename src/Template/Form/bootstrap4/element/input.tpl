{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton() || $element->clearUrl()}
    {if $input_group}
        <div class="input-group">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <span class="input-group-btn">{$prependButton}</span>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="input-group-addon">{$prepend}</span>
        {/foreach}
    {/if}

    {block name='input'}
        <input type="text" {$element->attrs()} />
    {/block}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-addon">{$append}</span>
        {/foreach}
    {/if}
    {if $element->clearUrl()}
        <span class="input-group-addon">
            <a href="{$element->clearUrl()}" class="input-clear-link" title="{__('Clear')}" aria-label="{__('Clear')}">&times;</a>
        </span>
    {/if}
    {if $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <span class="input-group-btn">{$appendButton}</span>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
