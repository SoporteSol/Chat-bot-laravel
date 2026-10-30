@props(['title' => null, 'tools' => null])

<div {{ $attributes->merge(['class' => 'app-card']) }}>
    @if ($title || isset($tools))
        <div class="app-card-header">
            @if ($title)
                <h3 class="app-card-title">{{ $title }}</h3>
            @endif
            @isset($tools)
                <div class="app-card-tools">
                    {{ $tools }}
                </div>
            @endisset
        </div>
    @endif
    <div class="app-card-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="app-card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
