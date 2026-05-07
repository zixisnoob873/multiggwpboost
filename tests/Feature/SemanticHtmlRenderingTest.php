<?php

namespace Tests\Feature;

use Database\Seeders\BlogArticleSeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticHtmlRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_pages_expose_semantic_page_structure(): void
    {
        $this->seed([FaqSeeder::class, ReviewSeeder::class, BlogArticleSeeder::class]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<header class="col-lg-7">', false)
            ->assertSee('<aside class="col-lg-5"', false)
            ->assertSee('<article class="ggwp-trust-strip__item">', false)
            ->assertSee('<section class="ggwp-service-pricing-grid__config"', false);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('<header class="col-lg-8">', false)
            ->assertSee('<aside class="col-lg-4"', false)
            ->assertSee('<article class="accordion-item">', false);

        $this->get(route('reviews'))
            ->assertOk()
            ->assertSee('<figure class="card app-card ggwp-panel-card h-100 mb-0">', false)
            ->assertSee('<blockquote class="mb-0 flex-grow-1">', false)
            ->assertSee('<figcaption>', false);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<section class="app-card ggwp-contact-info"', false)
            ->assertSee('<article class="ggwp-info-section">', false);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('<section aria-labelledby="checkoutContactHeading">', false)
            ->assertSee('<fieldset class="d-grid gap-2">', false)
            ->assertSee('<dl id="orderSummaryDetails"', false);
    }

    public function test_blog_pages_use_semantic_headers_asides_and_navigation(): void
    {
        $this->seed(BlogArticleSeeder::class);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('<header class="ggwp-blog-hero ggwp-public-hero card app-card ggwp-panel-card">', false)
            ->assertSee('<aside class="ggwp-blog-hero__aside"', false);

        $this->get(route('blog.show', ['slug' => 'is-valorant-boosting-safe']))
            ->assertOk()
            ->assertSee('<aside class="col-xl-4"', false)
            ->assertSee('<nav class="card app-card ggwp-panel-card"', false)
            ->assertSee('<nav class="ggwp-blog-related"', false)
            ->assertSee('<article class="accordion-item">', false);
    }
}
