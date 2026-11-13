<!DOCTYPE html>
<html lang="fa-IR">
<head>
    <title>پرینت فاکتور</title>
    <link rel="stylesheet" href="{{ config('webroot.assets') }}/css/bootstrap.css">
    <style>
        @font-face {
            font-family: IRANSans;
            src: url("{{ config('webroot.assets') }}/font/iransans/IRANSansWeb_Light.woff") format("woff");
            font-style: normal;
            font-weight: 300;
            font-display: swap;
        }

        @font-face {
            font-family: IRANSans;
            src: url("{{ config('webroot.assets') }}/font/iransans/IRANSansWeb.woff") format("woff");
            font-style: normal;
            font-weight: normal;
            font-display: swap;
        }

        @font-face {
            font-family: IRANSans;
            src: url("{{ config('webroot.assets') }}/font/iransans/IRANSansWeb_Medium.woff") format("woff");
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

        /* توضیحات قابل ویرایش؛ ارتفاع با اسکریپت پایین صفحه به اندازه متن تنظیم می‌شود */
        textarea.auto-height {
            display: block;
            box-sizing: border-box;
            width: 100%;
            border: 1px dashed transparent;
            border-radius: 0.4rem;
            background: none;
            color: #4d43ca;
            font: inherit;
            line-height: 1.7;
            padding: 0.25rem;
            resize: none;
            overflow: hidden;
            white-space: pre-wrap;
        }

        textarea.auto-height:hover, textarea.auto-height:focus {
            border-color: #4d43ca;
            outline: none;
        }

        .auto-height-print {
            display: none;
            color: #4d43ca;
            line-height: 1.7;
            padding: 0.25rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        @media print {
            textarea.auto-height {
                display: none;
            }

            .auto-height-print {
                display: block;
            }
        }

    </style>


</head>
<body dir="rtl">
<div class="mx-auto mt-3 pb-4" style="max-width: 1000px">
    @include('partials.print_tools')

    <div class="card-header print-tile" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row text-center" style="align-items: center;">
            <div class="col text-center" id="title">
                شرکت خورشیدیار
            </div>
            <div class="col">
{{--                <img src="{{ config('webroot.assets') }}/picture/logo-ivaz.png" alt="logo-ivaz" style="max-width: 7rem">--}}
            </div>
        </div>
    </div>
    <div class="card-header mt-2 print-tile" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row" style="align-items: center;">
            <div class="col-auto">
                <div>
                    <span>نام مشتری : </span>
                    <span>{{ $accounting->customer->name }}</span>
                </div>
                <div>
                    <span>تلفن تماس : </span>
                    <span>{{ $accounting->customer->contact_number }}</span>
                </div>
            </div>
            <div class="col text-center">
            </div>
            <div class="col-auto">
                <div>
                    <span>آدرس مشتری : </span>
                    <span>{{ $accounting->customer->address }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="card-header mt-2 print-tile" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row" style="align-items: center;">
            <div class="col-auto">
                {{ $accounting->status === \App\Models\Automation::SUCCESS ? "فاکتور فروش" : "پیش فاکتور" }}
            </div>
            <div class="col text-center">
            </div>
            <div class="col-auto">
                <div>
                    <span>تاریخ : </span>
                    <span>{{ $accounting->jalali_date }}</span>
                </div>
                <div>
                    <span>شماره فاکتور : </span>
                    <span>{{ $accounting->invoice_number }}</span>
                </div>
            </div>
        </div>
    </div>


    <div class="card-header mt-2 print-tile" style="border: 1px solid blue;border-radius: 0.7rem">
        <table class="table table-bordered">
            <tr>
                <th class="text-center">#</th>
                <th class="text-center">شرح</th>
                <th class="text-center">مقدار</th>
                <th class="text-center">واحد</th>
                <th class="text-center">قیمت واحد (ریال)</th>
                <th class="text-center">قیمت کل (ریال)</th>
            </tr>
            <tbody>
            @php
                $i=1;
                $totalCount = 0;
                $totalPrice = 0;
            @endphp
            @foreach ($accounting->orders as $item)
                {{-- خدمات هزینه (مثل حمل و نقل) از مشتری دریافت می‌شوند و چاپ می‌شوند؛
                     فقط ردیف‌های قدیمی که مبلغی از مشتری برایشان ثبت نشده (مبلغ صفر) چاپ نمی‌شوند --}}
                @continue($item->product->isCostService() && (float)$item->price == 0)
                <tr>
                    <td>{{ $i }}</td>
                    <td style="font-weight: bold">{{ $item->product->name }}</td>
                    <td style="font-weight: bold">{{ $item->count }}</td>
                    <td style="font-weight: bold">{{ $item->product->unit != null ? $item->product->unit->title : 'عدد' }}</td>
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
                <td style="font-weight: bold" colspan="3">{{ $totalCount }}</td>
                <td style="font-weight: bold">
                    <span>{{ number_format($totalPrice * 10) }}</span>
                    <span> ریال</span>
                </td>
            </tr>
        </table>
        {{-- توضیحات پیش فاکتور زیر اقلام؛ فقط اگر ثبت شده باشد. قبل از پرینت همین‌جا قابل ویرایش است
             (فقط برای همین پرینت و در دیتابیس ذخیره نمی‌شود) و ارتفاعش به اندازه متن است تا اسکرول نخورد --}}
        @if(filled($accounting->invoice_description))
            <textarea id="invoice-description" class="auto-height mt-2 mb-3" rows="1"
                      title="قبل از پرینت می‌توانید متن را ویرایش کنید">{{ $accounting->invoice_description }}</textarea>
            {{-- نسخه چاپی همان متن؛ ارتفاعش در صفحه چاپ خودکار است و چیزی بریده نمی‌شود --}}
            <div class="auto-height-print mt-2 mb-3" aria-hidden="true">{{ $accounting->invoice_description }}</div>
        @endif
        @if(count($accounting->payments) > 0)
            <div class="print-tile print-subtile mt-5">
            <div class="mb-1">
                پرداختی ها
            </div>
            <table class="table table-bordered table-responsive-sm">
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">نوع پرداخت</th>
                    <th class="text-center">مبلغ (ریال)</th>
                    <th class="text-center">تاریخ</th>
                </tr>
                <tbody>
                @php
                    $i=1;
                    $totalPrice = 0;
                @endphp
                @foreach ($accounting->payments as $item)
                    <tr>
                        <td>{{ $i }}</td>
                        <td style="font-weight: bold">{{ $item->method }}</td>
                        <td style="font-weight: bold">{{ number_format($item->amount * 10) }}</td>
                        <td style="font-weight: bold">{{ $item->jalali_date }}</td>
                    </tr>
                    @php
                        $totalPrice += $item->amount;
                        $i++;
                    @endphp
                @endforeach
                </tbody>
                <tr style="-webkit-print-color-adjust: exact; background: #c7c7c7">
                    <td style="font-weight: bold" colspan="2">جمع</td>
                    <td style="font-weight: bold" colspan="2">
                        <span>{{ number_format($totalPrice * 10) }}</span>
                        <span> ریال</span>
                    </td>
                </tr>
            </table>
            </div>
        @endif
        @if(count($accounting->cheques) > 0)
            <div class="print-tile print-subtile mt-3">
            <div class="mb-1">
                چک ها
            </div>
            <table class="table table-bordered table-responsive-sm">
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">شماره چک</th>
                    <th class="text-center">بانک</th>
                    <th class="text-center">مبلغ (ریال)</th>
                    <th class="text-center">تاریخ سررسید</th>
                </tr>
                <tbody>
                @php
                    $i=1;
                    $totalPrice = 0;
                @endphp
                @foreach ($accounting->cheques as $item)
                    <tr>
                        <td>{{ $i }}</td>
                        <td style="font-weight: bold">{{ $item->number }}</td>
                        <td style="font-weight: bold">{{ $item->bank_name }}</td>
                        <td style="font-weight: bold">{{ number_format($item->amount * 10) }}</td>
                        <td style="font-weight: bold">{{ $item->jalali_due_date }}</td>
                    </tr>
                    @php
                        $totalPrice += $item->amount;
                        $i++;
                    @endphp
                @endforeach
                </tbody>
                <tr style="-webkit-print-color-adjust: exact; background: #c7c7c7">
                    <td style="font-weight: bold" colspan="3">جمع</td>
                    <td style="font-weight: bold" colspan="2">
                        <span>{{ number_format($totalPrice * 10) }}</span>
                        <span> ریال</span>
                    </td>
                </tr>
            </table>
            </div>
        @endif
    </div>


    <div class="row mt-5 pb-4 print-tile">
        <div class="col"></div>
        <div class="col-auto">
            <span style="margin-right: auto">مهر و امضای مشتری</span>
        </div>
    </div>


<script>
    // ارتفاع textarea به اندازه کل متن تا هیچ‌وقت اسکرول نخورد (هنگام تایپ، تغییر اندازه صفحه و قبل از پرینت)
    (function () {
        var areas = document.querySelectorAll('textarea.auto-height');

        function fit(el) {
            el.style.height = 'auto';
            // scrollHeight + ضخامت border تا هیچ بخشی از متن پنهان نشود
            el.style.height = (el.scrollHeight + el.offsetHeight - el.clientHeight) + 'px';
        }

        function fitAll() {
            areas.forEach(fit);
        }

        areas.forEach(function (el) {
            // متن ویرایش شده در نسخه چاپی هم قرار می‌گیرد
            var mirror = el.nextElementSibling;
            el.addEventListener('input', function () {
                fit(el);
                if (mirror && mirror.classList.contains('auto-height-print'))
                    mirror.textContent = el.value;
            });
        });
        window.addEventListener('resize', fitAll);
        // هر بار که عرض کادر تغییر کند (مثلاً بارگذاری دیرتر css) ارتفاع دوباره محاسبه می‌شود
        if (window.ResizeObserver) {
            var lastWidths = new WeakMap();
            var observer = new ResizeObserver(function (entries) {
                entries.forEach(function (entry) {
                    var width = entry.contentRect.width;
                    if (lastWidths.get(entry.target) !== width) {
                        lastWidths.set(entry.target, width);
                        setTimeout(function () {
                            fit(entry.target);
                        }, 0);
                    }
                });
            });
            areas.forEach(function (el) {
                observer.observe(el);
            });
        }
        window.addEventListener('load', fitAll);
        if (document.fonts && document.fonts.ready)
            document.fonts.ready.then(fitAll);
        fitAll();
    })();
</script>
</body>
</html>
