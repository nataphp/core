{if $alert.url}<a href="{$alert.url}">{/if}
<div role="alert" id="{$alert.id}" class="alert alert-warning{if $alert.dismissable} alert-dismissable{/if}">
    <div class="flex items-center gap-2 flex-1 text-left">
        {$alert.icon}
        <div class="flex flex-col text-left">
            <strong class="text-left">{$alert.title}</strong>
            {if $alert.body}<small class="text-left">{$alert.body}</small>{/if}
        </div>
    </div>
    {if $alert.dismissable}
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="{__('Close')}">
            <span aria-hidden="true">&times;</span>
            <span class="sr-only">{__('Close')}</span>
        </button>
    {/if}
</div>
{if $alert.url}</a>{/if}
