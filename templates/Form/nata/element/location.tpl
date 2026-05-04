{block name='input'}
    <div class="flex w-full [&>*]:rounded-none [&>:first-child]:rounded-l-lg [&>:last-child]:rounded-r-lg [&>*:not(:first-child)]:-ml-px">
        <span class="shrink-0 inline-flex items-center px-3 py-2.5 bg-gray-100 border border-gray-300 text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        </span>

        <input type="text"
               data-zoom="{$element->zoom()}"
               data-inline="{if $element->inline()}1{else}0{/if}"
               data-marker-position="{$element->markerPosition()}"
               data-autocomplete="{$element->autocomplete()}"
               id="{$element->attrs()->id()}"
               placeholder="{$element->attrs()->get('placeholder')}"
               name="{$element->attrs()->get('name-address-control')}"
               class="{$element->attrs()->getClass()} flex-1 min-w-0 px-3 py-2.5 text-sm bg-white text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 relative focus:z-10 transition form-control-location-map"
               value="{$element->getValueOutput()}" />

        <button type="button"
                class="shrink-0 inline-flex items-center px-3 py-2.5 text-white bg-blue-600 border border-blue-600 hover:bg-blue-700 transition cursor-pointer form-control-geolocation"
                data-location-geolocation="{$element->attrs()->id()}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m13.5 0a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" /></svg>
        </button>

        {if $element->inline()}
            <button type="button"
                    class="shrink-0 inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium text-white bg-blue-600 border border-blue-600 hover:bg-blue-700 transition cursor-pointer"
                    data-location-toggle="{$element->attrs()->id()}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.196-5.196a3 3 0 001.5-2.598V6a3 3 0 013-3h8.196A2.99 2.99 0 0115 6v6.207a3 3 0 001.5 2.598L21 20" /></svg>
                <span>{__('Show map')}</span>
            </button>
        {else}
            <button type="button"
                    class="shrink-0 inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium text-white bg-blue-600 border border-blue-600 hover:bg-blue-700 transition cursor-pointer"
                    onclick="document.getElementById('modal-{$element->attrs()->id()}').showModal()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.196-5.196a3 3 0 001.5-2.598V6a3 3 0 013-3h8.196A2.99 2.99 0 0115 6v6.207a3 3 0 001.5 2.598L21 20" /></svg>
                <span class="btn-openmap-text">{__('Open map')}</span>
            </button>
        {/if}
    </div>

    <input type="hidden"
           id="{$element->attrs()->id()}-hidden"
           name="{$element->attrs()->name()}"
           value="{$element->getValueOutput()}" />

    {if $element->inline()}
        <div data-location-map-for="{$element->attrs()->id()}"
             class="location-map-container w-full mt-2 rounded-xl overflow-hidden"
             style="height:350px"
             hidden></div>
    {else}
        <dialog id="modal-{$element->attrs()->id()}" class="rounded-2xl shadow-2xl p-0 w-full max-w-3xl backdrop:bg-black/50" onclick="if(event.target===this)this.close()">
            <div class="bg-white rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">{__('Select location')}</h3>
                    <button type="button" class="p-1.5 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition cursor-pointer" onclick="this.closest('dialog').close()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-4 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div data-location-map-for="{$element->attrs()->id()}"
                     class="location-map-container w-full"
                     style="height:420px"></div>
                <div class="flex items-center justify-between px-5 py-4 border-t border-gray-200">
                    <button type="button"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-100 transition cursor-pointer form-control-geolocation"
                            data-location-geolocation="{$element->attrs()->id()}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="size-5 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m13.5 0a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" /></svg>
                        {__('My Location')}
                    </button>
                    <div class="flex gap-2">
                        <button type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition cursor-pointer" onclick="this.closest('dialog').close()">{__('Cancel')}</button>
                        <button type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition cursor-pointer" data-location-confirm>{__('Use this location')}</button>
                    </div>
                </div>
            </div>
        </dialog>
    {/if}
{/block}
