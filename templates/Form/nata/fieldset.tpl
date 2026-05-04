<fieldset id="{$fieldset->attrs()->id()}" class="border border-gray-200 rounded-xl p-5 slideInRight" title="{$fieldset->title()}">
    {if $fieldset->description()}
        <legend class="px-2 text-sm font-semibold text-gray-700">{$fieldset->description()}</legend>
    {/if}
    {$fieldset->elements()->render()}
    {$fieldset->groups()->render()}
</fieldset>
