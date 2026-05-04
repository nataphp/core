<div class="flex flex-col gap-2 mb-5 {$element->attrs()->id()} sm:flex-row sm:items-start">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="block pt-2.5 text-sm font-medium shrink-0 sm:w-1/4 {if $element->hasError()}text-red-500{else}text-gray-700{/if}">
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

    <div class="flex-1 min-w-0">
        {block name='element'}{/block}
        <div id="{$element->attrs()->id()}-error" class="mt-1 min-h-[1.125rem]">
            <span class="text-xs text-red-500" data-nata-form-error-message>{if $element->hasError()}{$element->error()}{/if}</span>
        </div>
        {if $element->description()}
            <p class="mt-1 text-xs text-gray-500">{$element->description()}</p>
        {/if}
    </div>
</div>
