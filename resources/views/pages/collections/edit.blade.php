<?php

use App\Concerns\CollectionValidationRules;
use App\Models\EndpointCollection;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Collection')] class extends Component {
    use CollectionValidationRules;

    public EndpointCollection $collection;

    public string $name = '';
    public string $description = '';

    public function mount(): void
    {
        abort_unless($this->collection->user_id === auth()->id(), 403);

        $this->name = $this->collection->name;
        $this->description = $this->collection->description ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate($this->collectionRules());

        $this->collection->update($validated);

        $this->redirectRoute('collections.show', $this->collection, navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('collections.show', $collection) }}" variant="ghost" icon="arrow-left" size="sm" />
        <flux:heading size="xl">Edit Collection</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-4">
        <flux:card class="space-y-4">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Optional description for this collection" />
                <flux:error name="description" />
            </flux:field>

            <div>
                <p class="mb-1 text-xs font-medium text-neutral-400">Slug</p>
                <code class="text-sm text-neutral-500">{{ $collection->slug }}</code>
                <p class="mt-1 text-xs text-neutral-400">The slug cannot be changed after creation.</p>
            </div>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('collections.show', $collection) }}" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary">Save Changes</flux:button>
        </div>
    </form>
</div>
