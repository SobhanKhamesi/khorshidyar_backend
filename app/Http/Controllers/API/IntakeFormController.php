<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationCustomerSurvey;
use App\Models\Customer;
use App\Models\CustomerIntakeForm;
use App\Models\Sms;
use App\Models\SourceSms;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * فرم اولیه‌ای که مشتری/سرنخ خودش تکمیل می‌کند:
 * ۱) کاربر از صفحه ثبت درخواست، لینک فرم را برای مشتری پیامک می‌کند و درخواست در کارتابل ساخته می‌شود.
 * ۲) مشتری از طریق لینک عمومی فرم را تکمیل می‌کند.
 * ۳) پس از تکمیل، به کاربران پیامک ارسال و در کارتابل اعلان نمایش داده می‌شود.
 */
class IntakeFormController extends Controller
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

    /**
     * ارسال لینک فرم اولیه برای مشتری.
     * با customer یک درخواست جدید در کارتابل ساخته می‌شود؛ با automation لینک برای همان درخواست (دوباره) ارسال می‌شود.
     */
    public function send(Request $request)
    {
        $this->validate($request, [
            'customer' => 'required_without:automation',
            'automation' => 'nullable',
        ], [
            'customer.required_without' => 'مشتری را انتخاب نمایید',
        ]);

        $automation = $request->input('automation') ? Automation::findOrFail($request->input('automation')) : null;
        $customer = $automation ? $automation->customer : Customer::findOrFail($request->input('customer'));

        if (!$customer->contact_number)
            return $this->apiController->respondValidationError('برای این مشتری شماره تماس ثبت نشده است');

        $referrerPercent = $this->apiHelper->referrerPercent($request);

        $form = DB::transaction(function () use ($automation, $customer, $referrerPercent, $request) {
            if (!$automation) {
                $automation = Automation::create([
                    'status' => Automation::REQUEST,
                    'user_id' => auth()->id(),
                    'customer_id' => $customer->id,
                    'referrer_percent' => $customer->referrer_id ? ($referrerPercent ?? $customer->referrer_percent) : null,
                    'description' => $request->input('description'),
                ]);
            }

            // اگر فرم تکمیل نشده‌ای برای این درخواست وجود دارد، همان لینک دوباره ارسال می‌شود
            return CustomerIntakeForm::where('automation_id', $automation->id)->whereNull('submitted_at')->latest()->first()
                ?? CustomerIntakeForm::create([
                    'token' => CustomerIntakeForm::generateToken(),
                    'customer_id' => $customer->id,
                    'automation_id' => $automation->id,
                    'sent_by' => auth()->id(),
                ]);
        });

        $template = SourceSms::where('title', CustomerIntakeForm::SMS_TEMPLATE_TITLE)->value('description')
            ?: CustomerIntakeForm::DEFAULT_SMS_TEXT;
        $content = str_contains($template, '{link}')
            ? str_replace('{link}', $form->link(), $template)
            : $template . "\n\n" . $form->link();

        $sms = $this->smsService->sendAndLog($customer, $content);
        $form->update(['sms_sent' => $sms->count > 0]);

        $message = $sms->count > 0
            ? 'لینک فرم اولیه برای مشتری پیامک شد و درخواست در کارتابل ثبت شد'
            : 'درخواست در کارتابل ثبت شد اما ارسال پیامک با خطا مواجه شد؛ لینک فرم را می‌توانید دستی ارسال کنید';

        return $this->apiController->respondCreated($message, [
            'automation_id' => $form->automation_id,
            'link' => $form->link(),
            'sms_sent' => $sms->count > 0,
        ]);
    }

    // ---------- مسیرهای عمومی (بدون ورود) ----------

    public function show($token)
    {
        $form = CustomerIntakeForm::with('customer')->where('token', $token)->first();
        if (!$form)
            return $this->apiController->respondError('لینک فرم نامعتبر است');

        $customer = $form->customer;

        return $this->apiController->respondData([
            'is_submitted' => $form->is_submitted,
            'intro' => CustomerIntakeForm::intro(),
            'questions' => AutomationCustomerSurvey::questions(),
            // مقادیر اولیه از اطلاعات ثبت شده مشتری
            'defaults' => $form->is_submitted ? (object)[] : [
                'full_name' => $customer?->name,
                'national_code' => $customer?->national_code,
                'address' => $customer?->address,
                'mobile' => $customer?->contact_number,
            ],
        ]);
    }

    public function submit(Request $request, $token)
    {
        $form = CustomerIntakeForm::with('customer')->where('token', $token)->first();
        if (!$form)
            return $this->apiController->respondError('لینک فرم نامعتبر است');
        if ($form->is_submitted)
            return $this->apiController->respondError('این فرم قبلاً تکمیل و ارسال شده است');

        // ارقام فارسی فقط در پاسخ‌های عددی تبدیل می‌شوند (گزینه‌هایی مثل «از ۶ تا ۱۰ سال» باید عیناً بمانند)
        $numeric = collect(AutomationCustomerSurvey::questions())
            ->whereIn('type', ['national_code', 'digits', 'number', 'mobile'])->pluck('key')->all();
        $request->merge(collect($request->only($numeric))
            ->map(fn($v) => is_string($v) ? self::latinDigits(trim($v)) : $v)->all());
        $answers = $this->validate($request, $this->rules(), $this->messages());

        // پاسخ‌ها به‌عنوان فرم بازدید «ثبت شده از مشتری» روی همین درخواست ذخیره می‌شوند و بعداً در سامانه قابل ویرایش‌اند
        DB::transaction(function () use ($form, $answers) {
            if ($form->automation_id) {
                AutomationCustomerSurvey::updateOrCreate(
                    ['automation_id' => $form->automation_id],
                    collect($answers)->only(AutomationCustomerSurvey::keys())->all()
                );
                // اگر هنوز فرم بازدیدی (خانگی/صنعتی) برای درخواست ثبت نشده، نوع فرم بازدید «ثبت شده از مشتری» می‌شود
                // ستون survey_type مقدار null نمی‌پذیرد و «بدون فرم» به‌صورت ۰ ذخیره می‌شود
                Automation::whereKey($form->automation_id)
                    ->where(fn($q) => $q->whereNull('survey_type')->orWhere('survey_type', 0))
                    ->update(['survey_type' => Automation::CUSTOMER]);
            }
            $form->update(['submitted_at' => Carbon::now()]);
        });

        // اطلاعات خالی پرونده مشتری از روی فرم تکمیل می‌شود (اطلاعات موجود بازنویسی نمی‌شود)
        $customer = $form->customer;
        if ($customer) {
            $fill = array_filter([
                'national_code' => $customer->national_code ? null : $answers['national_code'],
                'address' => $customer->address ? null : $answers['address'],
                'contact_number1' => ($customer->contact_number1 || $answers['mobile'] === $customer->contact_number) ? null : $answers['mobile'],
            ]);
            if ($fill)
                $customer->update($fill);
        }

        $this->notifyStaff($form, $answers);

        return $this->apiController->respondCreated('فرم شما با موفقیت ثبت شد. کارشناسان ما به‌زودی با شما تماس می‌گیرند.');
    }

    // ---------- مسیرهای کارتابل ----------

    // وضعیت لینک فرم اولیه یک درخواست؛ با مشاهده فرم تکمیل شده، اعلان آن خوانده شده در نظر گرفته می‌شود
    // (پاسخ‌ها به‌عنوان فرم بازدید درخواست در automation/{id} برگردانده می‌شوند)
    public function answers(Automation $automation)
    {
        $form = CustomerIntakeForm::where('automation_id', $automation->id)->latest()->first();
        if (!$form)
            return $this->apiController->respondError('فرم اولیه‌ای برای این درخواست ارسال نشده است');

        if ($form->is_submitted && !$form->seen_at)
            $form->update(['seen_at' => Carbon::now()]);

        return $this->apiController->respondData([
            'is_submitted' => $form->is_submitted,
            'sms_sent' => $form->sms_sent,
            'link' => $form->link(),
            'jalali_created_at' => $form->jalali_created_at,
            'jalali_submitted_at' => $form->jalali_submitted_at,
        ]);
    }

    // فرم‌های تکمیل شده‌ای که هنوز در کارتابل دیده نشده‌اند (برای اعلان)
    public static function unseenSubmittedCount(): int
    {
        return CustomerIntakeForm::whereNotNull('submitted_at')->whereNull('seen_at')->count();
    }

    // سوالات فرم «ثبت شده از مشتری» برای فرم ثبت/ویرایش درخواست در سامانه
    public function questions()
    {
        return $this->apiController->respondData(AutomationCustomerSurvey::questions());
    }

    private function notifyStaff(CustomerIntakeForm $form, array $answers): void
    {
        $mobiles = config('services.raygan_sms.staff_mobiles', []);
        if (!$mobiles)
            return;

        $customer = $form->customer;
        $kind = match ($customer?->status) {
            Customer::LEAD => 'سرنخ',
            Customer::OPPORTUNITY => 'فرصت',
            default => 'مشتری',
        };
        $content = "فرم اولیه توسط {$kind} «" . ($customer?->name ?? $answers['full_name'] ?? '-') . "» تکمیل شد.\n"
            . 'شماره تماس: ' . ($answers['mobile'] ?? '-') . "\n"
            . 'جهت بررسی به کارتابل «درخواست ها» مراجعه نمایید.';

        $sent = $this->smsService->sendMany($mobiles, $content);

        Sms::create([
            'receivers' => $sent,
            'count' => count($sent),
            'body' => $content,
            'sent_at' => Carbon::now(),
            'customer_id' => $customer?->id,
            'user_id' => $form->sent_by,
        ]);
    }

    private function rules(): array
    {
        $rules = [];
        foreach (AutomationCustomerSurvey::questions() as $q) {
            $rules[$q['key']] = match ($q['type']) {
                'text' => ['required', 'string', 'max:100'],
                'national_code' => ['required', 'digits:10'],
                'digits' => ['required', 'digits_between:5,20'],
                'textarea' => ['required', 'string', 'max:1000'],
                'number' => ['required', 'numeric', 'min:0', 'max:2000000000'],
                'single' => ['required', Rule::in($q['options'])],
                'multiple' => ['required', 'array', 'min:1', 'max:' . ($q['max'] ?? count($q['options']))],
                'mobile' => ['required', 'regex:/^09\d{9}$/'],
            };
            if ($q['type'] === 'multiple')
                $rules[$q['key'] . '.*'] = [Rule::in($q['options'])];
        }

        return $rules;
    }

    private function messages(): array
    {
        $messages = [
            'national_code.digits' => 'کد ملی باید ۱۰ رقم باشد',
            'bill_id.digits_between' => 'شناسه قبض برق معتبر نیست',
            'mobile.regex' => 'شماره موبایل معتبر نیست (مانند ۰۹۱۲۳۴۵۶۷۸۹)',
            'available_area.numeric' => 'فضای در دسترس را به عدد وارد کنید',
            'surface_material.max' => 'حداکثر ۲ گزینه برای جنس سطح انتخاب کنید',
        ];
        foreach (AutomationCustomerSurvey::questions() as $q)
            $messages[$q['key'] . '.required'] = 'لطفاً «' . $q['title'] . '» را تکمیل کنید';

        return $messages;
    }

    private static function latinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
