{block name='element'}
    {assign var=input_group value=$element->prefix() || $element->prepend() || $element->append() || $element->prependButton() || $element->appendButton()}

    {if $input_group}
        <div class="input-group">
    {/if}

    {if $element->prefix()}
        <div class="input-group-text">
            <select class="form-select" {$element->prefix()->attrs()}>
                <!-- iOS multiple select workaround -->
                <optgroup disabled hidden></optgroup>
                <!-- /iOS multiple select workaround -->
                {block name='options'}
                    {foreach $element->prefix()->options()->get() as $option}
                        <option value="{$option->value()}"{if $option->description()} title="{$option->description()}"{/if}{if $option->disabled()} disabled="disabled"{/if}{if $option->selected()} selected="selected"{/if} {$option->attributes()->toHtml()}>{$option->label()}</option>
                    {/foreach}
                {/block}
            </select>
        </div>
    {/if}

    {block name='input'}
        <input type="tel" class="form-control" {$element->attrs()} />
    {/block}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-text">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <span class="input-group-text">{$appendButton}</span>
        {/foreach}
    {/if}

    {if $input_group}</div>{/if}
{/block}
