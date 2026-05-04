{block name='element'}
    <div class="input-group">
        <input type="search" {$element->attrs()} />
        {if $element->clearUrl()}
        <span class="input-group-addon">
            <a href="{$element->clearUrl()}" class="input-clear-link" title="{__('Clear')}" aria-label="{__('Clear')}">&times;</a>
        </span>
        {/if}
        <span class="input-group-btn">
            <button class="btn btn-default" type="submit">{$element->label()}</button>
        </span>
    </div><!-- /input-group -->
{/block}
