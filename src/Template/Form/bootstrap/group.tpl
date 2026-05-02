<section>
    {if $group->legend()}
        <header>{$group->legend()}</header>
    {/if}
    <div class="panel-group" id="{$group->attrs()->id()}" data-group="{$group->group()}" data-max="{$group->max()}" role="tablist" aria-multiselectable="true">
        {assign var=add_row_text value=$group->addRowText()}
        {foreach $group->rows() as $index => $row}
            <div id="{$row->attrs()->id()}" class="panel panel-default group-row{if $row->open()} open{/if}">
                <div class="panel-heading" role="tab">
                    <h4 class="panel-title">
                        <a data-title-pattern="{$row->titlePattern()|escape:'html'}" data-default-title="{$row->defaultTitle()|escape:'html'}" class="panel-toggle" data-group-toggle>
                            <span class="panel-toggle-title">{$row->title()}</span>
                            <span class="group-row-chevron"><i class="fa fa-chevron-down"></i></span>
                        </a>
                        <a class="pull-right remove" href="#" title="{__('Remove this row?')}"><i class="fa fa-times"></i></a>
                    </h4>
                </div>
                <div class="panel-collapse" data-group-content>
                    <div class="panel-body">
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
            </div>
        {/foreach}
        <button class="btn btn-white btn-sm btn-block panel-group-add-another{if !$group->newRowAllowed()} hidden{/if}" type="button">
            <i class="fa fa-plus"></i> {$add_row_text}
        </button>
    </div>
</section>
