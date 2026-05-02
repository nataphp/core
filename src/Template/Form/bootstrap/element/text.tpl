{block name='input'}
    {if $element->translate()}
        <ul class="nav nav-tabs translation-controls" role="tablist">
            {foreach $element->languages() as $code => $l10n}
                <li{if $code == $nata.lang.code} class="active"{/if} role="presentation">
                    <a href="#tab-{$element->attrs()->id()}-{$code}" aria-controls="profile" data-toggle-lang="{$code}" role="tab" data-toggle="tab">
                        <i class="fli fli-{$code}"></i> <span class="language-name">{$l10n->language}</span>
                    </a>
                </li>
            {/foreach}
        </ul>

        <div class="tab-content translation-tab-content">
        {foreach $element->languages() as $code => $l10n}
            {assign var='value' value=''}
            {foreach $element->getValueOutput() as $_code => $_value}
                {if $_code == $code}
                    {assign var='value' value=$_value}
                    {break}
                {/if}
            {/foreach}
            <div role="tabpanel" class="tab-pane{if $code == $nata.lang.code} active{/if}" id="tab-{$element->attrs()->id()}-{$code}">
                <input type="text" data-lang-code="{$code}" class="{$element->attrs()->getClass()} form-control-translate" id="{$element->attrs()->id()}-{$code}" name="{$element->attrs()->name()}[{$code}]" placeholder="{$l10n->language}" value="{$value}" />
            </div>
        {/foreach}
        </div>
    {else}
        <input type="text" {$element->attrs()} />
    {/if}
{/block}
