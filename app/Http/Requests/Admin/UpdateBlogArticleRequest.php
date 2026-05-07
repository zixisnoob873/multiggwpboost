<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogArticle;
use Illuminate\Validation\Rule;

class UpdateBlogArticleRequest extends BlogArticleRequest
{
    protected function slugRule()
    {
        /** @var BlogArticle|null $blogArticle */
        $blogArticle = $this->route('blogArticle');

        return Rule::unique('blog_articles', 'slug')->ignore($blogArticle?->id);
    }
}
