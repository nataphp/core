{block name='element'}
    <div class="flex w-full [&>*]:rounded-none [&>:first-child]:rounded-l-lg [&>:last-child]:rounded-r-lg [&>*:not(:first-child)]:-ml-px">
        <input type="search" class="flex-1 min-w-0 px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 relative focus:z-10 transition placeholder:text-gray-400" {$element->attrs()} />
        <button class="shrink-0 inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 border border-blue-600 hover:bg-blue-700 transition cursor-pointer" type="submit">{$element->label()}</button>
    </div>
{/block}
