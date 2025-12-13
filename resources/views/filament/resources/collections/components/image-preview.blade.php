@php
    $record = $getRecord();
    $imageUrl = $record?->image_url ?? $get('image_url');
@endphp

@if($imageUrl)
    <div class="space-y-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Image</label>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800">
            <img 
                src="{{ $imageUrl }}" 
                alt="Collection image" 
                class="max-w-full h-auto rounded-lg shadow-sm"
                style="max-height: 300px; object-fit: contain;"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            />
            <div style="display: none;" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                Image failed to load. URL: <a href="{{ $imageUrl }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $imageUrl }}</a>
            </div>
        </div>
    </div>
@endif
