{block name='element'}

    {assign var=input_group value=$element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}
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

    {if $element->translate()}
        {foreach $element->languages() as $code => $l10n}
            {assign var='value' value=''}
            {foreach $element->getValueOutput() as $_code => $_value}
                {if $_code == $code}
                    {assign var='value' value=$_value}
                    {break}
                {/if}
            {/foreach}
            <textarea class="{$element->attrs()->getClass()} form-control-translate" data-lang-code="{$code}" rows="{$element->rows()}" id="{$element->attrs()->id()}-{$code}" placeholder="{$l10n->language}" name="{$element->attrs()->name()}[{$code}]">{$value}</textarea>
        {/foreach}
    {else}
        <textarea {$element->attrs()}>{$element->getValueOutput()}</textarea>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-addon">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <span class="input-group-btn">{$appendButton}</span>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
