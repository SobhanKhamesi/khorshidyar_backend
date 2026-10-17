<!DOCTYPE html>
<html lang="fa-IR">
<head>
    <title>پرینت فاکتور</title>
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

    </style>


</head>
<body dir="rtl">
<div class="mx-auto mt-3 pb-4" style="max-width: 1000px">
    <div class="card-header" style="border: 1px solid blue;border-radius: 0.7rem">
        <div class="row text-center" style="align-items: center;">
            <div class="col text-center" id="title">
                انرژی پایدار ایواز
            </div>
            <div class="col">
                <img src="{{ asset("assets/picture/logo-ivaz.png") }}" alt="logo-ivaz" style="max-width: 7rem">
            </div>
        </div>
    </div>
    <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
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
    <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
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


    <div class="card-header mt-2" style="border: 1px solid blue;border-radius: 0.7rem">
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
        @if(count($accounting->payments) > 0)
            <div class="mt-5 mb-1">
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
        @endif
        @if(count($accounting->cheques) > 0)
            <div class="mt-3 mb-1">
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
        @endif
    </div>


    <div class="row my-5" style="color:#4d43ca">
        <textarea  rows="10" cols="100" style="border: 0; background: none;white-space: break-spaces;color: #4d43ca">{{ $accounting->description ?? "در این نیروگاه دستگاه از سری هیبرید ۶ کیلو وات گرووات با ۲ سال گارانتی
پنل های خورشیدی گرید A دارای ۱۵ سال گارانتی
استراکچر نیروگاهی با تاییدیه ساتبا استفاده شده است
سیم و کابل مصرفی به دلیل مشخص نبودن متراژ در این فاکتور لحاظ نشده است.
تهیه ارت به عهده کارفرما میباشد
در این پکیج تمامی تجهیزات و لوازم مورد نیاز (به جز سیم و کابل) از قبیل تابلو حفاظات ، تابلو چنج آور و بتون ، جرثقیل ، کارگر و ... در نظر گرفته شده است.
" }}</textarea>
    </div>

    <div class="row mt-5 pb-4">
        <div class="col"></div>
        <div class="col-auto">
            <span style="margin-right: auto">مهر و امضای مشتری</span>
        </div>
    </div>


</body>
</html>
