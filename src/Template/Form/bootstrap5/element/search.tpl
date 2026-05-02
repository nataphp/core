{block name='element'}
    <div class="input-group">
        <input type="search" class="form-control" {$element->attrs()} />
        {if $element->clearUrl()}
        <span class="input-group-text">
            <a href="{$element->clearUrl()}" class="input-clear-link text-decoration-none" title="{__('Clear')}" aria-label="{__('Clear')}">&times;</a>
        </span>
        {/if}
        <button class="btn btn-outline-secondary" type="submit">{$element->label()}</button>
    </div>
{/block}
