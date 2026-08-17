<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Package;
use App\Models\Zone;
use App\Models\ClientType;
use App\Models\ProtocolType;
use App\Models\MikrotikServer;
use App\Models\CustomerBillingStatus;
use App\Models\ConnectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    /**
     * Endpoint 1 — Filter dropdown metadata for the Bill Collection Report
     * page. Single optimized request returning only the columns each dropdown
     * renders (client username/code, employee name + linked user id, zone and
     * package names, payment methods).
     */
    public function billCollectionFilters()
    {
        try {
            return response()->json([
                'clients' => Customer::select('id', 'username')
                    ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'left'))
                    ->orderBy('username')
                    ->get(),
                'employees' => Employee::select('id', 'name', 'user_id')->orderBy('name')->get(),
                'zones' => Zone::select('id', 'zone_name')->orderBy('zone_name')->get(),
                'packages' => Package::select('id', 'packagename')->orderBy('packagename')->get(),
                'payment_methods' => [
                    ['label' => 'Cash', 'value' => 'Cash'],
                    ['label' => 'Bkash', 'value' => 'Bkash'],
                    ['label' => 'Nagad', 'value' => 'Nagad'],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint 2 — Consolidated Bill Collection Report.
     *
     * Applies every active filter and returns, in ONE payload:
     *   - records   — filtered, backend-paginated payments (UI-scoped columns
     *                 and constrained eager loads only)
     *   - totals    — SUM(received_amount), SUM(customers.monthlybill) and the
     *                 transaction COUNT computed in SQL over the full filtered
     *                 set BEFORE pagination (no PHP-side collection math)
     *   - pagination metadata (total / per_page / current_page / last_page)
     */
    public function billCollectionReport(Request $request)
    {
        try {
            $perPage = max(1, $request->integer('per_page', 10));
            $page = max(1, $request->integer('page', 1));

            $toDate = $request->filled('toDate') ? Carbon::parse($request->input('toDate'))->format('Y-m-d') : null;
            $fromDate = $request->filled('fromDate') ? Carbon::parse($request->input('fromDate'))->format('Y-m-d') : null;

            // Approved collections only, fetching ONLY the columns rendered by
            // the page: table cells + the Excel/PDF export fields. Columns are
            // table-qualified so the totals aggregation can safely join
            // customers without ambiguous-column errors.
            $query = Payment::select([
                    'payments.id', 'payments.payment_id', 'payments.recieved_date',
                    'payments.customer_id', 'payments.notes', 'payments.received_amount',
                    'payments.discount', 'payments.payment_info', 'payments.recieved_by',
                    'payments.created_by', 'payments.approved_by',
                ])
                ->with([
                    // id is required for the customer's formatted_id accessor.
                    'customer:id,username,name,mobile,zone,package,billingstatus,monthlybill',
                    'customer.invoice:id,customer_id,amount',
                    'creator:id,name',
                    'approver:id,name',
                ])
                ->where('approval_status', 'approved');

            if ($request->filled('userId')) {
                $query->where('customer_id', $request->input('userId'));
            }
            if ($request->filled('toDate')) {
                $query->whereDate('recieved_date', '<=', $toDate);
            }
            if ($request->filled('fromDate')) {
                $query->whereDate('recieved_date', '>=', $fromDate);
            }
            if ($request->filled('recieved_by')) {
                $query->where('recieved_by', $request->input('recieved_by'));
            }
            if ($request->filled('created_by')) {
                // Match both new rows (integer user ID) and legacy rows (user name string).
                $createdBy = $this->createdByFilter($request->input('created_by'));
                $query->where(function ($q) use ($createdBy) {
                    $q->whereIn('created_by', $createdBy['userIds']);
                    if ($createdBy['rawInput'] !== null && $createdBy['rawInput'] !== '') {
                        $q->orWhere('created_by', $createdBy['rawInput']);
                    }
                });
            }
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->input('payment_method'));
            }
            // zone/package live on the customers table — filter through the relation.
            if ($request->filled('zone')) {
                $query->whereHas('customer', fn ($q) => $q->where('zone', $request->input('zone')));
            }
            if ($request->filled('package')) {
                $query->whereHas('customer', fn ($q) => $q->where('package', $request->input('package')));
            }

            // Totals BEFORE pagination — single SQL aggregation over the same
            // filtered set. customer.monthlybill is summed per payment row,
            // preserving the original report's semantics.
            $totals = (clone $query)
                ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
                ->select(DB::raw('COALESCE(SUM(payments.received_amount), 0) as received_amount_total, COALESCE(SUM(customers.monthlybill), 0) as monthly_bill_total, COUNT(*) as transactions_total'))
                ->first();

            $records = $query->orderByDesc('recieved_date')->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'records' => $records->items(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'totals' => [
                    'received_amount' => (float) $totals->received_amount_total,
                    'monthly_bill' => (float) $totals->monthly_bill_total,
                    'count' => (int) $totals->transactions_total,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getBillCollection()
    {
        // Get the current date
        $currentDate = Carbon::now()->toDateString();

        // Fetch all approved payments with customer details
        $dailyPayments = Payment::with(['customer.invoice', 'creator:id,name', 'approver:id,name'])
            ->where('approval_status', 'approved')
            ->get();

        // Return data as JSON response
        return response()->json($dailyPayments);
    }
    public function getBillCollectionQuery(Request $request)
    {
        // Validate query parameters
        try {

            $validated = $request->validate([
                'userId' => 'nullable|string',

                'recieved_by' => 'nullable|string',
                'created_by' => 'nullable|string',
                'payment_method' => 'nullable|string',
            ]);
            $todate = $request->input('toDate');

            // recieved_date is stored as Y-m-d — always compare against Y-m-d strings.
            $toDate = $request->filled('toDate') ? Carbon::parse($request->input('toDate'))->format('Y-m-d') : null;
            $fromDate = $request->filled('fromDate') ? Carbon::parse($request->input('fromDate'))->format('Y-m-d') : null;

            // Build query
            $query = Payment::with(['customer.invoice', 'creator:id,name', 'approver:id,name']);

            if ($request->filled('userId')) {
                $query->where('customer_id', $request->input('userId'));
            }
            if ($request->filled('toDate')) {
                $query->whereDate('recieved_date', '<=', $toDate);
            }
            if ($request->filled('fromDate')) {
                $query->whereDate('recieved_date', '>=', $fromDate);
            }
            if ($request->filled('recieved_by')) {
                $query->where('recieved_by', $request->input('recieved_by'));
            }
            if ($request->filled('created_by')) {
                // Match both new rows (integer user ID) and legacy rows (user name string).
                $createdBy = $this->createdByFilter($request->input('created_by'));
                $query->where(function ($q) use ($createdBy) {
                    $q->whereIn('created_by', $createdBy['userIds']);
                    if ($createdBy['rawInput'] !== null && $createdBy['rawInput'] !== '') {
                        $q->orWhere('created_by', $createdBy['rawInput']);
                    }
                });
            }
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->input('payment_method'));
            }
            // zone/package live on the customers table, not on payments —
            // filter through the customer relation instead of direct columns.
            if ($request->filled('zone')) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('zone', $request->input('zone'));
                });
            }
            if ($request->filled('package')) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('package', $request->input('package'));
                });
            }

            // Execute query and get results
            $paymentDetails = $query->get();

            // Return results as JSON
            return response()->json($paymentDetails);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint 1 — Filter dropdown metadata for the Discount Report page.
     * Single optimized request returning only the columns each dropdown
     * renders (client username/code, employee name + linked user id).
     */
    public function discountReportFilters()
    {
        try {
            return response()->json([
                'clients' => Customer::select('id', 'username')
                    ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'left'))
                    ->orderBy('username')
                    ->get(),
                'employees' => Employee::select('id', 'name', 'user_id')->orderBy('name')->get(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint 2 — Consolidated Discount Report.
     *
     * Applies every active filter and returns, in ONE payload:
     *   - records   — filtered, backend-paginated discount payments
     *                 (UI-scoped columns and constrained eager loads only)
     *   - totals    — SUM(discount), SUM(customers.monthlybill) and the row
     *                 COUNT computed in SQL over the full filtered set BEFORE
     *                 pagination (no PHP-side collection math)
     *   - pagination metadata (total / per_page / current_page / last_page)
     */
    public function discountReport(Request $request)
    {
        try {
            $perPage = max(1, $request->integer('per_page', 10));
            $page = max(1, $request->integer('page', 1));

            $toDate = $request->filled('toDate') ? Carbon::parse($request->input('toDate'))->format('Y-m-d') : null;
            $fromDate = $request->filled('fromDate') ? Carbon::parse($request->input('fromDate'))->format('Y-m-d') : null;

            // Approved discount payments only, fetching ONLY the columns rendered
            // by the page (table cells + Excel/PDF export fields). Columns are
            // table-qualified so the totals aggregation can safely join customers.
            $query = Payment::select([
                    'payments.id', 'payments.customer_id', 'payments.recieved_date',
                    'payments.discount', 'payments.received_amount',
                    'payments.recieved_by', 'payments.created_by',
                ])
                ->with([
                    // id is required for the customer's formatted_id accessor.
                    'customer:id,username,name,mobile,zone,package,monthlybill',
                    'creator:id,name',
                ])
                ->where('approval_status', 'approved')
                ->whereNotNull('discount')
                ->where('discount', '>', 0);

            if ($request->filled('userId')) {
                $query->where('customer_id', $request->input('userId'));
            }
            if ($request->filled('toDate')) {
                $query->whereDate('recieved_date', '<=', $toDate);
            }
            if ($request->filled('fromDate')) {
                $query->whereDate('recieved_date', '>=', $fromDate);
            }
            if ($request->filled('recieved_by')) {
                $query->where('recieved_by', $request->input('recieved_by'));
            }
            if ($request->filled('created_by')) {
                // Match both new rows (integer user ID) and legacy rows (user name string).
                $createdBy = $this->createdByFilter($request->input('created_by'));
                $query->where(function ($q) use ($createdBy) {
                    $q->whereIn('created_by', $createdBy['userIds']);
                    if ($createdBy['rawInput'] !== null && $createdBy['rawInput'] !== '') {
                        $q->orWhere('created_by', $createdBy['rawInput']);
                    }
                });
            }

            // Totals BEFORE pagination — single SQL aggregation over the same
            // filtered set. customer.monthlybill is summed per payment row,
            // preserving the original report's semantics.
            $totals = (clone $query)
                ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
                ->select(DB::raw('COALESCE(SUM(payments.discount), 0) as discount_total, COALESCE(SUM(customers.monthlybill), 0) as monthly_bill_total, COUNT(*) as transactions_total'))
                ->first();

            $records = $query->orderByDesc('recieved_date')->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'records' => $records->items(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'totals' => [
                    'discount' => (float) $totals->discount_total,
                    'monthly_bill' => (float) $totals->monthly_bill_total,
                    'count' => (int) $totals->transactions_total,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDiscountReportQuery(Request $request)
    {
        // Validate query parameters
        try {
            $validated = $request->validate([
                'userId' => 'nullable|string',
                'recieved_by' => 'nullable|string',
                'created_by' => 'nullable|string',
            ]);

            $todate = $request->input('toDate');
            // recieved_date is stored as Y-m-d — always compare against Y-m-d strings.
            $toDate = $request->filled('toDate') ? Carbon::parse($request->input('toDate'))->format('Y-m-d') : null;
            $fromDate = $request->filled('fromDate') ? Carbon::parse($request->input('fromDate'))->format('Y-m-d') : null;

            // Build query
            $query = Payment::with(['customer.invoice', 'creator:id,name', 'approver:id,name']);

            // Approved payments only — pending / rejected money is not collection.
            $query->where('approval_status', 'approved');

            if ($request->filled('userId')) {
                $query->where('customer_id', $request->input('userId'));
            }
            if ($request->filled('toDate')) {
                $query->whereDate('recieved_date', '<=', $toDate);
            }
            if ($request->filled('fromDate')) {
                $query->whereDate('recieved_date', '>=', $fromDate);
            }
            if ($request->filled('recieved_by')) {
                $query->where('recieved_by', $request->input('recieved_by'));
            }
            if ($request->filled('created_by')) {
                // Match both new rows (integer user ID) and legacy rows (user name string).
                $createdBy = $this->createdByFilter($request->input('created_by'));
                $query->where(function ($q) use ($createdBy) {
                    $q->whereIn('created_by', $createdBy['userIds']);
                    if ($createdBy['rawInput'] !== null && $createdBy['rawInput'] !== '') {
                        $q->orWhere('created_by', $createdBy['rawInput']);
                    }
                });
            }

            // Filter payments with a discount value
            $query->whereNotNull('discount')
                ->where('discount', '>', 0);

            // Execute query and get results
            $paymentDetails = $query->get();

            // Return results as JSON
            return response()->json($paymentDetails);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getDiscountReport()
    {
        try {
            // Build query to get all payments with a discount value
            $paymentData = Payment::with(['customer.invoice', 'creator:id,name', 'approver:id,name'])
                ->where('approval_status', 'approved')
                ->whereNotNull('discount')
                ->where('discount', '>', 0)
                ->get();

            // Return results as JSON
            return response()->json($paymentData);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getCustomerReportQuery(Request $request)
    {
        // Validate query parameters
        try {

            $protocol = $request->input('protocol');
            $client_type = $request->input('client_type');
            $clientsof = $request->input('clientsof');
            $server = $request->input('server');
            $bill_status = $request->input('bill_status');
            $customstatus = $request->input('customstatus');
            $macreseller = $request->input('macreseller');

            $todate = $request->input('toDate');
            // $toDate = Carbon::parse($todate)->format('d F Y');
            $fromdate = $request->input('fromDate');
            // $fromDate = Carbon::parse($fromdate)->format('d F Y');

            if ($clientsof === 'Admin') {
                $model = Customer::query();
                if ($todate) {
                    $todate = Carbon::createFromFormat('d-m-Y', $todate)->format('Y-m-d');
                    $model->whereDate('created_at', '<=', $todate);
                }

                if ($fromdate) {
                    $fromdate = Carbon::createFromFormat('d-m-Y', $fromdate)->format('Y-m-d');
                    $model->whereDate('created_at', '>=', $fromdate);
                }

                if ($protocol) {
                    $model->where('protocoltype', $protocol);
                }

                if ($client_type) {
                    $model->where('clienttype', $client_type);
                }

                if ($server) {
                    $model->where('server', $server);
                }

                if ($bill_status) {
                    $model->where('billingstatus', $bill_status);
                }

                if ($customstatus) {
                    if ($customstatus === 'New Client') {
                        // Query for the current month if the custom status is 'New Client'
                        $currentMonth = Carbon::now()->format('Y-m');
                        $model->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$currentMonth]);
                    } elseif ($customstatus === 'Online') {
                        // Query for online clients with mikrotikStatus true
                        $model->where('mikrotikStatus', true);
                    } elseif ($customstatus === 'Offline') {
                        // Query for offline clients with mikrotikStatus false
                        $model->where('mikrotikStatus', false);
                    }
                }

                $results = $model->get();

                // Return the results as JSON
                return response()->json($results);
            } else {
                $model = Customer::with('invoice');
                if ($todate) {
                    $model->whereDate('created_at', '<=', $todate);
                }

                if ($fromdate) {
                    $model->whereDate('created_at', '>=', $fromdate);
                }

                if ($protocol) {
                    $model->where('protocoltype', $protocol);
                }

                if ($client_type) {
                    $model->where('clienttype', $client_type);
                }

                if ($server) {
                    $model->where('server', $server);
                }


                if ($bill_status) {
                    $model->where('billingstatus', $bill_status);
                }
                if ($macreseller) {
                    $model->where('macreseller', $macreseller);
                }

                if ($customstatus) {
                    if ($customstatus === 'New Client') {
                        // Query for the current month if the custom status is 'New Client'
                        $currentMonth = Carbon::now()->format('Y-m');
                        $model->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$currentMonth]);
                    } elseif ($customstatus === 'Online') {
                        // Query for online clients with mikrotikStatus true
                        $model->where('mikrotikStatus', true);
                    } elseif ($customstatus === 'Offline') {
                        // Query for offline clients with mikrotikStatus false
                        $model->where('mikrotikStatus', false);
                    }
                }

                $results = $model->get();

                // Return the results as JSON
                return response()->json($results);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCustomerReport()
    {
        try {
            // Fetch all customers with their invoice relationships
            $customers = Customer::get();


            return response()->json([
                'customers' => $customers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching customers.'
            ], 500);
        }
    }

    /**
     * Endpoint 1 — Filter dropdown metadata for the Customer Report page.
     * Single optimized request returning only the columns each dropdown renders
     * (billing status names, server names, protocol types, client types) plus
     * the two static option lists (Clients OF / Custom Status).
     */
    public function customerReportFilters()
    {
        try {
            return response()->json([
                'billing_statuses' => CustomerBillingStatus::select('id', 'billingstatus')->orderBy('billingstatus')->get(),
                'servers' => MikrotikServer::select('id', 'serverName')->orderBy('serverName')->get(),
                'protocols' => ProtocolType::select('id', 'protocol_type')->orderBy('protocol_type')->get(),
                'client_types' => ClientType::select('id', 'client_type')->orderBy('client_type')->get(),
                'clientsof_options' => [
                    ['label' => 'Admin', 'value' => 'Admin'],
                    ['label' => 'Macreseller', 'value' => 'Macreseller'],
                ],
                'customstatus_options' => [
                    ['label' => 'New Client', 'value' => 'New Client'],
                    ['label' => 'Offline Client', 'value' => 'Offline Client'],
                    ['label' => 'Online Client', 'value' => 'Online Client'],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint 2 — Consolidated Customer Report.
     *
     * Applies every active filter and returns, in ONE payload:
     *   - records   — filtered, backend-paginated customers
     *                 (only the columns the UI renders, incl. exports)
     *   - totals    — COUNT(*) and SUM(monthlybill) computed in SQL over the
     *                 full filtered set BEFORE pagination (no PHP collection math)
     *   - pagination metadata (total / per_page / current_page / last_page)
     */
    public function customerReport(Request $request)
    {
        try {
            $perPage = max(1, $request->integer('per_page', 10));
            $page = max(1, $request->integer('page', 1));

            // Columns rendered by the page (table cells + Excel/PDF exports).
            // id is required for the formatted_id accessor. created_at is used
            // only in WHERE clauses, so it is not part of the select.
            $query = Customer::select([
                'id', 'username', 'password', 'name', 'mobile', 'clienttype',
                'package', 'server', 'protocoltype', 'profile', 'billingstatus',
                'monthlybill', 'mikrotikStatus',
            ]);

            // Date range on created_at — the UI sends dd-mm-yyyy.
            $toDate = $request->filled('toDate') ? $this->parseCustomerReportDate($request->input('toDate')) : null;
            $fromDate = $request->filled('fromDate') ? $this->parseCustomerReportDate($request->input('fromDate')) : null;
            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }
            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }

            if ($request->filled('protocol')) {
                $query->where('protocoltype', $request->input('protocol'));
            }
            if ($request->filled('client_type')) {
                $query->where('clienttype', $request->input('client_type'));
            }
            if ($request->filled('server')) {
                $query->where('server', $request->input('server'));
            }
            if ($request->filled('bill_status')) {
                $query->where('billingstatus', $request->input('bill_status'));
            }

            if ($request->filled('customstatus')) {
                $customStatus = $request->input('customstatus');
                if ($customStatus === 'New Client') {
                    // Customers created in the current month.
                    $query->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [Carbon::now()->format('Y-m')]);
                } elseif (in_array($customStatus, ['Online', 'Online Client'], true)) {
                    $query->where('mikrotikStatus', true);
                } elseif (in_array($customStatus, ['Offline', 'Offline Client'], true)) {
                    $query->where('mikrotikStatus', false);
                }
            }

            // Totals BEFORE pagination — single SQL aggregation over the same
            // filtered set (COUNT of customers + total monthly billing).
            $totals = (clone $query)
                ->select(DB::raw('COUNT(*) as customers_total, COALESCE(SUM(monthlybill), 0) as monthly_bill_total'))
                ->first();

            $records = $query->orderBy('id')->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'records' => $records->items(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'totals' => [
                    'count' => (int) $totals->customers_total,
                    'monthly_bill' => (float) $totals->monthly_bill_total,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse a dd-mm-yyyy (or yyyy-mm-dd) date string into a Y-m-d value.
     */
    private function parseCustomerReportDate($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Endpoint 1 — Filter dropdown metadata for the BTRC Monthly Report page.
     * Single optimized request returning only the columns each dropdown renders
     * (billing status names, server names, zone names, connection types,
     * protocol types, client types, packages for the bandwidth lookup) plus the
     * static option lists (Clients OF / Distribution Point / Date Format /
     * Allocated IP type).
     */
    public function btrcReportFilters()
    {
        try {
            return response()->json([
                'billing_statuses' => CustomerBillingStatus::select('id', 'billingstatus')->orderBy('billingstatus')->get(),
                'servers' => MikrotikServer::select('id', 'serverName')->orderBy('serverName')->get(),
                'zones' => Zone::select('id', 'zone_name')->orderBy('zone_name')->get(),
                'connection_types' => ConnectionType::select('id', 'connection_type')->orderBy('connection_type')->get(),
                'protocols' => ProtocolType::select('id', 'protocol_type')->orderBy('protocol_type')->get(),
                'client_types' => ClientType::select('id', 'client_type')->orderBy('client_type')->get(),
                'packages' => Package::select('id', 'packagename', 'bandwithallowcationmb')->orderBy('packagename')->get(),
                'clientsof_options' => [
                    ['label' => 'Admin', 'value' => 'Admin'],
                    ['label' => 'Macreseller', 'value' => 'Macreseller'],
                ],
                'distribution_point_options' => [
                    ['label' => 'DC', 'value' => 'DC'],
                    ['label' => 'NOC', 'value' => 'NOC'],
                    ['label' => 'POP', 'value' => 'POP'],
                    ['label' => 'SERVER', 'value' => 'SERVER'],
                ],
                'date_format_options' => [
                    ['label' => 'YYYY-MM-DD(e.g..2023-07-22)', 'value' => 'YYYY-MM-DD'],
                    ['label' => 'DD/MM/YYYY(e.g..22/07/2023)', 'value' => 'DD/MM/YYYY'],
                    ['label' => 'MM/DD/YYYY(e.g..07/22/2023)', 'value' => 'MM/DD/YYYY'],
                    ['label' => 'MM-DD-YYYY(e.g..07-22-2023)', 'value' => 'MM-DD-YYYY'],
                    ['label' => 'DD-MM-YYYY(e.g..22-07-2023)', 'value' => 'DD-MM-YYYY'],
                    ['label' => 'D-M-YY(e.g..22-7-2023)', 'value' => 'D-M-YY'],
                    ['label' => 'M-D-YY(e.g..7-22-2023)', 'value' => 'M-D-YY'],
                    ['label' => 'M/D/YY(e.g..7/222023)', 'value' => 'M/D/YY'],
                    ['label' => 'D/M/YY(e.g..22/7/2023)', 'value' => 'D/M/YY'],
                ],
                'allocated_ip_type_options' => [
                    ['label' => 'IP Address', 'value' => 'IP Address'],
                    ['label' => 'MAC Address', 'value' => 'MAC Address'],
                    ['label' => 'User ID', 'value' => 'User ID'],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint 2 — Consolidated BTRC Monthly Report.
     *
     * Applies every active Admin-branch filter (month, protocol, zone,
     * bill_status, connectiontype, server, client_type) and returns, in ONE
     * payload:
     *   - records   — filtered, backend-paginated customers
     *                 (only the columns the UI renders, incl. exports)
     *   - totals    — COUNT(*) and SUM(monthlybill) computed in SQL over the
     *                 full filtered set BEFORE pagination (no PHP collection math)
     *   - pagination metadata (total / per_page / current_page / last_page)
     */
    public function btrcReport(Request $request)
    {
        try {
            $perPage = max(1, $request->integer('per_page', 10));
            $page = max(1, $request->integer('page', 1));

            // Columns rendered by the page (table cells + Excel/PDF exports) and
            // the fields needed to derive the computed display values
            // (joiningdate -> activationdate, package -> bandwidth, caller_id /
            // username -> allocated IP). id keeps the row key working.
            $query = Customer::select([
                'id', 'name', 'username', 'clienttype', 'connectiontype',
                'joiningdate', 'district', 'upzila', 'mobile', 'email',
                'monthlybill', 'package', 'caller_id',
            ]);

            // Month filter — joiningdate is stored as 'd MMMM yyyy' strings.
            if ($request->filled('month')) {
                try {
                    $monthDate = Carbon::createFromFormat('d F Y', $request->input('month'))->format('Y-m-d');
                    $query->whereRaw('STR_TO_DATE(joiningdate, "%e %M %Y") <= ?', [$monthDate]);
                } catch (\Exception $e) {
                    // Ignore an unparseable month value.
                }
            }

            // Remaining filters match the client-side semantics of the original
            // page (dropdowns bind option-value="label", so filters send the
            // label strings directly).
            if ($request->filled('protocol')) {
                $query->where('protocoltype', $request->input('protocol'));
            }
            if ($request->filled('zone')) {
                $query->where('zone', $request->input('zone'));
            }
            if ($request->filled('bill_status')) {
                $query->where('billingstatus', $request->input('bill_status'));
            }
            if ($request->filled('connectiontype')) {
                $query->where('connectiontype', $request->input('connectiontype'));
            }
            if ($request->filled('server')) {
                $query->where('server', $request->input('server'));
            }
            if ($request->filled('client_type')) {
                $query->where('clienttype', $request->input('client_type'));
            }

            // Totals BEFORE pagination — single SQL aggregation over the same
            // filtered set (COUNT of customers + total monthly billing).
            $totals = (clone $query)
                ->select(DB::raw('COUNT(*) as customers_total, COALESCE(SUM(monthlybill), 0) as monthly_bill_total'))
                ->first();

            $records = $query->orderBy('id')->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'records' => $records->items(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'totals' => [
                    'count' => (int) $totals->customers_total,
                    'monthly_bill' => (float) $totals->monthly_bill_total,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
