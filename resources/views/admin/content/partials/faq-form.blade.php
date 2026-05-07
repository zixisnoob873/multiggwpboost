@php
    $formContext = $formContext ?? ($faq?->exists ? 'faq-'.$faq->id : 'faq-create');
    $modalId = $modalId ?? $formContext;
    $useOldInput = old('faq_context') === $formContext;
    $faqInput = $useOldInput ? (array) old('faq', []) : [];
@endphp

<form
    action="{{ $action }}"
    method="POST"
    class="row g-2{{ empty($wrapperClass) ? '' : ' '.$wrapperClass }}"
    data-loading-form
    data-validate-form
    data-modal-reset-form
    novalidate
>
    @csrf
    @if (! empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <input type="hidden" name="modal_id" value="{{ $modalId }}">
    <input type="hidden" name="faq_context" value="{{ $formContext }}">

    <div class="col-md-4">
        <label class="form-label" for="faqQuestion{{ $formContext }}">Question</label>
        <input
            id="faqQuestion{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('question') ? 'is-invalid' : '' }}"
            name="faq[question]"
            value="{{ data_get($faqInput, 'question', $faq->question ?? '') }}"
            maxlength="255"
            required
        >
        @if($useOldInput)
            @error('question')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label" for="faqAnswer{{ $formContext }}">Answer</label>
        <textarea
            id="faqAnswer{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('answer') ? 'is-invalid' : '' }}"
            name="faq[answer]"
            rows="3"
            maxlength="2000"
            required
        >{{ data_get($faqInput, 'answer', $faq->answer ?? '') }}</textarea>
        @if($useOldInput)
            @error('answer')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-2">
        <label class="form-label" for="faqOrder{{ $formContext }}">Order</label>
        <input
            id="faqOrder{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('order') ? 'is-invalid' : '' }}"
            type="number"
            min="0"
            max="9999"
            name="faq[order]"
            value="{{ data_get($faqInput, 'order', $faq->order ?? '') }}"
            required
        >
        @if($useOldInput)
            @error('order')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
        <button class="btn {{ $submitClass ?? 'btn-outline-light btn-sm' }}" type="submit" data-busy-label="Saving...">{{ $submitLabel }}</button>
    </div>
</form>
