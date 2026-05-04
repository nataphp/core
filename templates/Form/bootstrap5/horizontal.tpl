<div class="row mb-3 {$element->attrs()->id()}{if $element->hasError()} has-error{/if}">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="col-sm-{$element->columnSize()} col-form-label">
                {if $element->required()}<sup class="text-danger">*</sup>{/if} {$element->label()}
                {if $element->tooltip()}
                    <i class="bi bi-question-circle" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-container="body" title="{$element->tooltip()}"></i>
                {/if}
            </label>
        {/if}
    {/block}

    {if $element->label()}
        {assign var='column_size' value=(12 - $element->columnSize())}
    {else}
        {assign var='column_size' value=12}
    {/if}
    <div class="col-sm-{$column_size}">
        {block name='element'}{/block}
        <div id="{$element->attrs()->id()}-error" class="invalid-feedback">
            {if $element->hasError()}
                <div><i class="bi bi-exclamation-circle"></i> {$element->hasError()}</div>
            {/if}
        </div>
        {if $element->description()}
            <div class="form-text text-muted">{$element->description()}</div>
        {/if}
    </div>
</div>