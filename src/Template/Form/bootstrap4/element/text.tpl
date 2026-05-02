{block name='input'}
    {if $element->translate()}
        {foreach $element->languages() as $code => $l10n}
            {assign var='value' value=''}
            {foreach $element->getValueOutput() as $_code => $_value}
                {if $_code == $code}
                    {assign var='value' value=$_value}
                    {break}
                {/if}
            {/foreach}
            <input type="text" data-lang-code="{$code}" class="{$element->attrs()->getClass()} form-control-translate" id="{$element->attrs()->id()}-{$code}" name="{$element->attrs()->name()}[{$code}]" placeholder="{$l10n->language}" value="{$value}" />
        {/foreach}
    {else}
        <input type="text" {$element->attrs()} />
    {/if}
{/block}
