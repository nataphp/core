{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="join w-full">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="btn btn-outline join-item" type="button">{$prependButton}</button>
        {/foreach}
    {/if}
    {if $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$prepend}</span>
        {/foreach}
    {/if}

    {block name='input'}
        <input type="text" class="input input-bordered w-full{if $input_group} join-item{/if} {$element->attrs()->consumeClass()}" {$element->attrs()} />
    {/block}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$append}</span>
        {/foreach}
    {/if}
    {if $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="btn btn-outline join-item" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
