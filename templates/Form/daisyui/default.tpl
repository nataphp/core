{*
 * `form-group` is what showwhen.js looks for: it toggles
 * dependentEl.closest('.form-group'), and silently does nothing when there is
 * no such ancestor. The bootstrap themes carry the class, this one never did,
 * which is why showWhen/hideWhen were inert on daisyui. Nothing styles it here
 * -- the only .form-group rules in nata.form.css target chosen containers.
 *}
<div class="form-group w-full mb-4 {$element->attrs()->id()}{if $element->hasError()} nata-field--invalid{/if}">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="label">
                <span class="label-text">
                    {if $element->required()}<span class="text-error">*</span>{/if} {$element->label()}
                    {if $element->tooltip()}
                        <span class="tooltip tooltip-right" data-tip="{$element->tooltip()|escape:'html'}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-4 stroke-current opacity-70 inline-block align-middle ml-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </span>
                    {/if}
                </span>
            </label>
        {/if}
    {/block}

    {block name='element'}{/block}

    <div id="{$element->attrs()->id()}-error" class="nata-field-error{if $element->hasError()} nata-field-error--visible{/if}">
        <span data-nata-form-error-message>{if $element->hasError()}{$element->error()}{/if}</span>
    </div>
</div>
