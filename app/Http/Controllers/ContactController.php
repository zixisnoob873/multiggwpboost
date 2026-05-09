<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Services\Discord\DiscordNotifier;
use App\Support\Cms\PageContentService;
use App\Support\Seo\StructuredDataBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        protected DiscordNotifier $discordNotifier,
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function contact(): View
    {
        $pageContent = $this->pageContentService->publicContent('contact');
        $seo = $this->pageContentService->seo('contact');
        $seo['schema'] = $this->structuredData->contact(
            $pageContent,
            $seo,
            $this->pageContentService->page('contact')?->updated_at
        );

        return view('contact', [
            'pageContent' => $pageContent,
            'seo' => $seo,
        ]);
    }

    public function submit(ContactRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $shouldNotifyDiscord = $this->discordNotifier->hasContactWebhook();

        $contactMessage = ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'order_ref' => $data['order_reference'] ?? null,
            'message' => $data['message'],
            'status' => $shouldNotifyDiscord ? 'queued' : 'received',
        ]);

        if ($shouldNotifyDiscord) {
            $this->discordNotifier->queueContactMessage($contactMessage);
        }

        return redirect()
            ->route('contact')
            ->with('status', 'Message sent successfully. Our team will get back to you shortly.')
            ->with('analyticsEvents', [[
                'name' => 'contact_form_submission',
                'payload' => [
                    'context' => 'contact_form',
                    'has_order_reference' => filled($data['order_reference'] ?? null),
                ],
            ]]);
    }
}
