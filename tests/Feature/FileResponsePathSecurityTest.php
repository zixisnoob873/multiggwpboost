<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FileResponsePathSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_route_rejects_unsafe_stored_paths(): void
    {
        foreach (['../.env', '/etc/passwd', 'https://example.com/file.jpg', 'uploads/profile-photos/../../.env', 'unexpected/avatar.jpg'] as $path) {
            $user = User::factory()->create([
                'role' => 'customer',
                'account_status' => 'active',
                'profile_photo_path' => $path,
            ]);

            $url = URL::temporarySignedRoute('profile-photos.show', now()->addMinutes(30), [
                'user' => $user,
                'v' => sha1($path),
            ]);

            $this->get($url)->assertNotFound();
        }
    }

    public function test_admin_completion_proof_rejects_unsafe_stored_paths(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);

        foreach (['../.env', '/etc/passwd', 'https://example.com/file.jpg', 'order-completion-proofs/../../.env', 'unexpected/proof.jpg'] as $path) {
            $order = $this->order($customer, $path);

            $this->actingAs($admin)
                ->get(route('admin-orders.completion-proof', ['order' => $order]))
                ->assertNotFound();
        }
    }

    public function test_admin_completion_proof_serves_valid_managed_path(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $customer = User::factory()->create([
            'role' => 'customer',
            'account_status' => 'active',
        ]);
        $path = 'order-completion-proofs/1/proof.jpg';
        $order = $this->order($customer, $path);

        Storage::disk('local')->put($path, 'proof');

        $this->actingAs($admin)
            ->get(route('admin-orders.completion-proof', ['order' => $order]))
            ->assertOk();
    }

    protected function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'account_status' => 'active',
        ]);
    }

    protected function order(User $customer, string $completionProofPath): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-PROOF-'.strtoupper(substr(hash('sha1', $completionProofPath.microtime()), 0, 8)),
            'product' => 'Rank Boosting',
            'status' => 'Completed',
            'payment_status' => 'paid',
            'price_cents' => 1999,
            'currency' => 'USD',
            'details' => ['order' => ['orderType' => 'Rank Boosting']],
            'metadata' => [],
            'contact_method' => 'email',
            'is_custom' => false,
            'completion_proof_path' => $completionProofPath,
        ]);
    }
}
