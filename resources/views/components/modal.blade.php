@props(['id', 'title' => '', 'size' => ''])

<div class="custom-modal-backdrop" id="{{ $id }}" style="display: none;">
    <div class="custom-modal-dialog {{ $size ? 'modal-' . $size : '' }}">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">{{ $title }}</h5>
                <button type="button" class="custom-modal-close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="custom-modal-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="custom-modal-footer">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
