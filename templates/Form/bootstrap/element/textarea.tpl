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
        <ul class="nav nav-tabs translation-controls" role="tablist">
            {foreach $element->languages() as $code => $l10n}
                <li{if $code == $nata.lang.code} class="active"{/if} role="presentation">
                    <a href="#tab-{$element->attrs()->id()}-{$code}" data-toggle-lang="{$code}" data-toggle="tab" aria-controls="profile" role="tab">
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
                <textarea class="{$element->attrs()->getClass()} form-control-translate" data-lang-code="{$code}" rows="{$element->rows()}" id="{$element->attrs()->id()}-{$code}" placeholder="{$l10n->language}" name="{$element->attrs()->name()}[{$code}]">{$value}</textarea>
            </div>
        {/foreach}
        </div>
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
