<section>
    {if $group->legend()}
        <header class="text-base font-semibold text-gray-900 mb-3">{$group->legend()}</header>
    {/if}
    <div id="{$group->attrs()->id()}" data-group="{$group->group()}" data-max="{$group->max()}" class="flex flex-col w-full gap-px">
        {assign var=add_row_text value=$group->addRowText()}
        {foreach $group->rows() as $index => $row}
            <div id="{$row->attrs()->id()}" class="border border-gray-200 rounded-lg overflow-hidden group-row{if $row->open()} open{/if}">
                <div class="flex items-center justify-between gap-2 px-4 py-3 bg-gray-50 border-b border-gray-200 cursor-pointer select-none"
                     data-group-toggle
                     data-title-pattern="{$row->titlePattern()|escape:'html'}"
                     data-default-title="{$row->defaultTitle()|escape:'html'}">
                    <span class="panel-toggle-title text-sm font-medium text-gray-700">{$row->title()}</span>
                    <div class="flex items-center gap-1 shrink-0">
                        <a class="p-1.5 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition remove" href="#" title="{__('Remove this row?')}" aria-label="{__('Remove this row?')}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </a>
                        <span class="text-gray-400 group-row-chevron transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
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
        <button class="flex items-center justify-center gap-2 w-full mt-2 px-4 py-2.5 text-sm font-medium text-blue-600 border border-dashed border-blue-300 rounded-lg hover:bg-blue-50 hover:border-blue-400 transition cursor-pointer panel-group-add-another{if !$group->newRowAllowed()} hidden{/if}" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            {$add_row_text}
        </button>
    </div>
</section>
