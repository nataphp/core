{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="join w-full">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="btn btn-outline join-item" type="button">{$prependButton}</button>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$prepend}</span>
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
                <textarea class="textarea textarea-bordered w-full {$element->attrs()->getClass()} form-control-translate{if $input_group} join-item{/if}" data-lang-code="{$code}" rows="{$element->rows()}" id="{$element->attrs()->id()}-{$code}" placeholder="{$l10n->language}" name="{$element->attrs()->name()}[{$code}]">{$value}</textarea>
            </div>
        {/foreach}
    {else}
        <textarea class="textarea textarea-bordered w-full {$element->attrs()->consumeClass()}{if $input_group} join-item{/if}" {$element->attrs()}>{$element->getValueOutput()}</textarea>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="btn btn-outline join-item" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
