{block name='element'}
    {if $element->multiple()}
        <input type="hidden" name="{$element->attrs()->name()}" />
    {/if}

    {if !$element->options()->isEmpty()}
        <select class="w-full px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition appearance-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" {$element->attrs()}>
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
        <p class="text-sm text-gray-500 italic"><em>{$element->noOptionsMessage()}</em></p>
    {/if}

    {if !$element->multiple() && $element->readOnly()}
        <input type="hidden" name="{$element->attrs()->name()}" value="{$element->getValueOutput()}" />
    {/if}
{/block}
