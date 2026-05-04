{block name='element'}
    <!-- Always send the input value -->
    <input type="hidden" name="{$element->attrs()->name()}" />

    {foreach $element->options()->get() as $option}
        {assign var=descriptive value=$option->description() || $option->icon()}

        <div class="form-check{if $option->disabled()} opacity-50{/if}{if $element->inline()} form-check-inline{/if}">
            <label class="form-check-label{if $element->inline()} me-3{/if}">
                {if !$element->multiple()}
                    <input type="hidden" id="{$option->id()}-hidden" name="{$element->attrs()->name()}" value="0" />
                {/if}

                {if $descriptive}<div class="descriptive">{/if}

                <input type="checkbox" class="form-check-input" id="{$option->id()}" name="{$element->attrs()->name()}" value="{$option->value()}"{if $option->disabled()} disabled="disabled"{/if}{if $option->checked()} checked="checked"{/if} {$option->attributes()->toHtml()} />

                {if $descriptive}
                    {if $option->icon()}
                        <img class="img-fluid" style="max-width: 32px; height: auto;" src="{Image->adaptive file=$option->icon() size='128x128'}" alt="">
                    {/if}
                    <div class="d-flex flex-column">
                        <div class="fw-bold">{$option->label()}</div>
                        <div class="text-muted small">{$option->description()}</div>
                    </div>
                {else}
                    {$option->label()}
                {/if}

                {if $descriptive}</div>{/if}
            </label>
        </div>
    {/foreach}
{/block}
