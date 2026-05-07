<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoosterApplicationRequest;
use App\Models\BoosterApplication;
use App\Support\Cms\PageContentService;
use App\Support\Seo\StructuredDataBuilder;
use App\Services\Discord\DiscordNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BoosterApplicationController extends Controller
{
    public function __construct(
        protected DiscordNotifier $discordNotifier,
        protected PageContentService $pageContentService,
        protected StructuredDataBuilder $structuredData,
    ) {}

    public function create(): View
    {
        $pageContent = $this->pageContentService->publicContent('become-booster');
        $seo = $this->pageContentService->seo('become-booster');
        $seo['schema'] = $this->structuredData->becomeBooster(
            $pageContent,
            $seo,
            $this->pageContentService->page('become-booster')?->updated_at
        );

        return view('public.become-booster', [
            'pageContent' => $pageContent,
            'seo' => $seo,
        ]);
    }

    public function store(BoosterApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $shouldNotifyDiscord = $this->discordNotifier->hasBoosterApplicationWebhook();

        $application = BoosterApplication::create([
            'name' => $data['name'],
            'nickname' => $data['nickname'],
            'email' => $data['email'],
            'current_rank' => $data['current_rank'],
            'peak_rank' => $data['peak_rank'],
            'average_time' => $data['average_time'],
            'discord' => $data['discord'],
            'main_account_tracker' => $data['main_account_tracker'],
            'marketplace_profile' => $data['marketplace_profile'] ?? null,
            'regions' => $data['regions'],
            'status' => 'new',
        ]);

        if ($shouldNotifyDiscord) {
            $this->discordNotifier->queueBoosterApplication($application);
        }

        return redirect()->route('become-booster')->with('status', 'Your booster application has been sent.');
    }
}
