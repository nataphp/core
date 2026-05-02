<div class="form-group {$element->attrs()->id()}{if $element->hasError()} has-error{/if}">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="control-label">
                {if $element->required()}<sup>*</sup>{/if} {$element->label()}
                {if $element->tooltip()}
                    <i class="fa fa-question-circle" data-toggle="tooltip" data-placement="auto top" data-container="body" {$element->tooltip()->attrs()} title="{$element->tooltip()}"></i>
                {/if}
            </label>
        {/if}
    {/block}
    {block name='element'}{/block}
    <div id="{$element->attrs()->id()}-error" class="nata-field-error{if $element->hasError()} nata-field-error--visible{/if}">
        <span data-nata-form-error-message>{if $element->hasError()}{$element->error()}{/if}</span>
    </div>
    {if $element->description()}
        <div class="help-block"><small><em>{$element->description()}</em></small></div>
    {/if}
</div>