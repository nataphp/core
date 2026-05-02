<div class="form-group {$element->attrs()->id()}{if $element->hasError()} has-error{/if}">
    {block name='label'}
        {if $element->label()}
            <label for="{$element->attrs()->id()}" class="control-label col-sm-{$element->columnSize()}">
                {if $element->required()}<sup>*</sup>{/if} {$element->label()}
                {if $element->tooltip()}
                    <i class="fa fa-question-circle" data-toggle="tooltip" data-placement="auto top" data-container="body" {$element->tooltip()->attrs()} title="{$element->tooltip()}"></i>
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
        <div id="{$element->attrs()->id()}-error" class="nataphp-error-message">
            {if $element->hasError()}
                <p class="help-block"><small><i class="fa fa-times"></i> {$element->hasError()}</small></p>
            {/if}
        </div>
        {if $element->description()}
            <p class="help-block"><small><em>{$element->description()}</em></small></p>
        {/if}
    </div>
</div>