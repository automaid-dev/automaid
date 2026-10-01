@php
    // State path of this ViewField, e.g. "data.location_picker" (create)
    // or "data.merchant.location_picker" (edit) -> base "data." / "data.merchant."
    $base = \Illuminate\Support\Str::beforeLast($getStatePath(), 'location_picker');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if (blank($apiKey))
        <div style="padding:12px;border:1px dashed #f59e0b;border-radius:8px;color:#92400e;background:#fffbeb;font-size:13px;">
            Map unavailable: <code>GOOGLE_MAPS_BROWSER_KEY</code> is not set in <code>.env</code>.
            You can still type the latitude and longitude below (e.g. from Google Maps → right-click → copy coordinates).
        </div>
    @else
        <div
            wire:ignore
            x-data="{
                base: @js($base),
                apiKey: @js($apiKey),
                states: @js($states),
                map: null,
                marker: null,
                geocoder: null,
                query: '',
                status: '',

                async init() {
                    await this.loadGoogle();
                    const lat = parseFloat(this.$wire.get(this.base + 'latitude'));
                    const lng = parseFloat(this.$wire.get(this.base + 'longitude'));
                    const hasPin = !isNaN(lat) && !isNaN(lng);
                    const center = hasPin ? { lat, lng } : { lat: 3.139, lng: 101.6869 }; // Kuala Lumpur

                    this.map = new google.maps.Map(this.$refs.map, {
                        center, zoom: hasPin ? 17 : 11,
                        mapTypeControl: false, streetViewControl: false,
                    });
                    this.geocoder = new google.maps.Geocoder();
                    this.marker = new google.maps.Marker({ map: this.map, position: center, draggable: true, visible: hasPin });

                    this.marker.addListener('dragend', (e) => this.setPin(e.latLng, false));
                    this.map.addListener('click', (e) => this.setPin(e.latLng, false));
                    this.status = hasPin ? 'Pin loaded from saved location. Drag it to adjust.' : 'Search, or click the map to drop a pin.';
                },

                loadGoogle() {
                    if (window.google?.maps?.Geocoder) return Promise.resolve();
                    if (!window.__automaidMapsLoading) {
                        window.__automaidMapsLoading = new Promise((resolve, reject) => {
                            window.__automaidMapsReady = resolve;
                            const s = document.createElement('script');
                            s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(this.apiKey)
                                + '&loading=async&callback=__automaidMapsReady';
                            s.async = true;
                            s.onerror = () => reject(new Error('Google Maps failed to load'));
                            document.head.appendChild(s);
                        });
                    }
                    return window.__automaidMapsLoading;
                },

                setPin(latLng, pan = true) {
                    this.marker.setPosition(latLng);
                    this.marker.setVisible(true);
                    if (pan) { this.map.panTo(latLng); this.map.setZoom(17); }
                    const lat = +latLng.lat().toFixed(7), lng = +latLng.lng().toFixed(7);
                    this.$wire.set(this.base + 'latitude', lat);
                    this.$wire.set(this.base + 'longitude', lng);
                    this.status = 'Pin set: ' + lat + ', ' + lng;
                },

                matchState(name) {
                    if (!name) return null;
                    const norm = (s) => s.toLowerCase()
                        .replace('federal territory of', '').replace('wilayah persekutuan', '')
                        .replace(/[^a-z]/g, '');
                    const n = norm(name);
                    for (const [id, label] of Object.entries(this.states)) {
                        const l = norm(label);
                        if (l && (l === n || l.includes(n) || n.includes(l))) return id;
                    }
                    return null;
                },

                fillFromResult(result) {
                    const get = (type) => result.address_components.find(c => c.types.includes(type))?.long_name;
                    const postcode = get('postal_code');
                    const city = get('locality') || get('sublocality') || get('administrative_area_level_2');
                    const stateId = this.matchState(get('administrative_area_level_1'));
                    if (postcode) this.$wire.set(this.base + 'postcode', postcode);
                    if (city) this.$wire.set(this.base + 'city', city);
                    if (stateId) this.$wire.set(this.base + 'state_id', stateId);
                    if (!this.$wire.get(this.base + 'address_line_1')) {
                        const street = [get('street_number'), get('route')].filter(Boolean).join(' ');
                        if (street) this.$wire.set(this.base + 'address_line_1', street);
                    }
                },

                geocode(address, fill) {
                    if (!address) { this.status = 'Type an address first.'; return; }
                    this.status = 'Searching…';
                    this.geocoder.geocode({ address, region: 'my' }, (results, st) => {
                        if (st !== 'OK' || !results.length) { this.status = 'Not found (' + st + '). Try a more specific address, or click the map.'; return; }
                        this.setPin(results[0].geometry.location);
                        if (fill) this.fillFromResult(results[0]);
                        this.status = 'Found: ' + results[0].formatted_address + '. Drag the pin to the exact entrance if needed.';
                    });
                },

                searchBox() { this.geocode(this.query, true); },

                useAddressFields() {
                    const parts = ['unit_no', 'block', 'address_line_1', 'address_line_2', 'postcode', 'city']
                        .map(f => this.$wire.get(this.base + f)).filter(Boolean);
                    const stateName = this.states[this.$wire.get(this.base + 'state_id')];
                    if (stateName) parts.push(stateName);
                    parts.push('Malaysia');
                    this.geocode(parts.join(', '), false);
                },
            }"
        >
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <input
                    type="text"
                    x-model="query"
                    x-on:keydown.enter.prevent="searchBox()"
                    placeholder="Search place or address, e.g. Dobi Hana SS2 Petaling Jaya"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                    style="flex:1;min-width:220px;padding:8px 10px;"
                />
                <button type="button" x-on:click="searchBox()"
                    class="fi-btn rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background:#f59e0b;">
                    Search
                </button>
                <button type="button" x-on:click="useAddressFields()"
                    class="fi-btn rounded-lg px-3 py-2 text-sm font-semibold" style="border:1px solid #d1d5db;">
                    Use address above
                </button>
            </div>
            <div x-ref="map" style="height:360px;width:100%;border-radius:8px;border:1px solid #e5e7eb;"></div>
            <p x-text="status" style="margin-top:6px;font-size:12px;color:#6b7280;"></p>
        </div>
    @endif
</x-dynamic-component>
