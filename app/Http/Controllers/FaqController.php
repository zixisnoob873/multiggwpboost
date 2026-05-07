<?php

namespace App\Http\Controllers;

use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /**
     * Return FAQs ordered for the frontend accordion.
     */
    public function index(): JsonResponse
    {
        $faqs = Faq::orderBy('order')->get(['question', 'answer']);

        return response()->json([
            'faqs' => FaqResource::collection($faqs)->resolve(),
        ]);
    }
}
