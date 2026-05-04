{block name='element'}

    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="input-group">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="btn btn-outline-secondary" type="button">{$prependButton}</button>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="input-group-text">{$prepend}</span>
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
                <textarea class="form-control {$element->attrs()->getClass()} form-control-translate" data-lang-code="{$code}" rows="{$element->rows()}" id="{$element->attrs()->id()}-{$code}" placeholder="{$l10n->language}" name="{$element->attrs()->name()}[{$code}]">{$value}</textarea>
            </div>
        {/foreach}
    {else}
        <textarea class="form-control" {$element->attrs()}>{$element->getValueOutput()}</textarea>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-text">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="btn btn-outline-secondary" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
