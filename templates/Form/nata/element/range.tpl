{block name='input'}
    <input type="range" class="w-full h-2 bg-gray-200 rounded-full appearance-none cursor-pointer accent-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-50 disabled:cursor-not-allowed" {$element->attrs()}{if !$element->options()->isEmpty()} list="{$element->attrs()->id()}-tickmarks"{/if} />

    {if !$element->options()->isEmpty()}
        <datalist id="{$element->attrs()->id()}-tickmarks">
            {foreach $element->options()->get() as $option}
                <option value="{$option->value()}" label="{$option->label()}">
            {/foreach}
        </datalist>
    {/if}
{/block}
