<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationContract;
use App\Models\Sms;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * قرارداد مرحله «مذاکره و قرارداد»:
 * ۱) کاربران پیش‌نویس قرارداد را تکمیل/ویرایش می‌کنند.
 * ۲) ادمین تایید می‌کند؛ متن نهایی ثابت (snapshot) و لینک برای مشتری پیامک می‌شود.
 * ۳) مشتری از طریق لینک قرارداد را مطالعه و به‌صورت دیجیتال امضا می‌کند و به کاربران اطلاع داده می‌شود.
 */
class ContractController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;
    private SmsService $smsService;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper, SmsService $smsService)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
        $this->smsService = $smsService;
    }

    private function contractFor(Automation $automation): AutomationContract
    {
        return AutomationContract::firstOrCreate(['automation_id' => $automation->id], ['status' => AutomationContract::DRAFT]);
    }

    private function payload(AutomationContract $contract): array
    {
        $jalali = fn($date) => $date ? (new Verta($date))->format('Y/m/d ساعت H:i') : null;

        return [
            'status' => $contract->status,
            'status_label' => $contract->status_label,
            'labels' => AutomationContract::fieldLabels(),
            'fields' => $contract->resolvedFields(),
            // پس از تایید، متن ثابت شده نمایش داده می‌شود؛ در پیش‌نویس، داده زنده اتوماسیون
            'data' => $contract->snapshot ?? $contract->buildData(),
            'link' => $contract->status !== AutomationContract::DRAFT ? $contract->link() : null,
            'sms_sent' => $contract->sms_sent,
            'approved_by' => $contract->approver?->name,
            'approved_at' => $jalali($contract->approved_at),
            'sent_at' => $jalali($contract->sent_at),
            'viewed_at' => $jalali($contract->viewed_at),
            'signed_at' => $jalali($contract->signed_at),
            'signer_name' => $contract->signer_name,
            'signature' => $contract->signature,
            'can_approve' => auth()->user()->role === User::ADMIN,
        ];
    }

    // ---------- کارتابل ----------

    public function show(Automation $automation)
    {
        $contract = $this->contractFor($automation);

        if ($contract->status === AutomationContract::SIGNED && !$contract->signed_seen_at)
            $contract->update(['signed_seen_at' => Carbon::now()]);

        return $this->apiController->respondData($this->payload($contract->fresh()));
    }

    // ذخیره پیش‌نویس؛ اگر قبلاً تایید شده باشد، نیاز به تایید مجدد ادمین دارد
    public function save(Request $request, Automation $automation)
    {
        $this->validate($request, ['fields' => 'required|array']);

        $contract = $this->contractFor($automation);
        if ($contract->status === AutomationContract::SIGNED)
            return $this->apiController->respondError('این قرارداد توسط مشتری امضا شده و قابل ویرایش نیست');

        $fields = collect($request->input('fields'))
            ->only(array_keys(AutomationContract::fieldLabels()))
            ->map(fn($v) => is_string($v) ? trim($v) : $v)
            ->all();
        // اقلام ماده ۳ (محصولات پیش فاکتور با مشخصات فنی + آیتم‌های دستی)
        $fields['tech_items'] = AutomationContract::sanitizeTechItems($request->input('fields.tech_items'));

        $wasApproved = $contract->status === AutomationContract::APPROVED;
        $contract->update(['fields' => $fields]);
        $contract->invalidateApproval();

        return $this->apiController->respondUpdated(
            $wasApproved ? 'قرارداد ذخیره شد؛ برای ارسال مجدد به مشتری تایید ادمین لازم است' : 'پیش‌نویس قرارداد ذخیره شد',
            $this->payload($contract->fresh())
        );
    }

    // تایید ادمین: متن نهایی ثابت می‌شود و لینک قرارداد برای مشتری پیامک می‌شود
    public function approve(Automation $automation)
    {
        if (auth()->user()->role !== User::ADMIN)
            return $this->apiController->respondError('تایید قرارداد فقط توسط ادمین امکان‌پذیر است');

        $contract = $this->contractFor($automation);
        if ($contract->status === AutomationContract::SIGNED)
            return $this->apiController->respondError('این قرارداد قبلاً توسط مشتری امضا شده است');
        if (!(float)$automation->final_price)
            return $this->apiController->respondError('مبلغ کل قرارداد ثبت نشده است');

        DB::transaction(function () use ($contract) {
            $contract->update([
                'status' => AutomationContract::APPROVED,
                'snapshot' => $contract->buildData(),
                'token' => $contract->token ?: AutomationContract::generateToken(),
                'approved_by' => auth()->id(),
                'approved_at' => Carbon::now(),
            ]);
        });

        $sent = $this->sendLink($contract->fresh());

        return $this->apiController->respondUpdated(
            $sent ? 'قرارداد تایید و لینک آن برای مشتری پیامک شد' : 'قرارداد تایید شد اما ارسال پیامک با خطا مواجه شد؛ لینک را دستی ارسال کنید',
            $this->payload($contract->fresh())
        );
    }

    public function resend(Automation $automation)
    {
        $contract = $this->contractFor($automation);
        if ($contract->status !== AutomationContract::APPROVED)
            return $this->apiController->respondError('فقط قرارداد تایید شده و امضا نشده قابل ارسال است');

        $sent = $this->sendLink($contract);

        return $this->apiController->respondUpdated($sent ? 'لینک قرارداد دوباره پیامک شد' : 'ارسال پیامک با خطا مواجه شد', $this->payload($contract->fresh()));
    }

    private function sendLink(AutomationContract $contract): bool
    {
        $customer = $contract->automation->customer;
        $content = $customer->greeting_name . "\n\n"
            . "قرارداد نیروگاه خورشیدی شما با شرکت خورشید یار یزد آماده است.\n"
            . "لطفاً از طریق لینک زیر قرارداد را مطالعه و امضا نمایید:\n"
            . $contract->link();

        $sms = $this->smsService->sendAndLog($customer, $content);
        $contract->update(['sms_sent' => $sms->count > 0, 'sent_at' => Carbon::now()]);

        return $sms->count > 0;
    }

    // قراردادهای امضا شده‌ای که هنوز در کارتابل دیده نشده‌اند (برای اعلان)
    public static function unseenSignedCount(): int
    {
        return AutomationContract::where('status', AutomationContract::SIGNED)->whereNull('signed_seen_at')->count();
    }

    // ---------- مسیرهای عمومی (بدون ورود) ----------

    public function publicShow($token)
    {
        $contract = AutomationContract::where('token', $token)->first();
        if (!$contract)
            return $this->apiController->respondError('لینک قرارداد نامعتبر است');
        if ($contract->status === AutomationContract::DRAFT)
            return $this->apiController->respondError('این قرارداد در حال بازبینی است؛ پس از تایید، لینک جدید برای شما ارسال می‌شود');

        if (!$contract->viewed_at)
            $contract->update(['viewed_at' => Carbon::now()]);

        return $this->apiController->respondData([
            'data' => $contract->snapshot,
            'is_signed' => $contract->status === AutomationContract::SIGNED,
            'signer_name' => $contract->signer_name,
            'signature' => $contract->signature,
            'signed_at' => $contract->signed_at ? (new Verta($contract->signed_at))->format('Y/m/d ساعت H:i') : null,
        ]);
    }

    public function sign(Request $request, $token)
    {
        $contract = AutomationContract::with('automation.customer')->where('token', $token)->first();
        if (!$contract)
            return $this->apiController->respondError('لینک قرارداد نامعتبر است');
        if ($contract->status === AutomationContract::SIGNED)
            return $this->apiController->respondError('این قرارداد قبلاً امضا شده است');
        if ($contract->status !== AutomationContract::APPROVED)
            return $this->apiController->respondError('این قرارداد در حال بازبینی است و فعلاً قابل امضا نیست');

        $this->validate($request, [
            'signer_name' => 'required|string|max:100',
            'signature' => ['required', 'string', 'max:500000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
            'agree' => 'accepted',
        ], [
            'signer_name.required' => 'نام و نام خانوادگی امضاکننده را وارد نمایید',
            'signature.required' => 'لطفاً قرارداد را امضا نمایید',
            'signature.regex' => 'تصویر امضا نامعتبر است',
            'signature.max' => 'تصویر امضا بیش از حد بزرگ است',
            'agree.accepted' => 'لطفاً تایید کنید که قرارداد را مطالعه کرده‌اید',
        ]);

        $contract->update([
            'status' => AutomationContract::SIGNED,
            'signer_name' => $request->input('signer_name'),
            'signature' => $request->input('signature'),
            'signer_ip' => $request->ip(),
            'signed_at' => Carbon::now(),
        ]);

        $this->notifyStaff($contract);

        return $this->apiController->respondCreated('قرارداد با موفقیت امضا شد. از اعتماد شما سپاسگزاریم.');
    }

    private function notifyStaff(AutomationContract $contract): void
    {
        $mobiles = config('services.raygan_sms.staff_mobiles', []);
        if (!$mobiles)
            return;

        $customer = $contract->automation->customer;
        $content = 'قرارداد «' . ($customer?->name ?? '-') . '» به شماره ' . ($contract->snapshot['fields']['contract_number'] ?? '-')
            . ' توسط مشتری امضا شد.' . "\nامضاکننده: " . $contract->signer_name;

        $sent = $this->smsService->sendMany($mobiles, $content);
        Sms::create([
            'receivers' => $sent,
            'count' => count($sent),
            'body' => $content,
            'sent_at' => Carbon::now(),
            'customer_id' => $customer?->id,
            'user_id' => $contract->approved_by,
        ]);
    }
}
