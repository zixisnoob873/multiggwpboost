@php
    $faqItems = old('faq_items', $blogArticle->faq_items ?? []);
    $faqItems = is_array($faqItems) && count($faqItems) > 0 ? $faqItems : [['question' => '', 'answer' => '']];
    $bodySections = old('body_sections', $bodySections ?? [['heading' => '', 'body' => '']]);
    $bodySections = is_array($bodySections) && count($bodySections) > 0 ? $bodySections : [['heading' => '', 'body' => '']];
    $tagsInput = old('tags_input', $blogArticle->tagList());
    $tagsInput = is_array($tagsInput) ? implode(', ', $tagsInput) : $tagsInput;
@endphp

<div class="row g-3 align-items-start ggwp-blog-admin-form">
    <div class="col-lg-8 d-grid gap-3 ggwp-blog-admin-form__column">
        <section class="card app-card admin-section-card">
            <div class="card-body">
                <h2 class="h6 mb-3">Article</h2>

                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="articleTitle">Title</label>
                        <input id="articleTitle" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $blogArticle->title) }}" maxlength="255" required>
                        @error('title')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleSlug">Slug</label>
                        <input id="articleSlug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $blogArticle->slug) }}" maxlength="255" required>
                        @error('slug')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="articleCategoryName">Category</label>
                        <input id="articleCategoryName" name="category_name" class="form-control @error('category_name') is-invalid @enderror" value="{{ old('category_name', $blogArticle->category_name) }}" maxlength="120">
                        @error('category_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="articleCategorySlug">Category Slug</label>
                        <input id="articleCategorySlug" name="category_slug" class="form-control @error('category_slug') is-invalid @enderror" value="{{ old('category_slug', $blogArticle->category_slug) }}" maxlength="120">
                        @error('category_slug')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleExcerpt">Excerpt</label>
                        <textarea id="articleExcerpt" name="excerpt" rows="3" class="form-control @error('excerpt') is-invalid @enderror" maxlength="600" required>{{ old('excerpt', $blogArticle->excerpt) }}</textarea>
                        @error('excerpt')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleIntro">Intro</label>
                        <textarea id="articleIntro" name="intro" rows="4" class="form-control @error('intro') is-invalid @enderror" required>{{ old('intro', $blogArticle->intro) }}</textarea>
                        @error('intro')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="card app-card admin-section-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">Sections</h2>
                    <button class="btn btn-outline-light btn-sm" type="button" data-blog-section-add>Add Section</button>
                </div>

                <div class="d-grid gap-3" data-blog-section-list>
                    @foreach($bodySections as $index => $section)
                        <div class="border rounded p-3 ggwp-inline-editor" data-blog-section-item>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label">Section Title</label>
                                    <input
                                        name="body_sections[{{ $index }}][heading]"
                                        class="form-control @error("body_sections.$index.heading") is-invalid @enderror"
                                        value="{{ data_get($section, 'heading') }}"
                                        maxlength="255"
                                    >
                                    @error("body_sections.$index.heading")
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Section Body</label>
                                    <textarea
                                        name="body_sections[{{ $index }}][body]"
                                        rows="7"
                                        class="form-control @error("body_sections.$index.body") is-invalid @enderror"
                                    >{{ data_get($section, 'body') }}</textarea>
                                    @error("body_sections.$index.body")
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button class="btn btn-outline-danger btn-sm" type="button" data-blog-section-remove>Remove</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template data-blog-section-template>
                    <div class="border rounded p-3 ggwp-inline-editor" data-blog-section-item>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Section Title</label>
                                <input name="body_sections[__INDEX__][heading]" class="form-control" maxlength="255">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Section Body</label>
                                <textarea name="body_sections[__INDEX__][body]" rows="7" class="form-control"></textarea>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <button class="btn btn-outline-danger btn-sm" type="button" data-blog-section-remove>Remove</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <section class="card app-card admin-section-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">FAQ</h2>
                    <button class="btn btn-outline-light btn-sm" type="button" data-blog-faq-add>Add FAQ Item</button>
                </div>

                <div class="d-grid gap-3" data-blog-faq-list>
                    @foreach($faqItems as $index => $faqItem)
                        <div class="border rounded p-3 ggwp-inline-editor" data-blog-faq-item>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label">Question</label>
                                    <input
                                        name="faq_items[{{ $index }}][question]"
                                        class="form-control @error("faq_items.$index.question") is-invalid @enderror"
                                        value="{{ data_get($faqItem, 'question') }}"
                                        maxlength="255"
                                    >
                                    @error("faq_items.$index.question")
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Answer</label>
                                    <textarea
                                        name="faq_items[{{ $index }}][answer]"
                                        rows="3"
                                        class="form-control @error("faq_items.$index.answer") is-invalid @enderror"
                                        maxlength="1000"
                                    >{{ data_get($faqItem, 'answer') }}</textarea>
                                    @error("faq_items.$index.answer")
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button class="btn btn-outline-danger btn-sm" type="button" data-blog-faq-remove>Remove</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template data-blog-faq-template>
                    <div class="border rounded p-3 ggwp-inline-editor" data-blog-faq-item>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Question</label>
                                <input name="faq_items[__INDEX__][question]" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Answer</label>
                                <textarea name="faq_items[__INDEX__][answer]" rows="3" class="form-control" maxlength="1000"></textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button class="btn btn-outline-danger btn-sm" type="button" data-blog-faq-remove>Remove</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>

    <div class="col-lg-4 d-grid gap-3 ggwp-blog-admin-form__column">
        <section class="card app-card admin-section-card">
            <div class="card-body">
                <h2 class="h6 mb-3">Publishing</h2>

                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="articleStatus">Status</label>
                        <select id="articleStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="draft" @selected(old('status', $blogArticle->status ?: 'draft') === 'draft')>Draft</option>
                            <option value="published" @selected(old('status', $blogArticle->status) === 'published')>Published</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articlePublishedAt">Publish Date</label>
                        <input
                            id="articlePublishedAt"
                            name="published_at"
                            type="datetime-local"
                            class="form-control @error('published_at') is-invalid @enderror"
                            value="{{ old('published_at', optional($blogArticle->published_at)->format('Y-m-d\\TH:i')) }}"
                        >
                        @error('published_at')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_in_sitemap" value="1" {{ old('include_in_sitemap', $blogArticle->include_in_sitemap ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Include in sitemap</span>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="card app-card admin-section-card">
            <div class="card-body">
                <h2 class="h6 mb-3">Discovery</h2>

                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="articleTags">Tags</label>
                        <input id="articleTags" name="tags_input" class="form-control @error('tags') is-invalid @enderror @error('tags.*') is-invalid @enderror" value="{{ $tagsInput }}" maxlength="500" placeholder="VALORANT, Ranked Tips">
                        @error('tags')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('tags.*')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleAuthorName">Author</label>
                        <input id="articleAuthorName" name="author_name" class="form-control @error('author_name') is-invalid @enderror" value="{{ old('author_name', $blogArticle->author_name) }}" maxlength="120" placeholder="GGWP-Boost Editorial Team">
                        @error('author_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleFeaturedImageUrl">Featured Image URL</label>
                        <input id="articleFeaturedImageUrl" name="featured_image_url" class="form-control @error('featured_image_url') is-invalid @enderror" value="{{ old('featured_image_url', $blogArticle->featured_image_url) }}" maxlength="2048">
                        @error('featured_image_url')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleFeaturedImageAlt">Featured Image Alt Text</label>
                        <input id="articleFeaturedImageAlt" name="featured_image_alt" class="form-control @error('featured_image_alt') is-invalid @enderror" value="{{ old('featured_image_alt', $blogArticle->featured_image_alt) }}" maxlength="255">
                        @error('featured_image_alt')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="card app-card admin-section-card">
            <div class="card-body">
                <h2 class="h6 mb-3">SEO</h2>

                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="articleMetaTitle">Meta Title</label>
                        <input id="articleMetaTitle" name="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $blogArticle->meta_title) }}" maxlength="255">
                        @error('meta_title')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleMetaDescription">Meta Description</label>
                        <textarea id="articleMetaDescription" name="meta_description" rows="3" class="form-control @error('meta_description') is-invalid @enderror" maxlength="130">{{ old('meta_description', $blogArticle->meta_description) }}</textarea>
                        @error('meta_description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleCanonicalUrl">Canonical URL</label>
                        <input id="articleCanonicalUrl" name="canonical_url" class="form-control @error('canonical_url') is-invalid @enderror" value="{{ old('canonical_url', $blogArticle->canonical_url) }}" maxlength="2048">
                        @error('canonical_url')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleRobots">Robots</label>
                        <select id="articleRobots" name="robots" class="form-select @error('robots') is-invalid @enderror">
                            <option value="" @selected(old('robots', $blogArticle->robots) === null || old('robots', $blogArticle->robots) === '')>Default index,follow</option>
                            <option value="index,follow" @selected(old('robots', $blogArticle->robots) === 'index,follow')>index,follow</option>
                            <option value="noindex,follow" @selected(old('robots', $blogArticle->robots) === 'noindex,follow')>noindex,follow</option>
                            <option value="noindex,nofollow" @selected(old('robots', $blogArticle->robots) === 'noindex,nofollow')>noindex,nofollow</option>
                        </select>
                        @error('robots')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="card app-card admin-section-card">
            <div class="card-body">
                <h2 class="h6 mb-3">CTA</h2>

                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="articleCtaLabel">CTA Label</label>
                        <input id="articleCtaLabel" name="cta_label" class="form-control @error('cta_label') is-invalid @enderror" value="{{ old('cta_label', $blogArticle->cta_label) }}" maxlength="255">
                        @error('cta_label')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="articleCtaUrl">CTA URL</label>
                        <input id="articleCtaUrl" name="cta_url" class="form-control @error('cta_url') is-invalid @enderror" value="{{ old('cta_url', $blogArticle->cta_url) }}" maxlength="2048">
                        @error('cta_url')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-danger" type="submit" data-busy-label="Saving...">{{ $submitLabel }}</button>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
  const list = document.querySelector('[data-blog-faq-list]');
  const template = document.querySelector('[data-blog-faq-template]');
  const addButton = document.querySelector('[data-blog-faq-add]');
  const sectionList = document.querySelector('[data-blog-section-list]');
  const sectionTemplate = document.querySelector('[data-blog-section-template]');
  const sectionAddButton = document.querySelector('[data-blog-section-add]');

  if (!list || !template || !addButton) {
    return;
  }

  const reindex = () => {
    list.querySelectorAll('[data-blog-faq-item]').forEach((item, index) => {
      item.querySelectorAll('input, textarea').forEach((field) => {
        field.name = field.name.replace(/faq_items\[\d+]/, `faq_items[${index}]`).replace(/faq_items\[__INDEX__]/, `faq_items[${index}]`);
      });
    });
  };

  addButton.addEventListener('click', () => {
    const markup = template.innerHTML.trim().replace(/__INDEX__/g, list.querySelectorAll('[data-blog-faq-item]').length);
    list.insertAdjacentHTML('beforeend', markup);
    reindex();
  });

  list.addEventListener('click', (event) => {
    const button = event.target.closest('[data-blog-faq-remove]');

    if (!button) {
      return;
    }

    const item = button.closest('[data-blog-faq-item]');

    if (!item) {
      return;
    }

    if (list.querySelectorAll('[data-blog-faq-item]').length === 1) {
      item.querySelectorAll('input, textarea').forEach((field) => {
        field.value = '';
      });

      return;
    }

    item.remove();
    reindex();
  });

  if (sectionList && sectionTemplate && sectionAddButton) {
    const reindexSections = () => {
      sectionList.querySelectorAll('[data-blog-section-item]').forEach((item, index) => {
        item.querySelectorAll('input, textarea').forEach((field) => {
          field.name = field.name.replace(/body_sections\[\d+]/, `body_sections[${index}]`).replace(/body_sections\[__INDEX__]/, `body_sections[${index}]`);
        });
      });
    };

    sectionAddButton.addEventListener('click', () => {
      const markup = sectionTemplate.innerHTML.trim().replace(/__INDEX__/g, sectionList.querySelectorAll('[data-blog-section-item]').length);
      sectionList.insertAdjacentHTML('beforeend', markup);
      reindexSections();
    });

    sectionList.addEventListener('click', (event) => {
      const button = event.target.closest('[data-blog-section-remove]');

      if (!button) {
        return;
      }

      const item = button.closest('[data-blog-section-item]');

      if (!item) {
        return;
      }

      if (sectionList.querySelectorAll('[data-blog-section-item]').length === 1) {
        item.querySelectorAll('input, textarea').forEach((field) => {
          field.value = '';
        });

        return;
      }

      item.remove();
      reindexSections();
    });
  }
})();
</script>
@endpush
