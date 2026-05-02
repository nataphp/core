<section>
    {if $group->legend()}
        <header>{$group->legend()}</header>
    {/if}
    <div class="accordion" id="{$group->attrs()->id()}" data-group="{$group->group()}" data-max="{$group->max()}">
        {assign var=add_row_text value=$group->addRowText()}
        {foreach $group->rows() as $index => $row}
            <div id="{$row->attrs()->id()}" class="accordion-item group-row{if $row->open()} open{/if}">
                <h2 class="accordion-header">
                    <div class="accordion-button d-flex align-items-center gap-2 pe-3"
                         data-group-toggle
                         data-title-pattern="{$row->titlePattern()|escape:'html'}"
                         data-default-title="{$row->defaultTitle()|escape:'html'}">
                        <span class="panel-toggle-title flex-grow-1">{$row->title()}</span>
                        <a class="btn btn-link text-danger p-0 remove" href="#" title="{__('Remove this row?')}">
                            <i class="bi bi-x-lg"></i>
                        </a>
                        <span class="group-row-chevron ms-1">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                </h2>
                <div class="accordion-body" data-group-content>
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
        <button class="btn btn-outline-primary w-100 mt-3 panel-group-add-another{if !$group->newRowAllowed()} d-none{/if}" type="button">
            <i class="bi bi-plus-lg"></i> {$add_row_text}
        </button>
    </div>
</section>
