<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounting', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->references('id')->on('customers');
            $table->foreignUuid('pos_bank_account_id')->nullable()->references('id')->on('bank_accounts');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('automation', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->references('id')->on('customers');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignUuid('bank_id')->references('id')->on('banks');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->references('id')->on('customers');
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('advertising_id')->nullable()->references('id')->on('advertising');

        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->references('id')->on('customers');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->nullable()->references('id')->on('customers');
            $table->foreignUuid('bank_id')->nullable()->references('id')->on('banks');
            $table->foreignUuid('bank_account_id')->nullable()->references('id')->on('bank_accounts');
            $table->foreignUuid('invoice_id')->nullable()->references('id')->on('accounting');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('co_persons', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->references('id')->on('customers');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('source_id')->nullable()->references('id')->on('source_categories');
            $table->foreignUuid('advertising_id')->nullable()->references('id')->on('advertising');

        });

        Schema::table('customer_categories', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('failure_categories', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('source_categories', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->foreignUuid('report_id')->references('id')->on('daily_reports');
        });

        Schema::table('planning', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('sms', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('customer_id')->nullable()->references('id')->on('customers');

        });

        Schema::table('targeting', function (Blueprint $table) {
            $table->foreignUuid('user')->references('id')->on('users');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->nullable()->references('id')->on('accounting');
            $table->foreignUuid('automation_id')->nullable()->references('id')->on('automation');
            $table->foreignUuid('product_id')->references('id')->on('product');
        });

        Schema::table('pre_invoice_status', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->references('id')->on('accounting');
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('advertising', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });
        Schema::table('morakhasi', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });
        Schema::table('kala', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');

        });
        Schema::table('orderkala', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->references('id')->on('kala');

        });
        Schema::table('mosaede', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');

        });

        Schema::table('letter', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });

        Schema::table('mamoriat', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');

        });
        Schema::table('over_time', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');

        });
        Schema::table('orders_over_time', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->references('id')->on('over_time');

        });
        Schema::table('comment', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('leave_id')->nullable()->references('id')->on('morakhasi');

        });

        Schema::table('comment_automation', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('invoice_id')->nullable()->references('id')->on('automation');

        });

        Schema::table('comment_kala', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('kala_id')->nullable()->references('id')->on('kala');

        });

        Schema::table('comment_imprest', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('imprest_id')->nullable()->references('id')->on('mosaede');
        });

        Schema::table('comment_letter', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('letter_id')->nullable()->references('id')->on('letter');

        });


        Schema::table('comment_over_time', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('over_time_id')->nullable()->references('id')->on('over_time');

        });
        Schema::table('comment_mamoriat', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('mamoriat_id')->nullable()->references('id')->on('mamoriat');

        });
        Schema::table('ticket', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('userId')->nullable()->references('id')->on('users');
            $table->foreignUuid('customer_id')->references('id')->on('customers');


        });
        Schema::table('ticket_comment', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('ticket_id')->nullable()->references('id')->on('ticket');

        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('ticket_id')->nullable()->references('id')->on('ticket');

        });
        Schema::table('inventory', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });
        Schema::table('product_categories', function (Blueprint $table) {

            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('inventory_id')->references('id')->on('inventory');

        });
        Schema::table('product', function (Blueprint $table) {

            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->foreignUuid('category_id')->references('id')->on('product_categories');

        });

        Schema::table('sms_categories', function (Blueprint $table) {
            $table->foreignUuid('user_id')->references('id')->on('users');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('pos_bank_account_id');
            $table->dropColumn('user_id');
        });

        Schema::table('automation', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('user_id');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('bank_id');
            $table->dropColumn('user_id');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('user_id');
            $table->dropColumn('advertising_id');

        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('user_id');
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('bank_id');
            $table->dropColumn('bank_account_id');
            $table->dropColumn('invoice_id');
            $table->dropColumn('user_id');
        });

        Schema::table('co_persons', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('user_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('source_id');
            $table->dropColumn('advertising_id');
        });

        Schema::table('customer_categories', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('failure_categories', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('source_categories', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('daily_report_items', function (Blueprint $table) {
            $table->dropColumn('report_id');
        });

        Schema::table('planning', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });


        Schema::table('sms', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('customer_id');


        });

        Schema::table('targeting', function (Blueprint $table) {
            $table->dropColumn('user');
            $table->dropColumn('user_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
            $table->dropColumn('product_id');
        });

        Schema::table('pre_invoice_status', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
            $table->dropColumn('user_id');
        });

        Schema::table('advertising', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('morakhasi', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('kala', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('orderkala', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
        Schema::table('mosaede', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('letter', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('mamoriat', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('over_time', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('orders_over_time', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });

        Schema::table('comment', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('leave_id');
        });
        Schema::table('comment_automation', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('invoice_id');
        });

        Schema::table('comment_kala', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('kala_id');
        });

        Schema::table('comment_imprest', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('imprest_id');
        });
        Schema::table('comment_letter', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('letter_id');
        });
        Schema::table('comment_over_time', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('over_time_id');
        });
        Schema::table('comment_mamoriat', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('mamoriat_id');
        });
        Schema::table('ticket_comment', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('ticket_id');
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('ticket_id');
            $table->dropColumn('customer_id');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('inventory_id');
        });
        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('category_id');
        });
        Schema::table('sms_categories', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });


    }
};
