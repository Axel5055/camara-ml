<script>
    // The server answers 423 to anything done while a temperature is pending: show the modal instead of an error.
    document.addEventListener('livewire:init', () => {
        window.Livewire.interceptRequest(({ onError }) => onError(({ response, preventDefault }) => {
            if (response.status === 423) {
                preventDefault();
                window.Livewire.dispatch('temperature-required');
            }
        }));
    }, { once: true });
</script>

<livewire:temperatures.temperature-gate :standalone="$standalone ?? false" />

@unless ($standalone ?? false)
    <livewire:temperatures.temperature-reminder />
@endunless
