@php
    use Illuminate\Support\Str;

    $robotsValue = old('robots', $pageRecord?->robots);
@endphp

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        Please review the highlighted fields and save again.
    </div>
@endif

<div class="row g-4 align-items-start">
    <div class="col-lg-8 d-grid gap-3">
        @foreach($pageDefinition['sections'] as $section)
            <div class="card app-card ggwp-panel-card">
                <div class="card-body">
                    <div class="mb-3">
                        <h2 class="h4 mb-1">{{ $section['title'] }}</h2>
                        @if(! empty($section['description']))
                            <p class="text-secondary mb-0">{{ $section['description'] }}</p>
                        @endif
                    </div>

                    <div class="row g-3">
                        @foreach($section['fields'] as $field)
                            @php
                                $fieldId = 'page-'.Str::slug($pageDefinition['key'].'-'.$field['name']);
                                $fieldPath = 'content.'.$field['name'];
                                $fieldName = 'content['.str_replace('.', '][', $field['name']).']';
                                $fieldValue = old($fieldPath, data_get($pageContent, $field['name']));
                            @endphp

                            @if($field['type'] === 'repeater')
                                @php
                                    $items = old($fieldPath, data_get($pageContent, $field['name'], []));
                                    $items = is_array($items) && count($items) > 0 ? $items : [[]];
                                @endphp
                                <div class="col-12" data-cms-repeater data-repeater-base="{{ $fieldName }}">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <div>
                                            <label class="form-label mb-0">{{ $field['label'] }}</label>
                                            @if(! empty($field['help']))
                                                <div class="form-text">{{ $field['help'] }}</div>
                                            @endif
                                        </div>
                                        <button class="btn btn-outline-light btn-sm" type="button" data-cms-repeater-add>Add Item</button>
                                    </div>

                                    <div class="d-grid gap-3" data-cms-repeater-list>
                                        @foreach($items as $index => $item)
                                            <div class="border rounded p-3 ggwp-inline-editor" data-cms-repeater-item>
                                                <div class="row g-3">
                                                    @foreach($field['fields'] as $childField)
                                                        @php
                                                            $childName = $fieldName.'['.$index.']['.str_replace('.', '][', $childField['name']).']';
                                                            $childPath = $fieldPath.'.'.$index.'.'.$childField['name'];
                                                            $childValue = data_get($item, $childField['name']);
                                                        @endphp
                                                        <div class="col-12">
                                                            <label class="form-label">{{ $childField['label'] }}</label>
                                                            @if($childField['type'] === 'textarea')
                                                                <textarea
                                                                    class="form-control @error($childPath) is-invalid @enderror"
                                                                    name="{{ $childName }}"
                                                                    rows="{{ $childField['rows'] ?? 3 }}"
                                                                    maxlength="{{ $childField['maxlength'] ?? 1000 }}"
                                                                    data-cms-field-suffix="[{{ str_replace('.', '][', $childField['name']) }}]"
                                                                >{{ $childValue }}</textarea>
                                                            @else
                                                                <input
                                                                    type="{{ $childField['type'] === 'url' ? 'url' : 'text' }}"
                                                                    class="form-control @error($childPath) is-invalid @enderror"
                                                                    name="{{ $childName }}"
                                                                    value="{{ $childValue }}"
                                                                    maxlength="{{ $childField['maxlength'] ?? 255 }}"
                                                                    data-cms-field-suffix="[{{ str_replace('.', '][', $childField['name']) }}]"
                                                                >
                                                            @endif
                                                            @if(! empty($childField['help']))
                                                                <div class="form-text">{{ $childField['help'] }}</div>
                                                            @endif
                                                            @error($childPath)
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    @endforeach
                                                    <div class="col-12 d-flex justify-content-end">
                                                        <button class="btn btn-outline-danger btn-sm" type="button" data-cms-repeater-remove>Remove</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <template data-cms-repeater-template>
                                        <div class="border rounded p-3 ggwp-inline-editor" data-cms-repeater-item>
                                            <div class="row g-3">
                                                @foreach($field['fields'] as $childField)
                                                    <div class="col-12">
                                                        <label class="form-label">{{ $childField['label'] }}</label>
                                                        @if($childField['type'] === 'textarea')
                                                            <textarea
                                                                class="form-control"
                                                                rows="{{ $childField['rows'] ?? 3 }}"
                                                                maxlength="{{ $childField['maxlength'] ?? 1000 }}"
                                                                data-cms-field-suffix="[{{ str_replace('.', '][', $childField['name']) }}]"
                                                            ></textarea>
                                                        @else
                                                            <input
                                                                type="{{ $childField['type'] === 'url' ? 'url' : 'text' }}"
                                                                class="form-control"
                                                                maxlength="{{ $childField['maxlength'] ?? 255 }}"
                                                                data-cms-field-suffix="[{{ str_replace('.', '][', $childField['name']) }}]"
                                                            >
                                                        @endif
                                                        @if(! empty($childField['help']))
                                                            <div class="form-text">{{ $childField['help'] }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                <div class="col-12 d-flex justify-content-end">
                                                    <button class="btn btn-outline-danger btn-sm" type="button" data-cms-repeater-remove>Remove</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            @else
                                <div class="col-12">
                                    <label class="form-label" for="{{ $fieldId }}">{{ $field['label'] }}</label>
                                    @if($field['type'] === 'textarea')
                                        <textarea
                                            id="{{ $fieldId }}"
                                            class="form-control @error($fieldPath) is-invalid @enderror"
                                            name="{{ $fieldName }}"
                                            rows="{{ $field['rows'] ?? 3 }}"
                                            maxlength="{{ $field['maxlength'] ?? 1000 }}"
                                        >{{ $fieldValue }}</textarea>
                                    @else
                                        <input
                                            id="{{ $fieldId }}"
                                            type="{{ $field['type'] === 'url' ? 'url' : 'text' }}"
                                            class="form-control @error($fieldPath) is-invalid @enderror"
                                            name="{{ $fieldName }}"
                                            value="{{ $fieldValue }}"
                                            maxlength="{{ $field['maxlength'] ?? 255 }}"
                                        >
                                    @endif
                                    @if(! empty($field['help']))
                                        <div class="form-text">{{ $field['help'] }}</div>
                                    @endif
                                    @error($fieldPath)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="col-lg-4 d-grid gap-3">
        <div class="card app-card ggwp-panel-card">
            <div class="card-body">
                <h2 class="h4 mb-3">Publishing</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Public Route</label>
                        <input class="form-control" value="{{ $pagePath }}" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_in_sitemap" value="1" {{ old('include_in_sitemap', $pageRecord?->include_in_sitemap ?? $pageDefinition['seo']['include_in_sitemap'] ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Include in sitemap</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card app-card ggwp-panel-card">
            <div class="card-body">
                <h2 class="h4 mb-3">SEO</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="pageMetaTitle">Meta Title</label>
                        <input id="pageMetaTitle" name="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $pageRecord?->meta_title ?? $pageDefinition['seo']['title'] ?? '') }}" maxlength="255">
                        @error('meta_title')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pageMetaDescription">Meta Description</label>
                        <textarea id="pageMetaDescription" name="meta_description" rows="3" class="form-control @error('meta_description') is-invalid @enderror" maxlength="130">{{ old('meta_description', $pageRecord?->meta_description ?? $pageDefinition['seo']['description'] ?? '') }}</textarea>
                        @error('meta_description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pageCanonicalUrl">Canonical URL</label>
                        <input id="pageCanonicalUrl" name="canonical_url" class="form-control @error('canonical_url') is-invalid @enderror" value="{{ old('canonical_url', $pageRecord?->canonical_url) }}" maxlength="2048">
                        @error('canonical_url')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pageRobots">Robots</label>
                        <select id="pageRobots" name="robots" class="form-select @error('robots') is-invalid @enderror">
                            <option value="" @selected($robotsValue === null || $robotsValue === '')>Default index,follow</option>
                            <option value="index,follow" @selected($robotsValue === 'index,follow')>index,follow</option>
                            <option value="noindex,follow" @selected($robotsValue === 'noindex,follow')>noindex,follow</option>
                            <option value="noindex,nofollow" @selected($robotsValue === 'noindex,nofollow')>noindex,nofollow</option>
                        </select>
                        @error('robots')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex flex-wrap gap-2">
        <button class="btn btn-danger" type="submit">Save Page</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin-pages.index') }}">Cancel</a>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
  document.querySelectorAll('[data-cms-repeater]').forEach((repeater) => {
    const baseName = repeater.getAttribute('data-repeater-base');
    const list = repeater.querySelector('[data-cms-repeater-list]');
    const template = repeater.querySelector('[data-cms-repeater-template]');
    const addButton = repeater.querySelector('[data-cms-repeater-add]');

    if (!baseName || !list || !template || !addButton) {
      return;
    }

    const reindex = () => {
      list.querySelectorAll('[data-cms-repeater-item]').forEach((item, index) => {
        item.querySelectorAll('[data-cms-field-suffix]').forEach((field) => {
          field.name = `${baseName}[${index}]${field.getAttribute('data-cms-field-suffix')}`;
        });
      });
    };

    addButton.addEventListener('click', () => {
      list.insertAdjacentHTML('beforeend', template.innerHTML.trim());
      reindex();
    });

    list.addEventListener('click', (event) => {
      const button = event.target.closest('[data-cms-repeater-remove]');

      if (!button) {
        return;
      }

      const item = button.closest('[data-cms-repeater-item]');

      if (!item) {
        return;
      }

      if (list.querySelectorAll('[data-cms-repeater-item]').length === 1) {
        item.querySelectorAll('input, textarea').forEach((field) => {
          field.value = '';
        });
        reindex();

        return;
      }

      item.remove();
      reindex();
    });

    reindex();
  });
})();
</script>
@endpush
