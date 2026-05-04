{block name='element'}
    <div class="radio-group">
        {foreach $element->options()->get() as $option}
            {assign var=descriptive value=$option->description() || $option->icon()}

            {if !$element->inline()}<div class="radio{if $option->disabled()} disabled{/if}">{/if}
                <label class="{if $element->inline()}radio-inline{/if}">
                    {if $descriptive}<div class="descriptive">{/if}

                    <input type="radio" id="{$option->id()}" name="{$element->attrs()->name()}" value="{$option->value()}"{if $option->disabled()} disabled="disabled"{/if}{if $option->checked()} checked="checked"{/if} {$option->attributes()->toHtml()} />

                    {if $descriptive}
                        {if $option->icon()}
                            <img class="label-icon" src="{Image->adaptive file=$option->icon() size='128x128'}" alt="">
                        {/if}
                        <div class="label-description-container">
                            <div class="label-text">{$option->label()}</div>
                            <div class="label-description text-muted"><small>{$option->description()}</small></div>
                        </div>
                    {else}
                        {$option->label()}
                    {/if}

                    {if $descriptive}</div>{/if}
                </label>
            {if !$element->inline()}</div>{/if}
        {/foreach}
    </div>
{/block}
