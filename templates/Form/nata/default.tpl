<div class="w-full mb-5 {$element->attrs()->id()}{if $element->hasError()} nata-field--invalid{/if}">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="block mb-1.5 text-sm font-medium {if $element->hasError()}text-red-500{else}text-gray-700{/if}">
                {if $element->required()}<span class="text-red-500 mr-0.5">*</span>{/if}{$element->label()}
                {if $element->tooltip()}
                    <span class="relative inline-block group/tip ml-1 align-middle">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-4 stroke-gray-400 inline-block"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2 py-1 text-xs text-white bg-gray-900 rounded-md whitespace-nowrap opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-opacity z-10">{$element->tooltip()}</span>
                    </span>
                {/if}
            </label>
        {/if}
    {/block}

    {block name='element'}{/block}

    <div id="{$element->attrs()->id()}-error" class="nata-field-error{if $element->hasError()} nata-field-error--visible{/if}">
        <span data-nata-form-error-message>{if $element->hasError()}{$element->error()}{/if}</span>
    </div>
</div>
