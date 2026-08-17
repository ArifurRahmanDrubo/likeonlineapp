<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Payment;
use App\Models\Customer;
use Illuminate\Http\Request;

class ReportController extends Controller
{

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
}
