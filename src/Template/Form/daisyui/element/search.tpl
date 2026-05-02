{block name='element'}
    <div class="join w-full">
        <input type="search" class="input input-bordered join-item w-full" {$element->attrs()} />
        <button class="btn btn-primary join-item" type="submit">{$element->label()}</button>
    </div>
{/block}
