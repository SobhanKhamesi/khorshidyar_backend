@php use App\Models\InstallationService; @endphp
    <!DOCTYPE html>
<html lang="fa-IR">
<head>
    <title>برگه نصب</title>
    <link rel="stylesheet" href="{{ asset("assets/css/bootstrap.css") }}">
    <style>
        @font-face {
            font-family: IRANSans;
            src: url("../assets/font/iransans/IRANSansWeb_Light.woff") format("woff");
            font-style: normal;
            font-weight: 300;
            font-display: swap;
        }

        @font-face {
            font-family: IRANSans;
            src: url("../assets/font/iransans/IRANSansWeb.woff") format("woff");
            font-style: normal;
            font-weight: normal;
            font-display: swap;
        }

        @font-face {
            font-family: IRANSans;
            src: url("../assets/font/iransans/IRANSansWeb_Medium.woff") format("woff");
            font-style: normal;
            font-weight: 500;
            font-display: swap;
        }

        body {
            font-weight: 500;
        }

        #title {
            font-weight: 800;
            font-size: 1.4rem;
            color: #0a53be;
            font-family: IRANSans, Tahoma, sans-serif !important;
        }

        .btn-remove-installer {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: transparent;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            line-height: 1;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .btn-remove-installer:hover {
            background: #dc3545;
            color: #fff;
        }

        /* هیچ‌کدام از دکمه‌های حذف در خروجی پرینت نمایش داده نمی‌شوند */
        @media print {
            .no-print {
                display: none !important;
            }
        }

    </style>


</head>
<body dir="rtl">
<div class="mx-auto mt-3 pb-4" style="max-width: 1000px">
    <div class="card-header" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row text-center" style="align-items: center;">
            <div class="col text-center" id="title">
                شرکت خورشیدیار
            </div>
            <div class="col">
{{--                <img src="{{ asset("assets/picture/logo-ivaz.png") }}" alt="logo-ivaz" style="max-width: 7rem">--}}
            </div>
        </div>
    </div>

    <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row" style="align-items: center;">
            <div class="col-auto">
                <div>
                    <span>نام مشتری : </span>
                    <span>{{ $installationService->customer?->name }}</span>
                </div>
                <div>
                    <span>تلفن تماس : </span>
                    <span>{{ $installationService->contact_number ?: $installationService->customer?->contact_number }}</span>
                </div>
            </div>
            <div class="col text-center">
            </div>
            <div class="col-auto">
                <div>
                    <span>آدرس نصب : </span>
                    <span>{{ $installationService->address ?: $installationService->customer?->address }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row" style="align-items: center;">
            <div class="col-auto">
                برگه نصب
                ({{ $installationService->status == InstallationService::FINAL ? 'نهایی' : 'موقت' }})
            </div>
            <div class="col text-center">
            </div>
            <div class="col-auto">
                <div>
                    <span>تاریخ ثبت : </span>
                    <span>{{ $installationService->jalali_date }}</span>
                </div>
                <div>
                    <span>تاریخ بازدید : </span>
                    <span>{{ $installationService->jalali_survey_date }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row" style="align-items: center;">
            <div class="col-auto">
                <span>منبع خرید : </span>
                <span>{{ $installationService->purchase_source == InstallationService::INTERNAL ? 'خرید شده از ما' : 'خریداری شده از جای دیگر' }}</span>
            </div>
            <div class="col text-center">
            </div>
            @if($installationService->technicalExpert)
                <div class="col-auto">
                    <span>کارشناس فنی : </span>
                    <span>{{ $installationService->technicalExpert->name }}</span>
                </div>
            @endif
        </div>
        @if($installationService->automation && $installationService->automation->invoice_number)
            <div class="row mt-1">
                <div class="col-auto">
                    <span>شماره فاکتور فروش مرتبط : </span>
                    <span>{{ $installationService->automation->invoice_number }}</span>
                </div>
            </div>
        @endif
        @if($installationService->description)
            <div class="row mt-1">
                <div class="col-auto">
                    <span>توضیحات محصول / سیستم نصب شده : </span>
                    <span>{{ $installationService->description }}</span>
                </div>
            </div>
        @endif
    </div>

    @php
        $roleLabels = InstallationService::ROLE_LABELS;
        $roleMap = InstallationService::ROLE_MAP;

        $installerRows = collect($roleMap)
            ->except('after_sale')
            ->map(function ($typeValue, $roleKey) use ($installationService, $roleLabels) {
                return [
                    'label' => $roleLabels[$roleKey] ?? $roleKey,
                ];
            });
    @endphp

    @if(count($installationService->installationServiceItems) > 0)
        <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
            <div class="mb-2" style="font-weight: bold">اقلام مصرفی</div>
            <table class="table table-bordered">
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">شرح</th>
                    <th class="text-center">مقدار</th>
                </tr>
                <tbody>
                @php $i = 1; @endphp
                @forelse($installationService->installationServiceItems as $item)
                    <tr>
                        <td>{{ $i }}</td>
                        <td style="font-weight: bold">{{ $item->product->name }}</td>
                        <td style="font-weight: bold">{{ $item->quantity }}</td>
                    </tr>
                    @php $i++; @endphp
                @empty
                    <tr>
                        <td colspan="3" class="text-center">قلم مصرفی ثبت نشده است</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
    @if($installationService->automation)
        <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
            <div class="mb-2" style="font-weight: bold">اطلاعات فاکتور فروش {{ $installationService->automation->invoice_number ?? " : " .$installationService->automation->invoice_number }}</div>
            <table class="table table-bordered">
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">شرح</th>
                    <th class="text-center">مقدار</th>
                    <th class="text-center">قیمت واحد (ریال)</th>
                    <th class="text-center">قیمت کل (ریال)</th>
                </tr>
                <tbody>
                @php
                    $i=1;
                    $totalCount = 0;
                    $totalPrice = 0;
                @endphp
                @foreach ($installationService->automation->orders as $item)
                    <tr>
                        <td>{{ $i }}</td>
                        <td style="font-weight: bold">{{ $item->product->name }}</td>
                        <td style="font-weight: bold">{{ $item->count }}</td>
                        <td style="font-weight: bold">{{ number_format($item->price * 10) }}</td>
                        <td style="font-weight: bold">{{ number_format($item->count * $item->price * 10) }}</td>
                    </tr>
                    @php
                        $totalCount += $item->count;
                        $totalPrice += $item->count * $item->price;
                        $i++;
                    @endphp
                @endforeach
                </tbody>
                <tr style="-webkit-print-color-adjust: exact; background: #c7c7c7">
                    <td style="font-weight: bold" colspan="2">جمع</td>
                    <td style="font-weight: bold" colspan="2">{{ $totalCount }}</td>
                    <td style="font-weight: bold">
                        <span>{{ number_format($totalPrice * 10) }}</span>
                        <span> ریال</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    @if($installerRows->contains(fn($row) => $row['appointment'] !== null))
        <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
            <div>
                تایم های نصب
            </div>
            <table class="table table-bordered mt-1" id="installer-times-table">
                <tr>
                    <th class="text-center">نقش</th>
                    <th class="text-center">نصاب</th>
                    <th class="text-center">تاریخ</th>
                    <th class="text-center">ساعت شروع</th>
                    <th class="text-center">ساعت پایان</th>
                    <th class="text-center no-print">حذف</th>
                </tr>
                <tbody>
                @foreach($installerRows as $row)
                    @if($row['appointment'])
                        <tr>
                            <td style="font-weight: bold">{{ $row['label'] }}</td>
                            <td style="font-weight: bold">{{ $row['appointment']->installer->name ?? '-' }}</td>
                            <td style="font-weight: bold">{{ (new \Hekmatinasser\Verta\Verta($row['appointment']->date))->format('Y/m/d') }}</td>
                            <td style="font-weight: bold">{{ $row['appointment']->start_time }}</td>
                            <td style="font-weight: bold">{{ $row['appointment']->end_time }}</td>
                            <td class="text-center no-print">
                                <button type="button" class="btn-remove-installer" onclick="removeInstallerRow(this)" title="حذف این نصاب از پرینت">✕</button>
                            </td>
                        </tr>
                        @php $i++; @endphp
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

<script>
    function removeInstallerRow(button) {
        var row = button.closest('tr');
        var table = document.getElementById('installer-times-table');
        row.parentNode.removeChild(row);

        // شماره‌گذاری ستون # را بعد از حذف، دوباره مرتب می‌کنیم
        var remainingRows = table.querySelectorAll('tbody tr');
        remainingRows.forEach(function (r, index) {
            var indexCell = r.querySelector('.row-index');
            if (indexCell) {
                indexCell.textContent = index + 1;
            }
        });

        // اگر همه‌ی نصاب‌ها حذف شدند، کل باکس «تایم های نصب» را هم مخفی کن
        if (remainingRows.length === 0) {
            table.closest('.card-header').style.display = 'none';
        }
    }
</script>

</body>
</html>
