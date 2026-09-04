{* `form-group` is the hook showwhen.js toggles -- see default.tpl. *}
<div class="form-group flex flex-col gap-4 mb-4 {$element->attrs()->id()}{if $element->hasError()} text-error{/if} sm:flex-row sm:items-baseline">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="label sm:w-1/4 shrink-0">
                <span class="label-text">
                    {if $element->required()}<span class="text-error">*</span>{/if} {$element->label()}
                    {if $element->tooltip()}
                        <span class="tooltip tooltip-right" data-tip="{$element->tooltip()}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-4 stroke current-content opacity-70 inline-block align-middle ml-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </span>
                    {/if}
                </span>
            </label>
        {/if}
    {/block}

    <div class="flex-1 min-w-0">
        {block name='element'}{/block}
        <div id="{$element->attrs()->id()}-error" class="label">
            {if $element->hasError()}
                <span class="label-text-alt text-error">{$element->error()}</span>
            {/if}
        </div>
        {if $element->description()}
            <p class="label-text-alt text-base-content/70 mt-1">{$element->description()}</p>
        {/if}
    </div>
</div>
