{!! $this->inertiaHead() !!}

<x-template :if="!$this->ssrHead">
    <x-slot />
</x-template>
