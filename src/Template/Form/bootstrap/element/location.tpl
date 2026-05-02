{block name='input'}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-map-marker"></i>
        </span>

        <input type="text"
               data-inline="{$element->inline()}"
               data-zoom="{$element->zoom()}"
               data-marker-position="{$element->markerPosition()}"
               data-autocomplete="{$element->autocomplete()}"
               id="{$element->attrs()->id()}"
               placeholder="{$element->attrs()->get('placeholder')}"
               name="{$element->attrs()->get('name-address-control')}"
               class="{$element->attrs()->getClass()} form-control-location-map"
               value="{$element->getValueOutput()}" />

        <input type="hidden"
               id="{$element->attrs()->id()}-hidden"
               name="{$element->attrs()->get('name-coords-control')}"
               value="{$element->getValueOutput()}" />

        <span class="input-group-btn">
            <button type="button" class="btn btn-primary form-control-geolocation">
                <i class="fa fa-crosshairs"></i> <span class="btn-location-text">{__('My Location')}</span>
            </button>
            {if !$element->inline()}
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-{$element->attrs()->id()}">
                    <i class="fa fa-map"></i> <span class="btn-openmap-text">{__('Open map')}</span>
                </button>
            {/if}
        </span>
    </div><!-- /input-group -->
{/block}
