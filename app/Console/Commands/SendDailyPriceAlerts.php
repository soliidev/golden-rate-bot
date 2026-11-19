<?php

namespace App\Console\Commands;

use App\Models\PriceAlert;
use App\Services\CurrentItemPrice\CurrentItemPriceService;
use App\Support\PriceItems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

class SendDailyPriceAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:send-daily {--dry-run : Show the alerts without sending them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the daily instant prices to every user who subscribed at the current time';

    /**
     * Execute the console command.
     */
    public function handle(CurrentItemPriceService $prices): int
    {
        $timezone = (string) config('price-alerts.timezone', 'Asia/Tehran');
        $now = now($timezone);

        $alerts = PriceAlert::query()
            ->where('is_active', true)
            ->where('notify_time', $now->format('H:i'))
            ->where(static function ($query) use ($now): void {
                $query->whereNull('last_sent_on')
                    ->orWhere('last_sent_on', '<', $now->toDateString());
            })
            ->get();

        if ($alerts->isEmpty()) {
            $this->info("No alerts scheduled at {$now->format('H:i')} ({$timezone}).");

            return self::SUCCESS;
        }

        try {
            $currentPrices = $prices->getCurrentItemPrice();
        } catch (Throwable $exception) {
            Log::error('Could not fetch prices for daily alerts.', [
                'exception' => $exception->getMessage(),
            ]);
            $this->error('Could not fetch the current prices: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            foreach ($alerts as $alert) {
                $this->line("Would notify chat {$alert->chat_id} at {$alert->notify_time}.");

                foreach ($this->linesFor($alert, $currentPrices) as $line) {
                    $this->line('  '.$line);
                }
            }

            return self::SUCCESS;
        }

        $bot = app(Nutgram::class);
        $sent = 0;

        foreach ($alerts as $alert) {
            $lines = $this->linesFor($alert, $currentPrices);

            if ($lines === []) {
                $this->warn("Skipped chat {$alert->chat_id}: none of its items are available.");

                continue;
            }

            $message = "🔔 قیمت‌های لحظه‌ای — {$now->format('H:i')}\n\n"
                .implode("\n", $lines)
                ."\n\n⏰ این پیام به درخواست شما هر روز در این ساعت ارسال می‌شود.";

            try {
                $bot->sendMessage($message, chat_id: $alert->chat_id);

                $alert->forceFill(['last_sent_on' => $now->toDateString()])->save();

                $sent++;
            } catch (Throwable $exception) {
                Log::error('Could not send the daily price alert.', [
                    'chat_id' => $alert->chat_id,
                    'exception' => $exception->getMessage(),
                ]);
                $this->warn("Failed to notify chat {$alert->chat_id}: {$exception->getMessage()}");
            }
        }

        $this->info("Sent {$sent} of {$alerts->count()} daily alerts.");

        return self::SUCCESS;
    }

    /**
     * Build the price lines of one alert.
     *
     * @param  array<string, mixed>  $currentPrices
     * @return array<int, string>
     */
    protected function linesFor(PriceAlert $alert, array $currentPrices): array
    {
        $lines = [];

        foreach ((array) $alert->items as $key) {
            if (! is_string($key) || ! PriceItems::exists($key) || ! isset($currentPrices[$key])) {
                continue;
            }

            $lines[] = PriceItems::formatAlertLine($key, (array) $currentPrices[$key]);
        }

        return $lines;
    }
}
