<?php

namespace App\Http\Controllers;

use App\Mail\PaymentSuccessMail;
use App\Models\Customer;
use App\Models\CustomRole;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\MUser;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RouterOS\Client;
use RouterOS\Exception as RouterOSException;
use RouterOS\Query;

class HelloController extends Controller
{
public function clientList(Request $request)
{
    // Server-side pagination only — search is NOT handled here.
    // The Vue ClientList page performs reactive client-side search across
    // all columns, so no `search` parameter is ever sent to this endpoint.
    // Live MikroTik session data is fetched on-demand per customer
    // via GET /api/customers/{id}/live-mac (never during page load).
    try {
        $perPage = max(1, $request->integer('per_page', 25));

        // Standard Laravel pagination JSON: data, current_page, last_page, total, per_page.
        // Left clients are managed on the dedicated Left Client page, so they
        // are excluded here. NULL-safe: legacy customers without a status are
        // still shown.
        return Customer::where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'left');
        })->paginate($perPage);
    } catch (\Exception $e) {
        Log::error("Failed to fetch client list: {$e->getMessage()}");

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch client list.',
        ], 500);
    }
}

    // public function clientList(Request $request)
    // {
    //     try {
    //         $customers = Customer::with('invoice')->get();
    //         $mikrotikServer = MikrotikServer::first();
    //         $client = new Client([
    //             'host' => $mikrotikServer->serverip,
    //             'user' => $mikrotikServer->Username,
    //             'pass' => $mikrotikServer->password,
    //             'port' => $mikrotikServer->port,
    //         ]);
    //         // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
    //         // $request = new RouterOS\Request('/ppp/active/print');
    //         $request = new Query('/ppp/active/print');
    //         // $responses = $client->sendSync($request);
    //         $responses = $client->query($request)->read();

    //         return response()->json([
    //             'customers' => $customers,
    //             'serverData' => $responses
    //         ], 200);
    //     } catch (Exception $e) {
    //         Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
    //         throw new \Exception("Failed to fetch users from Mikrotik Server");
    //     }
    // }
    public function DeleteEmployee(Request $request)
    {
        try {

            $employeeId = $request->input('id');
            $employee = Employee::find($employeeId);
            if ($employee) {
                // Delete associated images from Cloudinary before deleting the record
                foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                    if ($employee->{$field . '_public_id'}) {
                        cloudinary_delete($employee->{$field . '_public_id'});
                    }
                }
                $employee->delete();
            }
            return response()->json([
                'message' => 'Employee Deleted Succefully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function UserStatus(Request $request)
    {
        try {
            $serverid = $request->input('serverId');
            $userid = $request->input('userid');

            $mikrotikServer = MikrotikServer::find($serverid);
            $mikrotikStatus = $request->input('disabled');
            $disabled = $mikrotikStatus ? 'true' : 'false';
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            $updateRequest = new Query('/ppp/secret/set');
            $updateRequest->equal('.id', $userid);
            $updateRequest->equal('disabled', $disabled);
            $client->query($updateRequest)->read();

            $user = MUser::where('mikrotik_id', $userid)->where('server_id', $serverid)->first();
            $user->update(['disabled' => $mikrotikStatus]);
            return response()->json(['status' => 'success', 'message' => 'User status updated successfully']);
        } catch (Exception $e) {
            Log::error("Failed to update PPPoE user statusss: {$e->getMessage()}");
            return response()->json(['status' => 'error', 'message' => 'Failed to update user status please try again'], 500);
        }
    }


    //payment
    public function store(Request $request)
    {
        // Start the transaction
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $payableamount = $request->input('payableamount');
            $clientcode = $request->input('clientcode');
            $monthlybill = $request->input('monthlybill');
            $dueamount = $request->input('dueamount');
            $balancedue = $request->input('balancedue');
            $recievefrom = $request->input('recievefrom');
            $discount = $request->input('discount');
            $transactionno = $request->input('transactionno');
            $recieveamount = $request->input('recieveamount');
            $notes = $request->input('notes');
            $recieveby = $request->input('recieveby');
            $recievedate = $request->input('recievedate');
            $paymentmethod = $request->input('paymentmethod');
            $customer_id = $request->input('Cus_id');
            $advance = 0;

            // Determine payment status
            if ($balancedue < 0) {
                $advance = -$balancedue;
                $status = 'paid';
            } elseif ($balancedue == 0) {
                $status = 'paid';
            } else {
                $status = 'unpaid';
            }

            // Calculate total amount
            $total_amount = $discount + $recieveamount;
            // recieved_date is a DATE column — always store standard SQL format (Y-m-d),
            // never a human-readable string like '13 August 2026'.
            $date = Carbon::parse($recievedate);
            $formattedDate = $date->format('Y-m-d');

            // Update invoice
            $invoice = Invoice::where('customer_id', $customer_id)->first();
            $invoice->update([
                'amount' => $payableamount,
                'advance' => $advance,
                'status' => $status,
                'received_amount' => $recieveamount,
                'transaction_no' => $transactionno,
                'notes' => $notes,
            ]);

            // Create payment record
            Payment::create([
                'customer_id' => $customer_id,
                'received_amount' => $recieveamount,
                'client_code' => $clientcode,
                'recieved_date' => $formattedDate,
                'recieved_by' => $recieveby,
                'discount' => $discount,
                'transaction_no' => $transactionno,
                // created_by is an unsignedBigInteger FK to users.id — store the user ID, not the name.
                'created_by' => $user->id,
                'notes' => $notes,
                'payment_info' => $paymentmethod,
                'total_amount' => $total_amount,
            ]);

            // Fetch customer details
            $customer = Customer::find($customer_id);
            if (!$customer || !$customer->email) {
                return response()->json([
                    'error' => 'Customer not found or email is missing.',
                ], 404);
            }

            // Send payment success email
            Mail::to($customer->email) // Sending to the customer's email
                ->send(new PaymentSuccessMail($total_amount, $transactionno, $customer->name));

            // Commit the transaction
            DB::commit();

            return response()->json([
                'message' => 'Payment Successful, email sent!'
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if an error occurs
            DB::rollBack();

            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                ]);
            }

            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            // Include the user's profile (avatar etc.) so the SPA can render
            // the topbar/profile views without a secondary /api/user-profile call.
            $user->load('profile');

            // Return the user + permissions in the same response so the SPA
            // can hydrate its Pinia store without a secondary API call.
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'message' => ' Login Successful',
                'status' => 'success',
                'user' => $user,
                'role' => $user->role ? $user->role->name : null,
                'permissions' => $user->role ? $user->role->permissions()->select('name', 'type', 'module')->get() : [],
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function createAppusers(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string',
                'status' => 'required|string',
                'role' => 'required|string|exists:roles,name', // Role must exist
            ]);
            $id = $request->input('id');
            $employee = Employee::find($id);
            if (!$employee) {
                return response()->json([
                    'message' => 'Employee not found',
                ], 404);
            }
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'mobile' => $request->input('mobile'),
                'status' => $request->input('status'),
                'password' => Hash::make($request->input('password')),
            ]);
            $employee->update([
                'user_id' => $user->id,
            ]);
            $role = CustomRole::where('name', $request->input('role'))->first();
            if (!$role) {
                return response()->json(['message' => 'Role not found'], 404);
            }
            $user->role()->associate($role);
            $user->save();


            DB::commit();
            return response()->json([
                'message' => ' Users Created Successfull'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateAppuserEmployee(Request $request)
    {
        // Retrieve all users with their roles and permissions
        DB::beginTransaction();
        try {
            $id = $request->input('user_id');
            $user = User::find($id);
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }
            $employee = Employee::where('user_id', $id)->first();
            if ($employee) {
                // Optionally, you can handle any business logic here before removing the association
                $employee->user_id = null; // Set the user_id to null to disassociate
                $employee->save(); // Save the changes to the employee
            }
            $user->update([
                'name' => $request->input('name'),
            ]);
            $newEmployeeId = $request->input('employee_id'); // Assuming this is passed in the request
            if ($newEmployeeId) {
                $newEmployee = Employee::find($newEmployeeId);
                if ($newEmployee) {
                    $newEmployee->user_id = $user->id; // Associate the user with the new employee
                    $newEmployee->save(); // Save the changes to the new employee
                } else {
                    return response()->json([
                        'message' => 'New employee not found',
                    ], 404);
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Employee Assigned Successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
//   protected function clientList(Request $request)
//     {
//         try {
//             $customers = Customer::with('invoice')->get();
//             $customersWithMacs = $customers->map(function ($customer) {
//                 $mikrotikServer = MikrotikServer::findOrFail($customer->server_id);
//                 $client = new Client([
//                     'host' => $mikrotikServer->serverip,
//                     'user' => $mikrotikServer->Username,
//                     'pass' => $mikrotikServer->password,
//                     'port' => $mikrotikServer->port,
//                 ]);
//                 // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
//                 // $request = new RouterOS\Request('/ppp/active/print');
//                 $request = new Query('/ppp/active/print');
//                 // $responses = $client->sendSync($request);
//                 $responses = $client->query($request)->read();
//                 $macAddress = 'N/A';
//                 $address = '';

//                 foreach ($responses as $response) {
//                     // Log::info("Raw Response from MikroTik: " . print_r($response, true));
//                     if ($response['name'] === $customer->username) {
//                         $macAddress = $response['caller-id'];
//                         $address = $response['address'];
//                         break;
//                     }
//                 }
//                 return $customer->toArray() + ['mac_address' => $macAddress, 'address' => $address];
//             });
//             return response()->json([
//                 'customers' => $customersWithMacs
//             ], 200);
//         } catch (Exception $e) {
//             Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
//             throw new \Exception("Failed to fetch users from Mikrotik Server");
//         }
//     }
