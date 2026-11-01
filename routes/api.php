<?php


use App\Http\Controllers\API\AfterSalesServiceController;
use App\Http\Controllers\API\AutomationController;
use App\Http\Controllers\API\DailyReportsController;
use App\Http\Controllers\API\BankAccountsController;
use App\Http\Controllers\API\FailureCategoriesController;
use App\Http\Controllers\API\InstallationServiceController;
use App\Http\Controllers\API\InstallerAppointmentController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\OpportunitiesController;
use App\Http\Controllers\API\ProductCategoryController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\ReferrerController;
use App\Http\Controllers\API\RemindersController;
use App\Http\Controllers\API\ServiceTypeController;
use App\Http\Controllers\API\SourceCategoriesController;
use App\Http\Controllers\API\SourceSmsController;
use App\Http\Controllers\API\TicketController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UserReportController;
use App\Http\Controllers\API\UsersController;
use App\Http\Controllers\API\MainController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\TargetingController;
use App\Http\Controllers\API\CustomerCategoriesController;
use App\Http\Controllers\API\LeadsController;
use App\Http\Controllers\API\PreInvoiceStatusController;
use App\Http\Controllers\API\CustomersController;
use App\Http\Controllers\API\CallsController;
use App\Http\Controllers\API\ProductsController;
use App\Http\Controllers\API\ChecksController;
use App\Http\Controllers\API\PlanningController;
use App\Http\Controllers\API\AccountingController;
use App\Http\Controllers\API\SmsController;
use App\Http\Controllers\API\ChartsController;
use Illuminate\Support\Facades\Route;


Route::post('user/login', [UsersController::class, 'login'])->name('login');

Route::get('survey/{token}', [RatingController::class, 'show']);
Route::post('survey/{token}', [RatingController::class, 'submit']);

Route::middleware('auth:sanctum')->group(callback: function () {


    Route::group(['prefix' => 'daily-reports'], function () {
        Route::get('index/{type}', [DailyReportsController::class, 'index']);
        Route::get('my-list', [DailyReportsController::class, 'MyList']);
        Route::post('store', [DailyReportsController::class, 'store']);
        Route::post('save', [DailyReportsController::class, 'save']);

        Route::post('update', [DailyReportsController::class, 'update']);
        Route::post('destroy', [DailyReportsController::class, 'destroy']);

        Route::get('{daily_report}', [DailyReportsController::class, 'show']);

    });
    Route::get('checking', [MainController::class, 'checking']);


    Route::get('user/logout', [UsersController::class, 'logout'])->name('logout');
    Route::post('user/register', [UsersController::class, 'store'])->name('register');
    Route::get('banks/list', [BankAccountsController::class, 'getBanksList']);
    Route::get('users', [UsersController::class, 'getUsers']);
    Route::get('technical_experts', [UsersController::class, 'getTechnicalExperts']);
    Route::get('installers', [UsersController::class, 'getInstallers']);
    Route::get('users-info', [UsersController::class, 'getUsersInfo']);
    Route::post('user/store', [UsersController::class, 'store'])->name('store');
    Route::post('user/edit', [UsersController::class, 'edit'])->name('edit');
    Route::post('user/toggle-status', [UsersController::class, 'toggleStatus'])->name('toggle-status');

    Route::get('user/show', [UsersController::class, 'getUserInfo']);
    Route::post('user/update-information', [UsersController::class, 'updateInformation']);
    Route::post('user/update-password', [UsersController::class, 'changePassword']);
    Route::post('user/update-info', [UsersController::class, 'changeInfo']);
    Route::post('users/quick-add', [UsersController::class, 'quickAdd']);

    Route::get('expired-items', [MainController::class, 'getExpiredItems']);

    Route::get('dashboard/widgets', [DashboardController::class, 'getWidgets']);
    Route::get('dashboard/charts', [DashboardController::class, 'getCharts']);


    Route::group(['prefix' => 'targeting'], function () {
        Route::get('list', [TargetingController::class, 'index']);
        Route::post('store', [TargetingController::class, 'store']);
        Route::post('update', [TargetingController::class, 'update']);
        Route::post('destroy', [TargetingController::class, 'destroy']);

        Route::get('{targeting}', [TargetingController::class, 'show']);
    });

    Route::group(['prefix' => 'source'], function () {
        Route::get('list', [SourceCategoriesController::class, 'index']);
        Route::post('store', [SourceCategoriesController::class, 'store']);
        Route::post('destroy', [SourceCategoriesController::class, 'destroy']);
        Route::post('update', [SourceCategoriesController::class, 'update']);

        Route::get('{source_category}', [SourceCategoriesController::class, 'show']);
    });

    Route::group(['prefix' => 'failure'], function () {
        Route::get('list', [FailureCategoriesController::class, 'index']);
        Route::post('store', [FailureCategoriesController::class, 'store']);
        Route::post('update', [FailureCategoriesController::class, 'update']);
        Route::post('destroy', [FailureCategoriesController::class, 'destroy']);

        Route::get('{failure_category}', [FailureCategoriesController::class, 'show']);
    });

    Route::group(['prefix' => 'customers'], function () {
        Route::group(['prefix' => 'categories'], function () {
            Route::get('list', [CustomerCategoriesController::class, 'index']);
            Route::post('store', [CustomerCategoriesController::class, 'store']);
            Route::post('update', [CustomerCategoriesController::class, 'update']);
            Route::post('destroy', [CustomerCategoriesController::class, 'destroy']);


            Route::get('{customer_category}', [CustomerCategoriesController::class, 'show']);
        });
        Route::group(['prefix' => 'leads'], function () {
            Route::get('list', [LeadsController::class, 'index']);
            Route::post('store', [LeadsController::class, 'store']);
            Route::post('update', [LeadsController::class, 'update']);
            Route::post('destroy', [LeadsController::class, 'destroy']);


            Route::get('{customer}', [LeadsController::class, 'show']);

        });
        Route::group(['prefix' => 'opportunities'], function () {
            Route::get('list', [OpportunitiesController::class, 'index']);
            Route::post('convert', [OpportunitiesController::class, 'convert']);

            Route::group(['prefix' => 'pre-invoice-status'], function () {
                Route::get('list/{accounting}', [PreInvoiceStatusController::class, 'index']);
                Route::post('store', [PreInvoiceStatusController::class, 'store']);
                Route::post('destroy', [PreInvoiceStatusController::class, 'destroy']);
            });

        });

        Route::get('list', [CustomersController::class, 'index']);
        Route::get('all', [CustomersController::class, 'getAllCustomersAndLeads']);
        Route::post('store', [CustomersController::class, 'store']);
        Route::post('quick-add', [CustomersController::class, 'quickStore']);
        Route::post('update', [CustomersController::class, 'update']);
        Route::post('destroy', [CustomersController::class, 'destroy']);

        Route::get('{customer}', [CustomersController::class, 'show']);

        Route::get('installation_services/{customer}', [CustomersController::class, 'installation_services']);
        Route::get('after_sale_services/{customer}', [CustomersController::class, 'after_sale_services']);
        Route::get('automations/{customer}/{status}', [CustomersController::class, 'automations']);
    });


    Route::group(['prefix' => 'referrers'], function () {
        Route::get('list', [ReferrerController::class, 'index']);
        Route::post('store', [ReferrerController::class, 'store']);
        Route::post('update/{referrer}', [ReferrerController::class, 'update']);
        Route::post('destroy/{referrer}', [ReferrerController::class, 'destroy']);
        Route::get('introduces/details/{customer}', [ReferrerController::class, 'introducesDetails']);
        Route::post('introduces/percent/{customer}', [ReferrerController::class, 'updatePercent']);
        Route::get('introduces/{referrer}', [ReferrerController::class, 'introduces']);
    });

    Route::group(['prefix' => 'calls'], function () {
        Route::get('list/{customer}', [CallsController::class, 'index']);
        Route::post('store', [CallsController::class, 'store']);
        Route::post('update', [CallsController::class, 'update']);
        Route::post('destroy', [CallsController::class, 'destroy']);

        Route::get('{call}', [CallsController::class, 'show']);
    });

    Route::group(['prefix' => 'bank-accounts'], function () {
        Route::get('list', [BankAccountsController::class, 'index']);
        Route::post('store', [BankAccountsController::class, 'store']);
        Route::post('update', [BankAccountsController::class, 'update']);
        Route::post('destroy', [BankAccountsController::class, 'destroy']);


        Route::get('{bank_account}', [BankAccountsController::class, 'show']);
    });

    Route::group(['prefix' => 'products'], function () {
        Route::get('list', [ProductsController::class, 'index']);
        Route::post('store', [ProductsController::class, 'store']);
        Route::post('update', [ProductsController::class, 'update']);
        Route::post('destroy', [ProductsController::class, 'destroy']);


        Route::get('{product}', [ProductsController::class, 'show']);
    });

    Route::group(['prefix' => 'checks'], function () {
        Route::get('list', [ChecksController::class, 'index']);
        Route::post('store', [ChecksController::class, 'store']);
        Route::post('update', [ChecksController::class, 'update']);
        Route::post('destroy', [ChecksController::class, 'destroy']);


        Route::get('{check}', [ChecksController::class, 'show']);

    });

    Route::group(['prefix' => 'reminders'], function () {
        Route::get('list', [RemindersController::class, 'index']);
        Route::post('store', [RemindersController::class, 'store']);
        Route::post('update', [RemindersController::class, 'update']);
        Route::post('destroy', [RemindersController::class, 'destroy']);


        Route::get('{reminder}', [RemindersController::class, 'show']);
    });

    Route::group(['prefix' => 'planning'], function () {
        Route::get('calendar', [PlanningController::class, 'calendar']);
        Route::get('list', [PlanningController::class, 'index']);
        Route::post('store', [PlanningController::class, 'store']);
        Route::post('update', [PlanningController::class, 'update']);
        Route::post('destroy', [PlanningController::class, 'destroy']);


        Route::get('{planning}', [PlanningController::class, 'show']);
    });

    Route::group(['prefix' => 'accounting'], function () {
        Route::get('list', [AccountingController::class, 'index']);
        Route::post('store', [AccountingController::class, 'store']);
        Route::post('update', [AccountingController::class, 'update']);
        Route::post('destroy', [AccountingController::class, 'destroy']);

        Route::get('{accounting}', [AccountingController::class, 'show']);
    });


    Route::group(['prefix' => 'automation'], function () {
        Route::get('list/{status}', [AutomationController::class, 'index']);
        Route::post('accept', [AutomationController::class, 'accept']);
        Route::post('quick-store', [AutomationController::class, 'quickStore']);
        Route::post('confirm-contract', [AutomationController::class, 'confirmContract']);
        Route::post('referrer-percent', [AutomationController::class, 'referrerPercent']);
        Route::post('success', [AutomationController::class, 'success']);
        Route::get('cancel/{automation}', [AutomationController::class, 'cancel']);
        Route::post('store', [AutomationController::class, 'store']);
        Route::post('comments', [AutomationController::class, 'comments']);
        Route::post('new-comment', [AutomationController::class, 'newComment']);
        Route::post('comment/destroy', [AutomationController::class, 'deleteComment']);
        Route::post('destroy', [AutomationController::class, 'destroy']);
        Route::post('update', [AutomationController::class, 'update']);
        Route::get('{automation}', [AutomationController::class, 'show']);
    });


    Route::group(['prefix' => 'source/sms'], function () {
        Route::get('list', [SourceSmsController::class, 'index']);
        Route::post('store', [SourceSmsController::class, 'store']);
        Route::post('update', [SourceSmsController::class, 'update']);
        Route::post('destroy', [SourceSmsController::class, 'destroy']);

        Route::get('{source_sms}', [SourceSmsController::class, 'show']);
    });


    Route::group(['prefix' => 'sms'], function () {
        Route::get('list', [SmsController::class, 'index']);
        Route::post('store', [SmsController::class, 'store']);
    });

    Route::prefix('installation-service')->group(function () {
        Route::get('list', [InstallationServiceController::class, 'list']);
        Route::get('customer/{customerId}/automations', [InstallationServiceController::class, 'customerAutomations']);
        Route::post('store', [InstallationServiceController::class, 'store']);
        Route::put('{id}', [InstallationServiceController::class, 'update']);
        Route::get('{id}', [InstallationServiceController::class, 'show']);
        Route::delete('destroy/{id}', [InstallationServiceController::class, 'destroy']);
    });

    Route::prefix('after-sales-service')->group(function () {
        Route::get('list', [AfterSalesServiceController::class, 'list']);
        Route::get('{id}', [AfterSalesServiceController::class, 'show']);
        Route::put('{id}', [AfterSalesServiceController::class, 'update']);
        Route::get('customer-history/{customerId}', [AfterSalesServiceController::class, 'customerHistory']);
        Route::post('store', [AfterSalesServiceController::class, 'store']);
        Route::put('complete/{id}', [AfterSalesServiceController::class, 'complete']);
    });

    Route::group(['prefix' => 'charts'], function () {

        Route::group(['prefix' => 'leads'], function () {
            Route::get('method', [ChartsController::class, 'getMethod']);
            Route::post('status', [ChartsController::class, 'getStatus']);
        });

        Route::group(['prefix' => 'activities'], function () {
            Route::post('targeting', [ChartsController::class, 'getTargeting']);
            Route::post('report', [ChartsController::class, 'getReport']);
        });

        Route::group(['prefix' => 'sales'], function () {
            Route::get('products', [ChartsController::class, 'getProducts']);
            Route::post('invoice', [ChartsController::class, 'getInvoice']);
            Route::post('seller', [ChartsController::class, 'getSeller']);

        });


    });


    Route::group(['prefix' => 'service_types'], function () {
        Route::get('list', [ServiceTypeController::class, 'index']);
        Route::post('store', [ServiceTypeController::class, 'quickAdd']);
        Route::post('update/{service_type}', [ServiceTypeController::class, 'update']);
        Route::post('quick-add', [ServiceTypeController::class, 'quickAdd']);
        Route::post('destroy', [ServiceTypeController::class, 'destroy']);
    });

    Route::group(['prefix' => 'UserReport'], function () {
        Route::post('list', [UserReportController::class, 'index']);
    });


    Route::group(['prefix' => 'ticket'], function () {
        Route::post('store', [TicketController::class, 'store']);
        Route::get('listUndo/{status}', [TicketController::class, 'indexUndo']);
        Route::get('listDone/{status}', [TicketController::class, 'indexDone']);
        Route::get('listDoing/{status}', [TicketController::class, 'indexDoing']);
        Route::post('comments', [TicketController::class, 'comments']);
        Route::post('new-comment', [TicketController::class, 'newComment']);
        Route::post('comment/destroy', [TicketController::class, 'deleteComment']);
        Route::post('attachments', [TicketController::class, 'attachments']);
        Route::post('new-attachment', [TicketController::class, 'newAttachment']);
        Route::post('attachment/destroy', [TicketController::class, 'deleteAttachment']);
        Route::post('show/{ticket}', [TicketController::class, 'show']);

        Route::get('accept/{ticket}', [TicketController::class, 'accept']);

        Route::post('update', [TicketController::class, 'update']);
        Route::post('destroy', [TicketController::class, 'destroy']);
    });
    Route::group(['prefix' => 'inventory'], function () {
        Route::post('store', [InventoryController::class, 'store']);
        Route::get('list', [InventoryController::class, 'index']);
        Route::get('{inventory}', [InventoryController::class, 'show']);
        Route::post('update', [InventoryController::class, 'update']);
        Route::post('destroy', [InventoryController::class, 'destroy']);


    });

    Route::group(['prefix' => 'units'], function () {
        Route::get('list', [UnitController::class, 'index']);
        Route::post('store', [UnitController::class, 'store']);
        Route::post('update', [UnitController::class, 'update']);
        Route::post('destroy', [UnitController::class, 'destroy']);

        Route::get('{unit}', [UnitController::class, 'show']);
    });

    Route::group(['prefix' => 'ProductCategory'], function () {
        Route::post('destroy', [ProductCategoryController::class, 'destroy']);

        Route::post('update', [ProductCategoryController::class, 'update']);
        Route::post('store', [ProductCategoryController::class, 'store']);
        Route::get('list', [ProductCategoryController::class, 'index']);
        Route::post('product', [ProductCategoryController::class, 'product']);
        Route::post('new-product', [ProductCategoryController::class, 'newProduct']);
        Route::post('product/destroy', [ProductCategoryController::class, 'deleteProduct']);
        Route::get('{Product_Category}', [ProductCategoryController::class, 'show']);


    });


});
