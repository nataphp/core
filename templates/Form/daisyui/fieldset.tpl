<fieldset id="{$fieldset->attrs()->id()}" class="fieldset slideInRight" title="{$fieldset->title()}">
    {if $fieldset->description()}
        <legend class="fieldset-legend">{$fieldset->description()}</legend>
    {/if}
    {$fieldset->elements()->render()}
    {$fieldset->groups()->render()}
</fieldset>
