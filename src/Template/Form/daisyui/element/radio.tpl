{block name='element'}
    {foreach $element->options()->get() as $option}
        {assign var=descriptive value=$option->description() || $option->icon()}

        <label class="label cursor-pointer justify-start gap-3 py-2{if $option->disabled()} opacity-50{/if}{if $element->inline()} inline-flex mr-4{/if}">
            {if $descriptive}<div class="flex flex-col gap-1">{/if}

            <input type="radio" class="radio radio-primary" id="{$option->id()}" name="{$element->attrs()->name()}" value="{$option->value()}"{if $option->disabled()} disabled="disabled"{/if}{if $option->checked()} checked="checked"{/if} {$option->attributes()->toHtml()} />

            {if $descriptive}
                {if $option->icon()}
                    <img class="max-w-8 h-auto" src="{Image->adaptive file=$option->icon() size='128x128'}" alt="">
                {/if}
                <span class="flex flex-col">
                    <span class="font-medium">{$option->label()}</span>
                    {if $option->description()}
                        <span class="text-sm text-base-content/70">{$option->description()}</span>
                    {/if}
                </span>
            {else}
                <span class="label-text">{$option->label()}</span>
            {/if}

            {if $descriptive}</div>{/if}
        </label>
    {/foreach}
{/block}
