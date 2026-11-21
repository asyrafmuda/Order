<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * The page that sells this system (/web), shown on the seller's demo site
 * only (LANDING_PAGE=true).
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(config('landing.enabled'), 404);

        $whatsapp = preg_replace('/\D/', '', (string) config('landing.whatsapp'));
        $product = (string) config('landing.product');

        return view('landing', [
            'product' => $product,
            'price' => (string) config('landing.price'),
            'priceNote' => (string) config('landing.price_note'),
            'whatsapp' => $whatsapp ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode("Hai, saya berminat dengan sistem {$product}.") : null,
            'email' => config('landing.email') ?: null,
            'demoUrl' => config('landing.demo_url') ?: url('/'),
            'demoAccounts' => $this->demoAccounts(),
        ]);
    }

    /**
     * Only accounts that really exist here and still sign in with the demo
     * password, so the page never shows a login that does not work.
     *
     * @return list<array{label: string, email: string, password: string}>
     */
    private function demoAccounts(): array
    {
        $password = (string) config('landing.demo_password');
        $accounts = (array) config('landing.demo_accounts');

        if ($password === '' || $accounts === []) {
            return [];
        }

        return User::query()->whereIn('email', array_keys($accounts))->get(['email', 'password'])
            ->filter(fn (User $user) => Hash::check($password, (string) $user->password))
            ->sortBy(fn (User $user) => array_search($user->email, array_keys($accounts), true))
            ->map(fn (User $user) => ['label' => $accounts[$user->email], 'email' => $user->email, 'password' => $password])
            ->values()
            ->all();
    }
}
