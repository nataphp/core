{block name='element'}
    {if $element->multiple()}
        <input type="hidden" name="{$element->attrs()->name()}" />
    {/if}

    {if !$element->options()->isEmpty()}
        <select class="select select-bordered w-full" {$element->attrs()}>
            <optgroup disabled hidden></optgroup>
            {block name='options'}
                {foreach $element->options()->get() as $item}
                    {if $item instanceof \Nata\Form\OptionGroup}
                        <optgroup label="{$item->label()}">
                            {foreach $item->options() as $option}
                                <option value="{$option->value()}"{if $option->description()} title="{$option->description()}"{/if}{if $option->disabled()} disabled="disabled"{/if}{if $option->selected()} selected="selected"{/if} {$option->attributes()->toHtml()}>{$option->label()}</option>
                            {/foreach}
                        </optgroup>
                    {else}
                        <option value="{$item->value()}"{if $item->description()} title="{$item->description()}"{/if}{if $item->disabled()} disabled="disabled"{/if}{if $item->selected()} selected="selected"{/if} {$item->attributes()->toHtml()}>{$item->label()}</option>
                    {/if}
                {/foreach}
            {/block}
        </select>
    {else}
        <p class="text-base-content/70 text-sm"><em>{$element->noOptionsMessage()}</em></p>
    {/if}

    {if !$element->multiple() && $element->readOnly()}
        <input type="hidden" name="{$element->attrs()->name()}" value="{$element->getValueOutput()}" />
    {/if}
{/block}
