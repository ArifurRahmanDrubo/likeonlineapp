<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PppoeProfile;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\HelloController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\UpzilaController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PackageController;

use App\Http\Controllers\PayheadController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\HrSettingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubZoneController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MikroTikController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmailSetupController;
use App\Http\Controllers\NewRequestController;
use App\Http\Controllers\ResignruleController;
use App\Http\Controllers\MackpackageController;
use App\Http\Controllers\MacResellerController;
use App\Http\Controllers\ResellerBoxController;
use App\Http\Controllers\SystemSetupController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WebCustomerController;
use App\Http\Controllers\Api\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Api\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Api\Portal\BillingController as PortalBillingController;
use App\Http\Controllers\Api\Portal\BkashPaymentController as PortalBkashPaymentController;
use App\Http\Controllers\Api\Portal\NagadPaymentController as PortalNagadPaymentController;
use App\Http\Controllers\Api\Portal\PackageController as PortalPackageController;
use App\Http\Controllers\EquipmentUseController;
use App\Http\Controllers\InvoiceSetupController;
use App\Http\Controllers\ProtocolTypeController;
use App\Http\Controllers\ResellerZoneController;
use App\Http\Controllers\BandwidthbillController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\ConnectionTypeController;
use App\Http\Controllers\MikrotikServerController;
use App\Http\Controllers\ResellerUpzilaController;
use App\Http\Controllers\DailyCollectionController;
use App\Http\Controllers\ResellerSubzoneController;
use App\Http\Controllers\ResellerDistrictController;
use App\Http\Controllers\ResellerPositionController;
use App\Http\Controllers\RoleAndPermissionController;
use App\Http\Controllers\ResellerDepartmentController;
use App\Http\Controllers\CustomerBillingStatusController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//web
Route::post('/contact', [WebCustomerController::class, 'storeContact']);

// Public branding endpoint — lets the public login/register pages show the
// company name/logo without authentication.
Route::get('/public/company-profile', [CompanyProfileController::class, 'index']);

Route::get('/get-webpackage', [WebCustomerController::class, 'webpackage']);
Route::post('/customer-new-line', [WebCustomerController::class, 'storeCustomerNewLine']);



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [HelloController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'SendOTPCode']);
Route::post('/verify-otp', [AuthController::class, 'VerifyOTP']);
Route::post('/reset-password', [AuthController::class, 'ResetPassword'])->middleware('auth:sanctum');
Route::put('/updatepassword', [AuthController::class, 'UpdatePassword'])->middleware('auth:sanctum');
Route::post('/user-profile', [UserProfileController::class, 'UserProfile'])->middleware('auth:sanctum');
Route::get('/user-profile', [UserProfileController::class, 'getUserProfile'])->middleware('auth:sanctum');
Route::delete('/user-delete', [UserProfileController::class, 'Userdelete'])->middleware('auth:sanctum');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
Route::post('/logoutOtherUser', [AuthController::class, 'logoutOtherUser'])->middleware('auth:sanctum')->name('logoutOtherUser');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->get('/user-permissions', [RoleAndPermissionController::class, 'getUserPermissions']);

// ---------------------------------------------------------------------------
// Customer Portal (self-service)
// ---------------------------------------------------------------------------
// Public Portal Routes
Route::prefix('portal')->group(function () {
    Route::post('/register', [PortalAuthController::class, 'register']);
    Route::post('/login', [PortalAuthController::class, 'customerLogin']);
});

// Two-step email-verified registration (OTP via email, 10-minute validity):
//   POST /api/register/send-otp   -> validate credentials, email the 6-digit OTP
//   POST /api/register/verify-otp -> verify OTP, create + bind account, auto-login
Route::post('/register/send-otp', [PortalAuthController::class, 'sendOtp']);
Route::post('/register/verify-otp', [PortalAuthController::class, 'verifyOtp']);

// Protected Customer Portal Routes
Route::middleware(['auth:sanctum', 'role:client'])->prefix('portal')->group(function () {
    Route::get('/me', [PortalAuthController::class, 'me']);
    Route::get('/dashboard', [PortalDashboardController::class, 'index']);
    Route::get('/invoices', [PortalBillingController::class, 'invoices']);
    Route::get('/invoices/{id}/pdf', [PortalBillingController::class, 'downloadPdf']);
    Route::get('/payments/{id}/pdf', [PortalBillingController::class, 'paymentPdf']);
    Route::get('/reports/all-bills-pdf', [PortalBillingController::class, 'allBillsPdf']);
    Route::get('/reports/all-payments-pdf', [PortalBillingController::class, 'allPaymentsPdf']);
    Route::post('/payments/submit', [PortalBillingController::class, 'submitPayment']);

    // Online payment gateways (bKash / Nagad) — create the checkout session
    Route::post('/payments/bkash/create', [PortalBkashPaymentController::class, 'createPayment']);
    Route::post('/payments/nagad/create', [PortalNagadPaymentController::class, 'createPayment']);
    Route::get('/packages', [PortalPackageController::class, 'index']);
    Route::post('/package-change', [PortalPackageController::class, 'requestChange']);

    // Realtime bandwidth monitor (scoped to the authenticated customer)
    Route::get('/bandwidth/status', [PortalDashboardController::class, 'status']);
    Route::post('/bandwidth/start', [PortalDashboardController::class, 'startMonitor']);
    Route::post('/bandwidth/stop', [PortalDashboardController::class, 'stopMonitor']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    //configuration
    Route::get('get-zones', [ZoneController::class, 'index'])->middleware('permission:zone,read');
    Route::post('create-zones', [ZoneController::class, 'store'])->middleware('permission:zone,write');
    Route::post('update-zones', [ZoneController::class, 'update'])->middleware('permission:zone,write');
    Route::post('delete-zones', [ZoneController::class, 'destroy'])->middleware('permission:zone,full');
    Route::post('subzones/delete-multiple', [ZoneController::class, 'deleteMultiple'])->middleware('permission:subzone,full');
    //subzone
    Route::get('get-subzones', [SubZoneController::class, 'index'])->middleware('permission:subzone,read');
    Route::post('create-subzones', [SubZoneController::class, 'store'])->middleware('permission:subzone,write');
    Route::post('update-subzones', [SubZoneController::class, 'update'])->middleware('permission:subzone,write');
    Route::post('delete-subzones', [SubZoneController::class, 'destroy'])->middleware('permission:subzone,full');
    Route::get('subzone-by-zone-id', [SubZoneController::class, 'Subzonebyzone'])->middleware('permission:subzone,write');
    Route::post('subzones/delete-multiple', [SubZoneController::class, 'deleteMultiple'])->middleware('permission:subzone,full');
    //box
    Route::get('get-boxes', [BoxController::class, 'index'])->middleware('permission:box,read');
    Route::post('create-boxes', [BoxController::class, 'store'])->middleware('permission:box,write');
    Route::post('update-boxes', [BoxController::class, 'update'])->middleware('permission:box,write');
    Route::post('delete-boxes', [BoxController::class, 'destroy'])->middleware('permission:box,full');
    Route::post('boxes/delete-multiple', [BoxController::class, 'deleteMultiple'])->middleware('permission:box,full');
    //connectiontype
    Route::get('get-connection-type', [ConnectionTypeController::class, 'index'])->middleware('permission:connection_type,read');
    Route::post('create-connection-type', [ConnectionTypeController::class, 'store'])->middleware('permission:connection_type,write');
    Route::post('update-connection-type', [ConnectionTypeController::class, 'update'])->middleware('permission:connection_type,write');
    Route::post('delete-connection-type', [ConnectionTypeController::class, 'destroy'])->middleware('permission:connapi/portal/bandwidth/startection_type,full');
    Route::post('connection-type/delete-multiple', [ConnectionTypeController::class, 'deleteMultiple'])->middleware('permission:connection_type,full');
    //clienttype
    Route::get('get-client-type', [ClientTypeController::class, 'index'])->middleware('permission:client_type,read');

    Route::post('delete-client-type', [ClientTypeController::class, 'destroy'])->middleware('permission:client_type,full');
    Route::post('client-types/delete-multiple', [ClientTypeController::class, 'deleteMultiple'])->middleware('permission:client_type,full');
    //
    Route::post('update-client-types', [ClientTypeController::class, 'update'])->middleware('permission:client_type,full');
    Route::post('create-client-types', [ClientTypeController::class, 'store'])->middleware('permission:client_type,write');


    //protocoltype
    Route::get('get-protocol-type', [ProtocolTypeController::class, 'index'])->middleware('permission:protocol_type,read');
    Route::post('create-protocol-type', [ProtocolTypeController::class, 'store'])->middleware('permission:protocol_type,write');
    Route::post('update-protocol-type', [ProtocolTypeController::class, 'update'])->middleware('permission:protocol_type,write');
    Route::post('delete-protocol-type', [ProtocolTypeController::class, 'destroy'])->middleware('permission:protocol_type,full');
    Route::post('protocol-type/delete-multiple', [ProtocolTypeController::class, 'deleteMultiple'])->middleware('permission:protocol_type,full');
    //billingstatus
    Route::get('get-customer-billingstatus', [CustomerBillingStatusController::class, 'index'])->middleware('permission:billing_status,read');

    Route::post('delete-customer-billingstatus', [CustomerBillingStatusController::class, 'destroy'])->middleware('permission:billing_status,full');
    Route::post('update-billing-status', [CustomerBillingStatusController::class, 'update'])->middleware('permission:billing_status,write');
    Route::post('create-billing-status', [CustomerBillingStatusController::class, 'store'])->middleware('permission:billing_status,write');
    Route::post('customer-billingstatus/delete-multiple', [CustomerBillingStatusController::class, 'deleteMultiple'])->middleware('permission:billing_status,full');

    ///district
    Route::get('get-district', [DistrictController::class, 'index'])->middleware('permission:district,read');
    Route::post('create-district', [DistrictController::class, 'store'])->middleware('permission:district,write');
    Route::post('update-district', [DistrictController::class, 'update'])->middleware('permission:district,write');
    Route::post('delete-district', [DistrictController::class, 'destroy'])->middleware('permission:district,full');

    Route::post('district/delete-multiple', [DistrictController::class, 'deleteMultiple'])->middleware('permission:district,full');
    //upzila
    Route::get('get-upzila', [UpzilaController::class, 'index'])->middleware('permission:upzila,read');

    Route::post('delete-upzila', [UpzilaController::class, 'destroy'])->middleware('permission:upzila,full');
    Route::post('update-upzila', [UpzilaController::class, 'update'])->middleware('permission:upzila,write');
    Route::post('create-upzila', [UpzilaController::class, 'store'])->middleware('permission:upzila,write');
    Route::post('upzila/delete-multiple', [UpzilaController::class, 'deleteMultiple'])->middleware('permission:upzila,full');
    //package
    Route::get('get-package', [PackageController::class, 'index'])->middleware('permission:package,read');

    Route::post('delete-package', [PackageController::class, 'destroy'])->middleware('permission:package,full');
    Route::post('update-package', [PackageController::class, 'update'])->middleware('permission:package,write');
    Route::post('create-package', [PackageController::class, 'store'])->middleware('permission:package,write');
    Route::post('package/delete-multiple', [PackageController::class, 'deleteMultiple'])->middleware('permission:package,full');


    //HR &PAYROLL SECTION
    Route::get('get-department', [DepartmentController::class, 'index'])->middleware('permission:department,read');

    Route::post('delete-department', [DepartmentController::class, 'destroy'])->middleware('permission:department,full');
    Route::post('update-department', [DepartmentController::class, 'update'])->middleware('permission:department,write');
    Route::post('create-department', [DepartmentController::class, 'store'])->middleware('permission:department,write');
    Route::post('department/delete-multiple', [DepartmentController::class, 'deleteMultiple'])->middleware('permission:department,full');

    //resignrule
    Route::get('get-resignrule', [ResignruleController::class, 'index'])->middleware('permission:resign_rule,read');
    Route::post('update-resignrule', [ResignruleController::class, 'update'])->middleware('permission:resign_rule,write');
    Route::post('create-resignrule', [ResignruleController::class, 'store'])->middleware('permission:resign_rule,write');
    Route::post('delete-resignrule', [ResignruleController::class, 'destroy'])->middleware('permission:resign_rule,full');
    Route::post('resignrule/delete-multiple', [ResignruleController::class, 'deleteMultiple'])->middleware('permission:resign_rule,full');

    //position
    Route::get('get-position', [PositionController::class, 'index'])->middleware('permission:position,read');
    Route::post('create-position', [PositionController::class, 'store'])->middleware('permission:position,write');
    Route::post('update-position', [PositionController::class, 'update'])->middleware('permission:position,write');
    Route::post('delete-position', [PositionController::class, 'destroy'])->middleware('permission:position,full');
    Route::post('positions/delete-multiple', [PositionController::class, 'deleteMultiple'])->middleware('permission:position,full');

    //MacResseler
    Route::get('get-mackpackage', [MackpackageController::class, 'index'])->middleware('permission:mac_package,read');
    Route::post('update-mackpackage', [MackpackageController::class, 'update'])->middleware('permission:mac_package,write');
    Route::post('create-mackpackage', [MackpackageController::class, 'store'])->middleware('permission:mac_package,write');
    Route::post('delete-mackpackage', [MackpackageController::class, 'destroy'])->middleware('permission:mac_package,full');
    Route::post('mackpackage/delete-multiple', [MackpackageController::class, 'deleteMultiple'])->middleware('permission:mac_package,full');
    Route::post('tariffs', [TariffController::class, 'store'])->middleware('permission:tariff,write');
    Route::get('tariffs', [TariffController::class, 'index'])->middleware('permission:tariff,read');
    Route::post('getTariffByid', [TariffController::class, 'getTariffByid'])->middleware('permission:tariff,write');
    Route::post('updatetariffs', [TariffController::class, 'updatetariffs'])->middleware('permission:tariff,write');
    Route::post('deletetariff', [TariffController::class, 'deleteTariff'])->middleware('permission:tariff,full');
    //MikrotikServer
    Route::get('hello', [HelloController::class, 'hello']);
    Route::post('user-status', [HelloController::class, 'UserStatus']);
    Route::get('get-mikrotikserver', [MikroTikController::class, 'index'])->middleware('permission:mikrotik_server,read');
    Route::get('get-muser/{id}', [MikroTikController::class, 'getMUser']);

    Route::post('update-mikrotikserver', [MikroTikController::class, 'update'])->middleware('permission:mikrotik_server,write');
    Route::post('delete-mikrotikserver', [MikroTikController::class, 'destroy'])->middleware('permission:mikrotik_server,full');
    Route::post('create-mikrotikserver', [MikroTikController::class, 'store'])->middleware('permission:mikrotik_server,write');
    Route::post('/mikrotik/sync/{serverId}', [MikroTikController::class, 'syncServerData']);
    Route::post('getusers-mikrotikserver', [MikroTikController::class, 'getUsers']);
    // Route::post('mikrotik/trigger-live-traffic', [MikroTikController::class, 'triggerLiveTraffic']);
    Route::post('mikrotik/start-monitoring', [MikroTikController::class, 'startMonitoring']);
    Route::post('mikrotik/stop-monitoring', [MikroTikController::class, 'stopMonitoring']);
    //pppoeProfile
    Route::get('get-PPPOEProfile', [PppoeProfile::class, 'getpppoeProfile']);
    Route::get('get-PPPOEIPpool', [PppoeProfile::class, 'getpppoeIPpool']);
    Route::post('create-PPPOEProfile', [PppoeProfile::class, 'createpppoeProfile']);
    Route::post('edit-PPPOEProfile', [PppoeProfile::class, 'editpppoeProfile']);
    Route::post('delete-PPPOEProfile', [PppoeProfile::class, 'deletepppoeProfile']);
    Route::post('create-PPPOEIpPool', [PppoeProfile::class, 'createpppoeIppool']);
    Route::post('edit-PPPOEIpPool', [PppoeProfile::class, 'editPppoeIppool']);
    Route::post('delete-PPPOEIpPool', [PppoeProfile::class, 'deletePppoeIppool']);
    Route::get('getProfile-mikrotikserver', [MikroTikController::class, 'getPppoeProfiles']);
    Route::post('mikrotikserver/delete-multiple', [MikroTikController::class, 'deleteMultiple'])->middleware('permission:mikrotik_server,full');
    Route::get('get-MacReseller', [MacResellerController::class, 'index'])->middleware('permission:mac_reseller,read');
    Route::post('create-MacReseller', [MacResellerController::class, 'store'])->middleware('permission:mac_reseller,rwrite');
    Route::post('update-MacReseller', [MacResellerController::class, 'updateMacReseller'])->middleware('permission:mac_reseller,write');
    Route::post('delete-MacReseller', [MacResellerController::class, 'destroy'])->middleware('permission:mac_reseller,rfull');
    Route::post('updateResellerType', [MacResellerController::class, 'updateResellerType'])->middleware('permission:mac_reseller,write');
    Route::get('getMacResellerbyId', [MacResellerController::class, 'getMacResellerbyId'])->middleware('permission:mac_reseller,write');
    Route::get('getMacResellerAllDatabyId', [MacResellerController::class, 'getMacResellerAllDatabyId']);
    Route::get('get-mac-reseller/{id}', [MacResellerController::class, 'getMacReseller']);



    //connectionMikrotikROUTER and ROUTER
    Route::get('/mikrotik/users', [MikroTikController::class, 'getUsers']);
    Route::post('/mikrotik/user/add', [MikroTikController::class, 'addUser']);


    // Newrequest
    Route::post('delete-newrequest', [NewRequestController::class, 'destroy']);
    Route::post('create-newrequest', [NewRequestController::class, 'store']);
    Route::post('assign_to', [NewRequestController::class, 'assignto']);
    Route::post('complete-newrequest', [NewRequestController::class, 'completed']);
    Route::get('newrequest', [NewRequestController::class, 'index']);
    Route::post('getNewRequestQuery', [NewRequestController::class, 'queryData']);

    Route::post('newrequest/delete-multiple', [NewRequestController::class, 'deleteMultiple']);

    //New Customer
    Route::post('create-customer', [CustomerController::class, 'store']);
    Route::post('delete-customer', [CustomerController::class, 'makeClientLeft']);
    Route::post('make-client-left', [CustomerController::class, 'makeClientLeft'])->middleware('permission:left_client,write');
    Route::post('restore-left-client', [CustomerController::class, 'restoreLeftClient'])->middleware('permission:left_client,write');
    Route::get('customers/left-clients', [CustomerController::class, 'leftClients']);
    Route::get('billingLists', [CustomerController::class, 'index']);
    Route::get('clientProfileData', [CustomerController::class, 'clientData']);
    Route::get('clientList', [HelloController::class, 'clientList']);
    Route::get('customers/{id}/live-mac', [CustomerController::class, 'getCustomerLiveMac']);
    Route::get('clientlistdashboard', [CustomerController::class, 'clientlistdashboard']);
    Route::get('billinglistdashboard', [CustomerController::class, 'billinglistdashboard']);

    Route::get('getcustomerData', [CustomerController::class, 'index']);
    //Master init: all AddNewClient dropdown/lookup data in one request
    Route::get('client-form-init', [CustomerController::class, 'getClientFormInitData']);
    Route::get('get-client/{id}', [CustomerController::class, 'getClient']);
    Route::post('update-clientbillingstatus', [CustomerController::class, 'updateclientbillingstatus']);
    Route::post('update-packageStatus', [CustomerController::class, 'updatepPackageStatus']);
    Route::post('customers/{id}/change-package', [CustomerController::class, 'changePackage']);
    Route::post('customers/{id}/change-status', [CustomerController::class, 'changeStatus']);
    Route::get('customers/{id}/changes', [CustomerController::class, 'getCustomerChanges']);

    // Change requests (approve/reject/edit date) + scheduler (queue/force-run/retry)
    Route::get('change-requests', [ChangeRequestController::class, 'index'])->middleware('permission:change_request,read');
    Route::post('change-requests/{id}/approve', [ChangeRequestController::class, 'approve'])->middleware('permission:change_request,write');
    Route::post('change-requests/{id}/reject', [ChangeRequestController::class, 'reject'])->middleware('permission:change_request,write');
    Route::post('change-requests/{id}/update-date', [ChangeRequestController::class, 'updateDate'])->middleware('permission:change_request,write');
    Route::get('scheduler', [ChangeRequestController::class, 'schedulerIndex'])->middleware('permission:scheduler,read');
    Route::post('scheduler/{id}/force-run', [ChangeRequestController::class, 'forceRun'])->middleware('permission:scheduler,write');
    Route::post('scheduler/{id}/retry', [ChangeRequestController::class, 'retry'])->middleware('permission:scheduler,write');
    // Backend PDF generation (mPDF): only the sections passed via sections[] are rendered
    Route::get('get-client-pdf', [CustomerController::class, 'generateClientProfilePdf']);

    Route::post('updatebulkstatus', [CustomerController::class, 'updatebulkStatus'])->middleware('permission:bulk_status_change,write');
    Route::post('disabledSelectedClient', [CustomerController::class, 'disabledSelectedClient']);
    Route::post('enabledSelectedClient', [CustomerController::class, 'enabledSelectedClient']);
    Route::post('toggleClient', [CustomerController::class, 'toggleClient']);
    Route::post('updatebulkstatus', [CustomerController::class, 'updatebulkStatus']);
    Route::post('bindMac', [CustomerController::class, 'bindMac']);
    Route::post('unbindMac', [CustomerController::class, 'unbindMac']);
    Route::post('bindSelectedMacAddresses', [CustomerController::class, 'bindSelectedMacAddresses']);
    Route::post('unbindSelectedMacAddresses', [CustomerController::class, 'unbindSelectedMacAddresses']);
    Route::post('unbindMac', [CustomerController::class, 'unbindMac']);
    //daily Bill Collectiond
    Route::get('dailycollectiondashboard', [DailyCollectionController::class, 'dailycollectiondashboard']);
    Route::get('getDailyBillCollection', [DailyCollectionController::class, 'getDailyBillCollection']);
    Route::get('getDailyBillCollectionQuery', [DailyCollectionController::class, 'getDailyBillCollectionQuery']);


    //Report
    Route::get('getBillCollection', [ReportController::class, 'getBillCollection']);
    Route::get('getBillCollectionQuery', [ReportController::class, 'getBillCollectionQuery']);
    Route::get('getDiscountReport', [ReportController::class, 'getDiscountReport']);
    Route::get('getDiscountReportQuery', [ReportController::class, 'getDiscountReportQuery']);
    Route::get('getCustomerReport', [ReportController::class, 'getCustomerReport']);
    Route::post('getCustomerReportQuery', [ReportController::class, 'getCustomerReportQuery']);
    //employee
    Route::post('create-employee', [EmployeeController::class, 'store'])->middleware('permission:add_employee,write');
    Route::post('delete-employee', [HelloController::class, 'DeleteEmployee'])->middleware('permission:delete_employee_profile,full');
    Route::post('remove-mikrotikuser', [CustomerController::class, 'removeMikrotikUser']);

    Route::get('getemployee', [EmployeeController::class, 'index'])->middleware('permission:employee_list,read');
    Route::get('get-employee/{id}', [EmployeeController::class, 'getEmployee'])->middleware('permission:employee_list,read');
    //attendance
    Route::post('create-attendance', [AttendanceController::class, 'store'])->middleware('permission:attendance,write');
    Route::post('update-attendance', [AttendanceController::class, 'update'])->middleware('permission:attendance,write');
    Route::get('get-attendance', [AttendanceController::class, 'index'])->middleware('permission:attendance,read');
    Route::post('attendance/bulk', [AttendanceController::class, 'bulkStore'])->middleware('permission:attendance,write');
    Route::get('get-attendance-monthly', [AttendanceController::class, 'monthly'])->middleware('permission:attendance,read');
    // daily punch sheet
    Route::get('daily-attendance', [AttendanceController::class, 'dailySheet'])->middleware('permission:attendance,read');
    Route::post('save-daily-attendance', [AttendanceController::class, 'saveDailySheet'])->middleware('permission:attendance,write');
    // shifts & HR rule settings
    Route::get('shifts', [ShiftController::class, 'index'])->middleware('permission:attendance,read');
    Route::post('save-shifts', [ShiftController::class, 'save'])->middleware('permission:attendance,write');
    Route::get('hr-settings', [HrSettingController::class, 'index'])->middleware('permission:attendance,read');
    Route::post('save-hr-settings', [HrSettingController::class, 'save'])->middleware('permission:attendance,write');
    //allowance
    Route::post('create-allowance', [EmployeeController::class, 'allowance'])->middleware('permission:allowance,write');
    Route::get('get-allowance', [EmployeeController::class, 'getAllowance'])->middleware('permission:allowance,read');
    Route::post('delete-allowance', [EmployeeController::class, 'deleteAllowance'])->middleware('permission:allowance,full');
    //late fees
    Route::post('create-latefee', [EmployeeController::class, 'LateFees'])->middleware('permission:late_fees,write');
    Route::get('get-latefee', [EmployeeController::class, 'getLateFees'])->middleware('permission:late_fees,read');
    Route::post('delete-latefee', [EmployeeController::class, 'deleteLateFee'])->middleware('permission:late_fees,full');
    //overtime
    Route::post('create-overtime', [EmployeeController::class, 'overtime'])->middleware('permission:overtime,write');
    Route::get('get-overtime', [EmployeeController::class, 'getOvertime'])->middleware('permission:overtime,read');
    Route::post('delete-overtime', [EmployeeController::class, 'deleteOvertime'])->middleware('permission:overtime,full');
    //advance
    Route::post('create-advance', [AdvanceController::class, 'store'])->middleware('permission:advance,write');
    Route::get('get-advance', [AdvanceController::class, 'index'])->middleware('permission:advance,read');
    Route::post('delete-advance', [AdvanceController::class, 'destroy'])->middleware('permission:advance,full');

    //payroll / salary
    Route::get('get-generatedsalary', [PayrollController::class, 'generatedSalary'])->middleware('permission:generated_salary_table,read');
    Route::post('delete-generatedsalary', [PayrollController::class, 'deleteGeneratedSalary'])->middleware('permission:generated_salary_table,full');
    Route::get('get-payslipHistory', [PayrollController::class, 'payslipHistory'])->middleware('permission:payslip_table,read');
    Route::post('delete-payslip', [PayrollController::class, 'deletePayslip'])->middleware('permission:payslip_table,full');
    Route::get('get-employeepdfImages', [EmployeeController::class, 'getpdfImages'])->middleware('permission:employee_profile,read');



    // Route::post('create-payment', [HelloController::class, 'store']);
    // Payment execution endpoints are guarded with the same permission scheme
    // as the rest of the ISP Billing module (frontend gates on the same names).
    Route::post('create-payment', [InvoiceController::class, 'store'])->middleware('permission:daily_bill_collection,write');
    Route::post('create-payslip', [PayrollController::class, 'store'])->middleware('permission:payroll,write');
    Route::get('get-payslip', [PayrollController::class, 'index'])->middleware('permission:payroll,read');
    Route::get('get-detailspayslip', [PayrollController::class, 'detailspayslip'])->middleware('permission:payroll,read');
    Route::get('payroll-summary', [PayrollController::class, 'summary'])->middleware('permission:payroll,read');
    Route::post('generate-payroll-manual', [PayrollController::class, 'generateManual'])->middleware('permission:payroll,write');
    Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->middleware('permission:payroll,full');
    Route::post('payroll/{payroll}/reject', [PayrollController::class, 'reject'])->middleware('permission:payroll,full');
    Route::post('payrolls/bulk-status', [PayrollController::class, 'bulkStatus'])->middleware('permission:payroll,write');
    Route::patch('payrolls/{payroll}/status', [PayrollController::class, 'updateStatus'])->middleware('permission:payroll,write');

    Route::get('/payments/pending', [InvoiceController::class, 'pendingPayments'])->middleware('permission:payments,read');
    Route::post('/payment/approve/{id}', [InvoiceController::class, 'approvePayment'])->middleware('permission:payments,write');
    Route::post('/payment/reject/{id}', [InvoiceController::class, 'rejectPayment'])->middleware('permission:payments,write');
    Route::post('/payments/bulk-approve', [InvoiceController::class, 'bulkApprove'])->middleware('permission:payments,write');

    Route::get('get-payment', [InvoiceController::class, 'index']);

    Route::get('get-detailsinvoice', [InvoiceController::class, 'detailsinvoice']);
    Route::get('get-detailsGenerateBill', [InvoiceController::class, 'GenerateBillData']);
    Route::get('get-paymentData', [InvoiceController::class, 'paymentData']);
    Route::post('delete-paymentData', [InvoiceController::class, 'deletePaymentData'])->middleware('permission:recieved_bill_history_cancel,write');

    //CompanyProfile
    Route::get('/company-profile', [CompanyProfileController::class, 'index']);
    Route::post('/company-profile', [CompanyProfileController::class, 'store']);
    Route::get('/email-setup', [EmailSetupController::class, 'index']);
    Route::post('/email-setup', [EmailSetupController::class, 'store']);
    Route::post('/InvoiceSetup', [InvoiceSetupController::class, 'store']);
    Route::get('/InvoiceSetup', [InvoiceSetupController::class, 'index']);

    //System SEtup
    Route::get('/SystemSetup', [SystemSetupController::class, 'getSystemSetup']);
    Route::post('/SystemCommonSetup', [SystemSetupController::class, 'saveCommonSetup']);
    Route::post('/SystemPayrollSetup', [SystemSetupController::class, 'savePayrollSetup']);
    Route::post('/SystemClientDisabled', [SystemSetupController::class, 'saveBillingSetup']);





    //role and permission

    Route::get('/get-roles', [RoleAndPermissionController::class, 'getAllRoles']);
    Route::get('/get-roleswithoutSuperAdmin', [RoleAndPermissionController::class, 'getroleswithoutSuperAdmin']);
    Route::get('/get-roleswithoutpermission', [RoleAndPermissionController::class, 'getRolesWithoutPermission']);
    Route::post('/create-role', [RoleAndPermissionController::class, 'createRole']);
    Route::post('/update-role', [RoleAndPermissionController::class, 'updateRole']);
    Route::post('/delete-role', [RoleAndPermissionController::class, 'deleteRole']);
    Route::get('/get-rolewithpermissions', [RoleAndPermissionController::class, 'rolewithpermissions']);
    Route::get('/getbackOrginalPermissions', [RoleAndPermissionController::class, 'getbackOrginalPermissions']);
    Route::post('/createOrupdate-permission', [RoleAndPermissionController::class, 'createOrupdatePermission']);
    Route::post('/update-permission', [RoleAndPermissionController::class, 'updatePermission']);
    Route::post('/remove-permissions', [RoleAndPermissionController::class, 'removePermission']);
    Route::post('/delete-permission', [RoleAndPermissionController::class, 'deletePermission']);
    Route::post('/create-appusers', [HelloController::class, 'createAppusers']);
    Route::post('/check-email', [RoleAndPermissionController::class, 'checkEmail']);
    Route::post('/update-appusers', [RoleAndPermissionController::class, 'updateAppusers']);
    Route::post('/delete-appuser', [RoleAndPermissionController::class, 'deleteAppusers']);
    Route::get('/get-appusers', [RoleAndPermissionController::class, 'getAllAppUsers']);
    Route::get('/get-users', [RoleAndPermissionController::class, 'getAppUsers']);
    Route::post('/get-appuserById', [RoleAndPermissionController::class, 'getAppUsersById']);
    Route::get('/login-history', [RoleAndPermissionController::class, 'loginHistory']);
    Route::post('/updateAppuserPassword', [RoleAndPermissionController::class, 'updateAppuserPassword']);
    Route::post('/updateAppuserEmployee', [HelloController::class, 'updateAppuserEmployee']);
    Route::post('/updateAppuserRole', [RoleAndPermissionController::class, 'updateAppuserRole']);
    Route::post('/updateAppuserInformation', [RoleAndPermissionController::class, 'updateAppuserInformation']);
    Route::get('/get-roleandpermission', [RoleAndPermissionController::class, 'RoleAndPermission']);
    Route::post('/send-sms', [SmsController::class, 'send']);

    //Dashborad data
    Route::get('/active-clients/{year}', [DashboardController::class, 'getActiveClientsByYear']);
    Route::get('/new-clients/{year}', [DashboardController::class, 'getMonthlyNewClients']);
    Route::get('getDashboardData', [DashboardController::class, 'dashboardData']);




    ///reselerrConfiguration


    Route::get('/get-resellerzones', [ResellerZoneController::class, 'index'])->middleware('permission:resellerzone,read');
    Route::post('/create-resellerzones', [ResellerZoneController::class, 'store'])->middleware('permission:resellerzone,write');
    Route::post('/update-resellerzones', [ResellerZoneController::class, 'update'])->middleware('permission:resellerzone,write');
    Route::post('/delete-resellerzones', [ResellerZoneController::class, 'destroy'])->middleware('permission:resellerzone,full');
    Route::post('/resellezones/delete-multiple', [ResellerZoneController::class, 'deleteMultiple'])->middleware('permission:resellerzone,full');
    //subzone
    Route::get('/get-resellersubzones', [ResellerSubzoneController::class, 'index'])->middleware('permission:resellersubzone,read');
    Route::post('/create-resellersubzones', [ResellerSubzoneController::class, 'store'])->middleware('permission:resellersubzone,write');
    Route::post('/update-resellersubzones', [ResellerSubzoneController::class, 'update'])->middleware('permission:resellersubzone,write');
    Route::post('/delete-resellersubzones', [ResellerSubzoneController::class, 'destroy'])->middleware('permission:resellersubzone,full');
    Route::post('/resellesubzone/delete-multiple', [ResellerSubzoneController::class, 'deleteMultiple'])->middleware('permission:resellersubzone,full');

    //box
    Route::get('/get-resellerbox', [ResellerBoxController::class, 'index'])->middleware('permission:resellerbox,read');
    Route::get('/create-resellerbox', [ResellerBoxController::class, 'store'])->middleware('permission:resellerbox,write');
    Route::get('/update-resellerbox', [ResellerBoxController::class, 'update'])->middleware('permission:resellerbox,write');
    Route::get('/delete-resellerbox', [ResellerBoxController::class, 'destroy'])->middleware('permission:resellerbox,full');
    Route::post('/resellebox/delete-multiple', [ResellerBoxController::class, 'deleteMultiple'])->middleware('permission:resellerbox,full');

    //districts
    Route::get('/get-resellerdistrict', [ResellerDistrictController::class, 'index'])->middleware('permission:resellerdistrict,read');
    Route::post('/create-resellerdistrict', [ResellerDistrictController::class, 'store'])->middleware('permission:resellerdistrict,write');
    Route::post('/update-resellerdistrict', [ResellerDistrictController::class, 'update'])->middleware('permission:resellerdistrict,write');
    Route::post('/delete-resellerdistrict', [ResellerDistrictController::class, 'destroy'])->middleware('permission:resellerdistrict,full');
    Route::post('/reselledistricts/delete-multiple', [ResellerDistrictController::class, 'deleteMultiple'])->middleware('permission:resellerdistrict,full');

    //upzila
    Route::get('/get-resellerupzila', [ResellerUpzilaController::class, 'index'])->middleware('permission:resellerupzila,read');
    Route::post('/create-resellerupzila', [ResellerUpzilaController::class, 'store'])->middleware('permission:resellerupzila,write');
    Route::post('/update-resellerupzila', [ResellerUpzilaController::class, 'update'])->middleware('permission:resellerupzila,write');
    Route::post('/delete-resellerupzila', [ResellerUpzilaController::class, 'idestroy'])->middleware('permission:resellerupzila,full');
    Route::post('/reselleupzila/delete-multiple', [ResellerUpzilaController::class, 'deleteMultiple'])->middleware('permission:resellerupzila,full');

    //department
    Route::get('/get-resellerdepartment', [ResellerDepartmentController::class, 'index'])->middleware('permission:resellerdepartment,read');
    Route::post('/create-resellerdepartment', [ResellerDepartmentController::class, 'store'])->middleware('permission:resellerdepartment,write');
    Route::post('/update-resellerdepartment', [ResellerDepartmentController::class, 'update'])->middleware('permission:resellerdepartment,write');
    Route::post('/delete-resellerdepartment', [ResellerDepartmentController::class, 'destroy'])->middleware('permission:resellerdepartment,full');
    Route::post('/reselledepartment/delete-multiple', [ResellerDepartmentController::class, 'deleteMultiple'])->middleware('permission:resellerdepartment,full');

    Route::get('/get-resellerposition', [ResellerPositionController::class, 'index'])->middleware('permission:resellerposition,read');
    Route::post('/create-resellerposition', [ResellerPositionController::class, 'store'])->middleware('permission:resellerposition,write');
    Route::post('/update-resellerposition', [ResellerPositionController::class, 'update'])->middleware('permission:resellerposition,write');
    Route::post('/delete-resellerposition', [ResellerPositionController::class, 'destroy'])->middleware('permission:resellerposition,full');
    Route::post('/reselleposition/delete-multiple', [ResellerPositionController::class, 'deleteMultiple'])->middleware('permission:resellerposition,full');

    //websiteController
    Route::get('/get-contact', [WebCustomerController::class, 'index'])->middleware('permission:contact,read');
    Route::post('/delete-contact', [WebCustomerController::class, 'destroy'])->middleware('permission:contact,full');
    Route::post('/contact/delete-multiple', [WebCustomerController::class, 'deleteMultiple'])->middleware('permission:contact,full');

    Route::get('/get-ConnectionRequest', [WebCustomerController::class, 'getNewLineRequest'])->middleware('permission:new_connection_request,read');
    Route::post('/delete-ConnectionRequest', [WebCustomerController::class, 'delete'])->middleware('permission:new_connection_request,full');
    Route::post('/ConnectionRequest/delete-multiple', [WebCustomerController::class, 'deleteMultipleNewline'])->middleware('permission:new_connection_request,full');



    //inventory Category
    Route::get('/get-category', [CategoryController::class, 'index'])->middleware('permission:category,read');
    Route::post('/create-category', [CategoryController::class, 'store'])->middleware('permission:category,write');
    Route::post('/update-category', [CategoryController::class, 'update'])->middleware('permission:category,write');
    Route::post('/delete-category', [CategoryController::class, 'destroy'])->middleware('permission:category,full');
    Route::post('/category/delete-multiple', [CategoryController::class, 'deleteMultiple'])->middleware('permission:category,full');



    //inventory Product
    Route::get('/get-product', [ProductController::class, 'index'])->middleware('permission:product,read');
    Route::post('/create-product', [ProductController::class, 'store'])->middleware('permission:product,write');
    Route::post('/update-product', [ProductController::class, 'update'])->middleware('permission:product,write');
    Route::post('/delete-product', [ProductController::class, 'destroy'])->middleware('permission:product,full');
    Route::post('/product/delete-multiple', [ProductController::class, 'deleteMultiple'])->middleware('permission:product,full');


    //inventory Supplier
    Route::get('/get-supplier', [SupplierController::class, 'index'])->middleware('permission:supplier,read');
    Route::post('/create-supplier', [SupplierController::class, 'store'])->middleware('permission:supplier,write');
    Route::post('/update-supplier', [SupplierController::class, 'update'])->middleware('permission:supplier,write');
    Route::post('/delete-supplier', [SupplierController::class, 'destroy'])->middleware('permission:supplier,full');
    Route::post('/supplier/delete-multiple', [SupplierController::class, 'deleteMultiple'])->middleware('permission:supplier,full');

    Route::post("/sale-create", [SaleController::class, 'saleCreate'])->middleware('permission:sale,read');
    Route::get("/sale-select", [SaleController::class, 'saleSelect'])->middleware('permission:sale,read');
    Route::post("/sale-details", [SaleController::class, 'saleDetails'])->middleware('permission:sale,read');
    Route::post("/sale-delete", [SaleController::class, 'saleDelete'])->middleware('permission:supplier,read');


    //Purchase web api
    Route::post("/purchase-create", [PurchaseController::class, 'purchaseCreate'])->middleware('permission:purchase,read');
    Route::get("/purchase-select", [PurchaseController::class, 'purchaseSelect'])->middleware('permission:purchase,read');
    Route::post("/purchase-details", [PurchaseController::class, 'purchaseDetails'])->middleware('permission:purchase,read');
    Route::post("/purchase-delete", [PurchaseController::class, 'purchaseDelete'])->middleware('permission:purchase,read');

    //equipment use
    Route::get('/get-equipmentuse', [EquipmentUseController::class, 'index'])->middleware('permission:equipmentuse,read');
    Route::post('/create-equipmentuse', [EquipmentUseController::class, 'store'])->middleware('permission:equipmentuse,write');

    Route::post('/delete-equipmentuse', [EquipmentUseController::class, 'destroy'])->middleware('permission:equipmentuse,full');
    Route::post('/equipmentuse/delete-multiple', [EquipmentUseController::class, 'deleteMultiple'])->middleware('permission:equipmentuse,full');

    //acounting
    Route::get('/get-bandwidthbill', [BandwidthbillController::class, 'index'])->middleware('permission:bandwidthbill,read');
    Route::post('/create-bandwidthbill', [BandwidthbillController::class, 'store'])->middleware('permission:bandwidthbill,write');

    Route::post('/delete-bandwidthbill', [BandwidthbillController::class, 'destroy'])->middleware('permission:bandwidthbill,full');
    Route::post('/bandwidthbill/delete-multiple', [BandwidthbillController::class, 'deleteMultiple'])->middleware('permission:bandwidthbill,full');
    Route::get('getAccountsData', [AccountsController::class, 'accountsData']);
});
