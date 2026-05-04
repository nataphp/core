{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->show() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="input-group nata-form-input-group">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <button class="btn btn-outline-secondary" type="button">{$prependButton}</button>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="input-group-text">{$prepend}</span>
        {/foreach}
    {/if}

    {block name='input'}
        {if $element->attrs()->get('autocomplete') === 'off'}
            <input type="password" class="form-control" {$element->attrs()} style="display: none;" />
        {/if}
        <input type="text" class="form-control" {$element->attrs()} />
    {/block}

    {if $element->show()}
        <button class="btn btn-outline-secondary btn-nata-form-toggle-show-password" type="button" aria-label="{__('Show password')}">
            <i class="bi bi-eye nata-form-show-password-icon"></i>
            <i class="bi bi-eye-slash hidden nata-form-hide-password-icon"></i>
        </button>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-text">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <button class="btn btn-outline-secondary" type="button">{$appendButton}</button>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
