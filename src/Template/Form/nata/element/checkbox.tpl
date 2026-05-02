{block name='element'}
    <input type="hidden" name="{$element->attrs()->name()}" />
    {foreach $element->options()->get() as $option}
        {assign var=descriptive value=$option->description() || $option->icon()}

        {if $option->label() && $option->label() != ''}
            <label class="flex items-center gap-3 py-2 cursor-pointer{if $option->disabled()} opacity-50 cursor-not-allowed{/if}{if $element->inline()} inline-flex mr-4{/if}">
                {if !$element->multiple()}
                    <input type="hidden" id="{$option->id()}-hidden" name="{$element->attrs()->name()}" value="0" />
                {/if}

                <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500/20 cursor-pointer transition shrink-0" id="{$option->id()}" name="{$element->attrs()->name()}" value="{$option->value()}"{if $option->disabled()} disabled="disabled"{/if}{if $option->checked()} checked="checked"{/if} {$option->attributes()->toHtml()} />

                {if $descriptive}
                    {if $option->icon()}
                        <img class="w-8 h-8 object-cover rounded shrink-0" src="{Image->adaptive file=$option->icon() size='128x128'}" alt="">
                    {/if}
                    <span class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-sm font-medium text-gray-900">{$option->label()}</span>
                        {if $option->description()}
                            <span class="text-xs text-gray-500">{$option->description()}</span>
                        {/if}
                    </span>
                {else}
                    <span class="text-sm text-gray-700">{$option->label()}</span>
                {/if}
            </label>
        {else}
            <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500/20 cursor-pointer transition {$element->attrs()->getClass()}" id="{$option->id()}" name="{$element->attrs()->name()}" value="{$option->value()}"{if $option->disabled()} disabled="disabled"{/if}{if $option->checked()} checked="checked"{/if} {$option->attributes()->toHtml()} />
        {/if}
    {/foreach}
{/block}
