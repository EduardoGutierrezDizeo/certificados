<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;

class NotifyExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expiring';

    protected $description = 'Notifica a los abogados cuya suscripción vence en los próximos 3 días';

    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->whereNull('expiry_notified_at')
            ->whereBetween('ends_at', [now(), now()->addDays(3)])
            ->with('user')
            ->get();

        $this->info("Encontradas {$subscriptions->count()} suscripciones por vencer.");

        foreach ($subscriptions as $subscription) {
            $subscription->user->notify(new SubscriptionExpiringSoon($subscription));
            $subscription->update(['expiry_notified_at' => now()]);

            $this->line("Notificado: {$subscription->user->email} (vence {$subscription->ends_at->format('d/m/Y')})");
        }

        return self::SUCCESS;
    }
}
