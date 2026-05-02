{block name='input'}
    <input type="range" class="range range-primary" {$element->attrs()}{if !$element->options()->isEmpty()} list="{$element->attrs()->id()}-tickmarks"{/if} />

    {if !$element->options()->isEmpty()}
        <datalist id="{$element->attrs()->id()}-tickmarks">
            {foreach $element->options()->get() as $option}
                <option value="{$option->value()}" label="{$option->label()}">
            {/foreach}
        </datalist>
    {/if}
{/block}
