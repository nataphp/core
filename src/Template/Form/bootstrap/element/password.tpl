{block name='element'}
    {assign var=input_group value=$element->prepend() || $element->append() || $element->show() || $element->prependButton() || $element->appendButton()}
    {if $input_group}
        <div class="input-group nata-form-input-group">
    {/if}
    {if $element->prependButton()}
        {foreach $element->prependButton() as $prependButton}
            <span class="input-group-btn">{$prependButton}</span>
        {/foreach}
    {elseif $element->prepend()}
        {foreach $element->prepend() as $prepend}
            <span class="input-group-addon">{$prepend}</span>
        {/foreach}
    {/if}

    {block name='input'}
        {if $element->attrs()->get('autocomplete') === 'off'}
            <input type="password" {$element->attrs()} style="display: none;" />
        {/if}
        <input type="password" {$element->attrs()} />
    {/block}

    {if $element->show()}
        <span class="input-group-btn">
            <button type="button" class="btn btn-white btn-nata-form-toggle-show-password" aria-label="{__('Show password')}">
                <i class="fa fa-eye nata-form-show-password-icon"></i>
                <i class="fa fa-eye-slash hidden nata-form-hide-password-icon"></i>
            </button>
        </span>
    {/if}

    {if $element->append()}
        {foreach $element->append() as $append}
            <span class="input-group-addon">{$append}</span>
        {/foreach}
    {elseif $element->appendButton()}
        {foreach $element->appendButton() as $appendButton}
            <span class="input-group-btn">{$appendButton}</span>
        {/foreach}
    {/if}

    {if $input_group}
        </div>
    {/if}
{/block}
