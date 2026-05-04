<div class="mb-3 {$element->attrs()->id()}{if $element->hasError()} has-error{/if}">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="form-label">
                {if $element->required()}<sup class="text-danger">*</sup>{/if} {$element->label()}
                {if $element->tooltip()}
                    <i class="bi bi-question-circle" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-container="body" title="{$element->tooltip()}"></i>
                {/if}
            </label>
        {/if}
    {/block}

    {block name='element'}{/block}

    <div id="{$element->attrs()->id()}-error" class="nata-field-error{if $element->hasError()} nata-field-error--visible{/if}">
        <span data-nata-form-error-message>{if $element->hasError()}{$element->error()}{/if}</span>
    </div>

    {if $element->description()}
        <div class="form-text text-muted">{$element->description()}</div>
    {/if}
</div>