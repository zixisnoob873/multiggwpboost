<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreBlogArticleRequest extends BlogArticleRequest
{
    protected function slugRule()
    {
        return Rule::unique('blog_articles', 'slug');
    }
}
