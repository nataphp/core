{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton() || $element->clearUrl()}
    {if $input_group}
        <div class="input-group">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="btn btn-outline-secondary" type="button">{$prependButton}</button>
        {/foreach}
    {/if}
    {if $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="input-group-text">{$prepend}</span>
        {/foreach}
    {/if}

    {block name='input'}
        <input type="text" class="form-control" {$element->attrs()} />
    {/block}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-text">{$append}</span>
        {/foreach}
    {/if}
    {if $element->clearUrl()}
        <span class="input-group-text">
            <a href="{$element->clearUrl()}" class="input-clear-link text-decoration-none" title="{__('Clear')}" aria-label="{__('Clear')}">&times;</a>
        </span>
    {/if}
    {if $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="btn btn-outline-secondary" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
