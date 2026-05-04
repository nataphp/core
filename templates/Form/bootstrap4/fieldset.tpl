<fieldset id="{$fieldset->attrs()->id()}" class="slideInRight" title="{$fieldset->title()}">
    {if $fieldset->description()}
        <legend>{$fieldset->description()}</legend>
    {/if}
    {$fieldset->elements()->render()}
    {$fieldset->groups()->render()}
</fieldset>
