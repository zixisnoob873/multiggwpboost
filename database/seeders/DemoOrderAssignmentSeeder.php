<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoOrderAssignmentSeeder extends Seeder
{
    /**
     * Seed 3 customers, 3 boosters, and 3 assigned demo orders.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoOrderAssignmentSeeder must not run in production because it creates demo users and orders.');
        }

        $customers = collect([
            [
                'email' => 'customer1@ggwp.dev',
                'first_name' => 'Ava',
                'last_name' => 'Carter',
            ],
            [
                'email' => 'customer2@ggwp.dev',
                'first_name' => 'Noah',
                'last_name' => 'Turner',
            ],
            [
                'email' => 'customer3@ggwp.dev',
                'first_name' => 'Mia',
                'last_name' => 'Brooks',
            ],
        ])->map(function (array $customer): User {
            return User::updateOrCreate(
                ['email' => $customer['email']],
                [
                    'name' => "{$customer['first_name']} {$customer['last_name']}",
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name'],
                    'role' => 'customer',
                    'password' => 'Customer123!Secure',
                    'account_status' => 'active',
                ]
            );
        })->values();

        $boosters = collect([
            [
                'email' => 'booster1@ggwp.dev',
                'first_name' => 'Liam',
                'last_name' => 'Hayes',
            ],
            [
                'email' => 'booster2@ggwp.dev',
                'first_name' => 'Emma',
                'last_name' => 'Reed',
            ],
            [
                'email' => 'booster3@ggwp.dev',
                'first_name' => 'Ethan',
                'last_name' => 'Parker',
            ],
        ])->map(function (array $booster): User {
            return User::updateOrCreate(
                ['email' => $booster['email']],
                [
                    'name' => "{$booster['first_name']} {$booster['last_name']}",
                    'first_name' => $booster['first_name'],
                    'last_name' => $booster['last_name'],
                    'role' => 'booster',
                    'password' => 'Booster123!Secure',
                    'account_status' => 'active',
                ]
            );
        })->values();

        $orders = [
            [
                'order_number' => 'DEMO-ORDER-1001',
                'product' => 'Rank Boosting',
                'status' => OrderStatus::IN_PROGRESS,
                'payment_status' => 'paid',
                'price_cents' => 12500,
                'details' => [
                    'game' => 'Valorant',
                    'service' => 'Rank Boosting',
                    'from' => 'Gold 2',
                    'to' => 'Platinum 1',
                    'region' => 'NA',
                    'platform' => 'PC',
                    'notes' => 'Priority evening sessions preferred.',
                ],
                'contact_method' => 'discord',
                'discord' => 'ava#1001',
            ],
            [
                'order_number' => 'DEMO-ORDER-1002',
                'product' => 'Rank Boosting',
                'status' => OrderStatus::PENDING,
                'payment_status' => 'paid',
                'price_cents' => 8900,
                'details' => [
                    'game' => 'League of Legends',
                    'service' => 'Rank Boosting',
                    'from' => 'Silver 1',
                    'to' => 'Gold 4',
                    'region' => 'EUW',
                    'platform' => 'PC',
                    'notes' => 'Solo queue only.',
                ],
                'contact_method' => 'whatsapp',
                'whatsapp' => '+15550001002',
            ],
            [
                'order_number' => 'DEMO-ORDER-1003',
                'product' => 'Rank Boosting',
                'status' => OrderStatus::PAUSED,
                'payment_status' => 'paid',
                'price_cents' => 15900,
                'details' => [
                    'game' => 'Rocket League',
                    'service' => 'Rank Boosting',
                    'from' => 'Diamond 1',
                    'to' => 'Champion 1',
                    'region' => 'NA',
                    'platform' => 'PlayStation',
                    'notes' => 'Paused while customer travels.',
                ],
                'contact_method' => 'email',
            ],
        ];

        foreach ($orders as $index => $orderData) {
            $customer = $customers[$index];
            $booster = $boosters[$index];
            $priceCents = (int) $orderData['price_cents'];

            Order::updateOrCreate(
                ['order_number' => $orderData['order_number']],
                [
                    'user_id' => $customer->id,
                    'booster_id' => $booster->id,
                    'product' => $orderData['product'],
                    'status' => $orderData['status'],
                    'payment_status' => $orderData['payment_status'],
                    'price_cents' => $priceCents,
                    'discount_amount' => 0,
                    'booster_payout_rate' => Order::configuredBoosterPayoutPercentage(),
                    'booster_payout_cents' => (int) round($priceCents * Order::configuredBoosterPayoutRate()),
                    'currency' => 'USD',
                    'details' => $orderData['details'],
                    'metadata' => [
                        'source' => 'demo-order-assignment-seeder',
                        'customer_email' => $customer->email,
                        'booster_email' => $booster->email,
                    ],
                    'contact_method' => $orderData['contact_method'],
                    'whatsapp' => $orderData['whatsapp'] ?? null,
                    'discord' => $orderData['discord'] ?? null,
                    'is_custom' => true,
                    'paid_at' => now(),
                    'assigned_at' => now(),
                ]
            );
        }
    }
}
