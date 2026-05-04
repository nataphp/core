<section>
    {if $group->legend()}
        <header class="text-lg font-semibold mb-2">{$group->legend()}</header>
    {/if}
    <div id="{$group->attrs()->id()}" data-group="{$group->group()}" data-max="{$group->max()}" class="join join-vertical w-full">
        {assign var=add_row_text value=$group->addRowText()}
        {foreach $group->rows() as $index => $row}
            <div id="{$row->attrs()->id()}" class="join-item border border-base-300 group-row{if $row->open()} open{/if}">
                <div class="flex items-center justify-between gap-2 px-4 py-3"
                     data-group-toggle
                     data-title-pattern="{$row->titlePattern()|escape:'html'}"
                     data-default-title="{$row->defaultTitle()|escape:'html'}">
                    <span class="panel-toggle-title font-medium">{$row->title()}</span>
                    <div class="flex items-center gap-1 shrink-0">
                        <a class="btn btn-ghost btn-sm text-error remove" href="#" title="{__('Remove this row?')}" aria-label="{__('Remove this row?')}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </a>
                        <span class="group-row-chevron">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>
                </div>
                <div class="p-4" data-group-content>
                    {if $row->elements()->has()}
                        {$row->elements()->render()}
                    {/if}
                    {if $row->groups()->has()}
                        {foreach $row->groups()->get() as $group}
                            {$group->render()}
                        {/foreach}
                    {/if}
                </div>
            </div>
        {/foreach}
        <button class="btn btn-outline btn-primary w-full mt-3 panel-group-add-another join-item{if !$group->newRowAllowed()} hidden{/if}" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            {$add_row_text}
        </button>
    </div>
</section>
