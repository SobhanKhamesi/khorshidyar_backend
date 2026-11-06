<?php

namespace App\Services;

use App\Models\AfterSalesService;
use App\Models\Sms;
use Throwable;

/**
 * پیامک‌های ثبت خدمات پس از فروش:
 *  - به مشتری: نام مسئول پیگیری + شماره تماس او + تاریخ مراجعه
 *  - به مسئول پیگیری: «کاری در کارتابل خدمات پس از فروش برای شما ثبت شده است»
 */
class AfterSalesSmsNotifier
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * @return array هشدارهای قابل نمایش به کاربر
     */
    public function notifyRegistered(AfterSalesService $service): array
    {
        // خدمت ثبت شده؛ خطای پیامک نباید پاسخ موفق را خراب کند
        try {
            return $this->send($service->loadMissing(['customer', 'responsibleUser']));
        } catch (Throwable $e) {
            report($e);
            return ['ارسال پیامک‌های خدمات پس از فروش با خطا مواجه شد'];
        }
    }

    private function send(AfterSalesService $service): array
    {
        $warnings = [];
        $installer = $service->responsibleUser;
        $customer = $service->customer;

        if (!$installer)
            return ['مسئول خدمت مشخص نیست و پیامکی ارسال نشد'];

        if ($installer->mobile) {
            $body = "{$installer->name} عزیز\n"
                . "یک کار برای مشتری «{$customer?->name}» در کارتابل خدمات پس از فروش برای شما ثبت شده است.";

            $sent = $this->smsService->send($installer->mobile, $body);
            $this->log($body, $installer->mobile, $sent);

            if (!$sent)
                $warnings[] = "ارسال پیامک به «{$installer->name}» ناموفق بود";
        } else {
            $warnings[] = "شماره همراه «{$installer->name}» ثبت نشده و پیامک برای او ارسال نشد";
        }

        if ($customer) {
            // در پیامک خدمات پس از فروش از عنوان «مسئول پیگیری» استفاده می‌شود (نه نصاب)
            $lines = [
                'درخواست خدمات پس از فروش شما ثبت شد.',
                "مسئول پیگیری: {$installer->name}",
            ];
            if ($installer->mobile)
                $lines[] = "شماره تماس مسئول پیگیری: {$installer->mobile}";
            if ($service->jalali_follow_up_date)
                $lines[] = "تاریخ مراجعه: {$service->jalali_follow_up_date}";

            $content = $customer->greeting_name . "\n\n" . implode("\n", $lines);

            $sms = $this->smsService->sendAndLog($customer, $content);
            if (!$sms->count)
                $warnings[] = 'ارسال پیامک اطلاعات مسئول پیگیری به مشتری ناموفق بود';
        }

        return $warnings;
    }

    private function log(string $body, string $mobile, bool $sent): void
    {
        Sms::create([
            'send_to_all' => false,
            'receiver_type' => Sms::TYPE_SPECIFIC,
            'receivers' => $sent ? [$mobile] : [],
            'count' => $sent ? 1 : 0,
            'body' => $body,
            'user_id' => auth()->id(),
        ]);
    }
}
