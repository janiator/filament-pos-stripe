<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="fi-form-actions">
            <div class="flex flex-wrap items-center gap-4">
                @foreach($this->getCachedFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </div>
    </form>
</x-filament-panels::page>
