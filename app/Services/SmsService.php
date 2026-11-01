<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sms;
use App\Models\SourceSms;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsService
{
    // تعداد درخواست‌های هم‌زمان در ارسال گروهی
    private const POOL_SIZE = 20;

    /**
     * ارسال یک پیامک؛ در صورت موفقیت true برمی‌گرداند.
     */
    public function send(string $mobile, string $body): bool
    {
        try {
            $response = Http::timeout($this->timeout())->get($this->url(), $this->params($mobile, $body));
            return $response->successful() && (bool)$response->body();
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * ارسال یک متن به چند شماره به‌صورت هم‌زمان؛ شماره‌هایی که با موفقیت ارسال شدند برگردانده می‌شوند.
     */
    public function sendMany(array $mobiles, string $body): array
    {
        $sent = [];

        foreach (array_chunk(array_values($mobiles), self::POOL_SIZE) as $chunk) {
            $responses = Http::pool(fn(Pool $pool) => array_map(
                fn($mobile) => $pool->timeout($this->timeout())->get($this->url(), $this->params($mobile, $body)),
                $chunk
            ));

            foreach ($chunk as $i => $mobile) {
                $response = $responses[$i] ?? null;
                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful() && (bool)$response->body())
                    $sent[] = $mobile;
            }
        }

        return $sent;
    }

    /**
     * ارسال پیامک بر اساس قالب ثبت شده به یک مشتری و ثبت آن در تاریخچه پیامک‌ها.
     * اگر قالب وجود نداشته باشد null برمی‌گرداند.
     */
    public function sendTemplateToCustomer(string $templateTitle, Customer $customer, string $suffix = ''): ?Sms
    {
        $template = SourceSms::where('title', $templateTitle)->first();
        if (!$template)
            return null;

        $content = $customer->greeting_name . "\n\n" . $template->description . $suffix;

        return $this->sendAndLog($customer, $content);
    }

    /**
     * ارسال متن به مشتری و ثبت در جدول پیامک‌ها
     */
    public function sendAndLog(Customer $customer, string $content, array $extra = []): Sms
    {
        $mobile = $customer->contact_number;
        $sent = $mobile && $this->send($mobile, $content);

        return Sms::create(array_merge([
            'receivers' => $sent ? [$mobile] : [],
            'count' => $sent ? 1 : 0,
            'body' => $content,
            'sent_at' => Carbon::now(),
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
        ], $extra));
    }

    private function url(): string
    {
        return config('services.raygan_sms.url');
    }

    private function timeout(): int
    {
        return (int)config('services.raygan_sms.timeout', 10);
    }

    private function params(string $mobile, string $body): array
    {
        return [
            'Username' => config('services.raygan_sms.username'),
            'Password' => config('services.raygan_sms.password'),
            'PhoneNumber' => config('services.raygan_sms.number'),
            'MessageBody' => $body,
            'RecNumber' => $mobile,
            'Smsclass' => 1,
        ];
    }
}
