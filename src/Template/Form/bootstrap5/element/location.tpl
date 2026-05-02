{block name='input'}
    <div class="input-group">
        <span class="input-group-text">
            <i class="bi bi-geo-alt"></i>
        </span>

        <input type="text"
               data-location-map
               data-zoom="{$element->zoom()}"
               data-marker-position="{$element->markerPosition()}"
               data-autocomplete="{$element->autocomplete()}"
               id="{$element->attrs()->id()}"
               placeholder="{$element->attrs()->get('placeholder')}"
               name="{$element->attrs()->get('name-address-control')}"
               class="form-control {$element->attrs()->getClass()} form-control-location-map"
               value="{$element->getValueOutput()}" />

        <input type="hidden"
               id="{$element->attrs()->id()}-hidden"
               name="{$element->attrs()->name()}"
               value="{$element->getValueOutput()}" />

        <button type="button"
                class="btn btn-primary form-control-geolocation"
                data-location-geolocation="{$element->attrs()->id()}">
            <i class="bi bi-geo"></i> <span class="btn-location-text">{__('My Location')}</span>
        </button>
        {if !$element->inline()}
            <button type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modal-{$element->attrs()->id()}">
                <i class="bi bi-map"></i> <span class="btn-openmap-text">{__('Open map')}</span>
            </button>
        {/if}
    </div>

    {if $element->inline()}
        <div data-location-map-for="{$element->attrs()->id()}"
             class="location-map-container w-100 mt-2 rounded overflow-hidden"
             style="height:350px"></div>
    {else}
        <div class="modal fade" id="modal-{$element->attrs()->id()}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{__('Select location')}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{__('Close')}"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div data-location-map-for="{$element->attrs()->id()}"
                             class="location-map-container"
                             style="height:420px"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{__('Cancel')}</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{__('Use this location')}</button>
                    </div>
                </div>
            </div>
        </div>
    {/if}
{/block}
