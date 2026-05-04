{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->show() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="join nata-form-input-group w-full relative">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="btn btn-outline join-item" type="button">{$prependButton}</button>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$prepend}</span>
        {/foreach}
    {/if}

    {block name='input'}
        {if $element->attrs()->get('autocomplete') === 'off'}
            <input type="password" class="input input-bordered w-full join-item hidden" {$element->attrs()} />
        {/if}
        <input type="password" class="input input-bordered w-full{if $input_group} join-item{/if} {$element->attrs()->consumeClass()}" {$element->attrs()} />
    {/block}

    {if $element->show()}
        <button class="btn btn-outline btn-nata-form-toggle-show-password join-item border-none absolute right-3 top-1/2 -translate-y-1/2 p-1 hover:text-base-content/70 transition-colors" type="button" aria-label="{__('Show password')}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current nata-form-show-password-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current hidden nata-form-hide-password-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
        </button>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="btn join-item no-animation bg-base-200 border-base-300">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="btn btn-outline join-item" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
