{{--
    ابزار مشترک صفحه‌های پرینت (فاکتور فروش و برگه‌ی نصب):
     - هر عنصر با کلاس print-tile یک دکمه‌ی ✕ دارد و قبل از پرینت از صفحه حذف می‌شود
       (فقط برای همین پرینت؛ چیزی ذخیره نمی‌شود و با «بازگردانی» برمی‌گردد).
       print-subtile برای بخش‌های داخل یک کاشی است (مثل پرداختی‌ها و چک‌ها).
     - هدر و فوتر پیش‌فرض مرورگر (عنوان، لینک صفحه و ...) چاپ نمی‌شود و فقط پایین هر صفحه
       شماره صفحه و تاریخ/ساعت لحظه‌ی پرینت نوشته می‌شود (هم‌خوان با src/utils/printPageFooter.js در فرانت).
    باید داخل body و بالای محتوای صفحه include شود.
--}}
<style>
    .print-tile {
        position: relative;
    }

    .btn-remove-tile {
        position: absolute;
        /* روی خط بالای کاشی تا روی متن آن نیفتد */
        top: -11px;
        left: 10px;
        z-index: 2;
        border: 1px solid #dc3545;
        color: #dc3545;
        background: #fff;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        line-height: 1;
        font-size: 0.8rem;
        cursor: pointer;
        padding: 0;
    }

    /* بخش‌های داخل یک کاشی: دکمه کنار عنوان همان بخش */
    .print-subtile > .btn-remove-tile {
        top: 0;
        left: 0;
    }

    .btn-remove-tile:hover {
        background: #dc3545;
        color: #fff;
    }

    .print-toolbar {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        align-items: center;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
    }

    @page {
        margin: 12mm 10mm 14mm;
    }

    /* هیچ‌کدام از دکمه‌ها و ابزارها در خروجی پرینت نمایش داده نمی‌شوند */
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>
<style id="print-page-footer"></style>

<div class="print-toolbar no-print">
    <span class="text-muted me-auto">با دکمه ✕ هر کاشی را می‌توانید قبل از پرینت از صفحه حذف کنید.</span>
    <button type="button" id="restore-tiles" class="btn btn-sm btn-outline-secondary" onclick="restoreTiles()" style="display: none">
        بازگردانی کاشی‌های حذف شده
    </button>
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">پرینت</button>
</div>

<script>
    // دکمه‌ی ✕ برای همه‌ی کاشی‌ها (بعد از بارگذاری کامل صفحه)
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.print-tile').forEach(function (tile) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn-remove-tile no-print';
            button.title = 'حذف این بخش از پرینت';
            button.textContent = '✕';
            button.addEventListener('click', function () {
                hideTile(tile);
            });
            tile.insertBefore(button, tile.firstChild);
        });
    });

    function hideTile(tile) {
        tile.classList.add('tile-hidden');
        tile.style.display = 'none';
        document.getElementById('restore-tiles').style.display = '';
    }

    function restoreTiles() {
        document.querySelectorAll('.print-tile.tile-hidden').forEach(function (tile) {
            tile.classList.remove('tile-hidden');
            // کاشی‌ای که همه‌ی ردیف‌هایش جداگانه حذف شده (tile-empty)، خالی برنمی‌گردد
            if (!tile.classList.contains('tile-empty'))
                tile.style.display = '';
        });
        document.getElementById('restore-tiles').style.display = 'none';
    }

    // پاورقی پرینت: فقط شماره صفحه و تاریخ/ساعت شمسی لحظه‌ی پرینت.
    // وقتی محتوای حاشیه‌های @page تعریف شود، مرورگر هدر و فوتر پیش‌فرض خودش را چاپ نمی‌کند.
    (function () {
        var style = document.getElementById('print-page-footer');

        function stamp() {
            var now = new Date();
            var day = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {year: 'numeric', month: '2-digit', day: '2-digit'}).format(now);
            var time = new Intl.DateTimeFormat('fa-IR', {hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).format(now);
            return (day + ' - ساعت ' + time).replace(/["\\]/g, '');
        }

        function refresh() {
            var box = 'font-family: IRANSans, Tahoma, sans-serif; font-size: 9pt; color: #555;';
            style.textContent = '@page {'
                + ' @top-left { content: ""; } @top-center { content: ""; } @top-right { content: ""; }'
                + ' @bottom-right { content: "صفحه " counter(page, persian) " از " counter(pages, persian); ' + box + ' }'
                + ' @bottom-center { content: ""; }'
                + ' @bottom-left { content: "' + stamp() + '"; ' + box + ' }'
                + ' }';
        }

        refresh();
        window.addEventListener('beforeprint', refresh);
    })();
</script>
