{block name='element'}
    {if $element->multiple()}
        <!-- Always send the input value -->
        <input type="hidden" name="{$element->attrs()->name()}" />
    {/if}

    {if !$element->options()->isEmpty()}
        <select class="form-select" {$element->attrs()}>
            <!-- iOS multiple select workaround -->
            <optgroup disabled hidden></optgroup>
            <!-- /iOS multiple select workaround -->
            {block name='options'}
                {foreach $element->options()->get() as $option}
                    <option value="{$option->value()}"{if $option->description()} title="{$option->description()}"{/if}{if $option->disabled()} disabled="disabled"{/if}{if $option->selected()} selected="selected"{/if} {$option->attributes()->toHtml()}>{$option->label()}</option>
                {/foreach}
            {/block}
        </select>
    {else}
        <p class="text-muted mb-0"><em>{$element->noOptionsMessage()}</em></p>
    {/if}

    {if !$element->multiple() && $element->readOnly()}
        <!-- Always send the input value -->
        <input type="hidden" name="{$element->attrs()->name()}" value="{$element->getValueOutput()}" />
    {/if}

{/block}
