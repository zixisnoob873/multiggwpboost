<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entries = [
            [
                'question' => 'How long does boosting take?',
                'answer' => 'Each VALORANT boost shows an estimated completion time before checkout, based on rank gap, RR gain, service type, and selected options.',
                'order' => 1,
            ],
            [
                'question' => 'Can I choose Duo / Self-Play?',
                'answer' => 'Yes. Duo, also known as Self-Play, lets you play alongside your booster where the service supports that mode.',
                'order' => 2,
            ],
            [
                'question' => 'What if I need support or refund?',
                'answer' => 'Open a support ticket from your order dashboard. Refund and dispute decisions for VALORANT boost orders are handled through the support workflow.',
                'order' => 3,
            ],
            [
                'question' => 'Can I request specific agents or roles?',
                'answer' => 'Yes. Use the Specific Agents addon if you want the booster to stay within your preferred agent pool whenever possible.',
                'order' => 4,
            ],
            [
                'question' => 'Will addons stay visible on my order details?',
                'answer' => 'Yes. Selected addons are saved with the order so customers, boosters, and admins can all see the same request scope.',
                'order' => 5,
            ],
            [
                'question' => 'Do you support rush delivery?',
                'answer' => 'Yes. Express Order prioritizes your order in the queue and is the fastest path when timing matters.',
                'order' => 6,
            ],
        ];

        Faq::query()->whereNotIn('order', array_column($entries, 'order'))->delete();

        foreach ($entries as $entry) {
            Faq::query()->updateOrCreate(
                ['order' => $entry['order']],
                $entry
            );
        }
    }
}
