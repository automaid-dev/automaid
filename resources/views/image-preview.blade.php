@if ($hasLandmarkPicture)
    @php
        $url = $record?->booking?->landmark_picture_url ?? null;
    @endphp
    <div>
        @if ($url)
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                <img src="{{ $url }}" alt="Landmark Picture" style="max-width: 25%; border-radius: 8px;" />
            </a>
        @else
            <p style="color: gray;">No image available</p>
        @endif
    </div>
@endif