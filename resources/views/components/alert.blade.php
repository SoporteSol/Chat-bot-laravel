@props(['type' => 'info', 'dismissible' => true])

<div {{ $attributes->merge(['class' => 'alert alert-' . $type . ($dismissible ? ' alert-dismissible' : '')]) }} role="alert">
    {{ $slot }}
    @if ($dismissible)
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    @endif
</div>
