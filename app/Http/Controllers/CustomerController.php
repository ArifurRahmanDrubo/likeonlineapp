<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\ClientType;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerBillingStatus;
use App\Models\District;
use App\Models\Employee;
use App\Models\GeneratedBill;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PackageChanged;
use App\Models\ProtocolType;
use App\Models\StatusChanged;
use App\Models\SystemPermission;
use App\Models\Upazila;
use App\Models\Upzila;
use App\Models\Zone;
use Carbon\Carbon;
use Dotenv\Exception\ValidationException;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PEAR2\Net\RouterOS;
use RouterOS\Client;
use RouterOS\Exception as RouterOSException;
use RouterOS\Query;

class CustomerController extends Controller
{

    public function index()
    {
        try {
            $customers = Customer::with('invoice')->get();
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
     * Master init endpoint: fetches all dropdown/lookup data needed by the
     * Add/Edit Client form in a single request (replaces 11 separate API calls
     * which used to hit the 429 rate limit).
     */
    public function getClientFormInitData()
    {
        try {
            return response()->json([
                'status'          => true,
                'clientTypes'     => ClientType::select('id', 'client_type')->orderBy('client_type')->get(),
                'districts'       => District::select('id', 'districtname')->orderBy('districtname')->get(),
                'upzilas'         => Upzila::select('id', 'upzilaname')->orderBy('upzilaname')->get(),
                'zones'           => Zone::select('id', 'zone_name')->orderBy('zone_name')->get(),
                'servers'         => MikrotikServer::select('id', 'serverName')->orderBy('serverName')->get(),
                'connectionTypes' => ConnectionType::select('id', 'connection_type')->orderBy('connection_type')->get(),
                'packages'        => Package::select('id', 'packagename')->orderBy('packagename')->get(),
                'boxes'           => Box::select('id', 'box_name')->orderBy('box_name')->get(),
                'protocolTypes'   => ProtocolType::select('id', 'protocol_type')->orderBy('protocol_type')->get(),
                'billingStatuses' => CustomerBillingStatus::select('id', 'billingstatus')->orderBy('billingstatus')->get(),
                'employees'       => Employee::select('id', 'name')->orderBy('name')->get(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to load client form data.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getClient($id)
    {
        try {
            $customer = Customer::with('invoice')->find($id);

            if (!$customer) {
                return response()->json([
                    'message' => 'Client not found'
                ], 404);
            }

            return response()->json([
                'customer' => $customer
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function clientData()
    {
        try {
            $user = Auth::user();
            $customers = Customer::where('user_id', $user->id)->with('invoice')->first();
            return response()->json([
                'customers' => $customers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching customers.'
            ], 500);
        }
    }


    public function clientlistdashboard()
    {
        $numberofRunningClient = Customer::where('billingstatus', '!=', 'Left')->count();
        $numberofFreeClient = Customer::where('billingstatus', '=', 'Free')->count();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Count the number of new clients created in the current month
        $newClientsCount = Customer::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();
        return response()->json([
            'RunningClient' => $numberofRunningClient,
            'newClientsCount' => $newClientsCount,
            'numberofFreeClient' => $numberofFreeClient
        ], 200);
    }
    public function billinglistdashboard()
    {
        $paidClient = Customer::whereHas('invoice', function ($query) {
            $query->where('status', '=', 'paid'); // Adjust this field based on your actual invoice status column
        })->count();
        $unpaidClient = Customer::whereHas('invoice', function ($query) {
            $query->where('status', '=', 'unpaid'); // Adjust this field based on your actual invoice status column
        })->count();
        $received_amount = Invoice::whereHas('customer')
            ->sum('received_amount');
        $due_amount = Invoice::whereHas('customer')
            ->sum('amount');
        $advance_amount = Invoice::whereHas('customer')
            ->sum('advance');
        $generated_bill = GeneratedBill::whereHas('customer')
            ->sum('amount');
        return response()->json([
            'paidClient' => $paidClient,
            'unpaidClient' => $unpaidClient,
            'received_amount' => $received_amount,
            'due_amount' => $due_amount,
            'generated_bill' => $generated_bill,
            'advance_amount ' => $advance_amount

        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'occupation' => 'nullable|string|max:255',
                'remarks' => 'nullable|string',
                'nid' => 'nullable|string|max:255',
                'gender' => 'nullable',
                'dateofbirth' => 'nullable',
                'registrationno' => 'nullable|string|max:255',
                'fathername' => 'nullable|string|max:255',
                'mothername' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'facebook' => 'nullable|string|max:255',
                'linkedin' => 'nullable|string|max:255',
                'twitter' => 'nullable|string|max:255',
                'mobile' => 'required|string',
                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255',
                'district' => 'nullable|string|max:255',
                'upzila' => 'nullable|string|max:255',
                'roadnumber' => 'nullable|string|max:255',
                'housenumber' => 'nullable|string|max:255',
                'praddress' => 'nullable|string',
                'paraddress' => 'nullable|string',
                'server' => 'required|string|max:255',
                'subzone' => 'nullable|string|max:255',
                'zone' => 'required|string|max:255',
                'protocoltype' => 'required|string|max:255',
                'box' => 'nullable|string|max:255',
                'connectiontype' => 'required|string|max:255',
                'cable' => 'nullable|string|max:255',
                'fiber' => 'nullable|string|max:255',
                'coreno' => 'nullable|string|max:255',
                'corecolor' => 'nullable|string|max:255',
                'con_charge' => 'nullable|string|max:255',
                'package' => 'required|string|max:255',
                'profile' => 'required|string|max:255',
                'username' => 'required|string|max:255',
                'password' => 'required|string|max:255',
                'clienttype' => 'required|string|max:255',
                'expireddate' => 'required',
                'server_id' => 'required|integer|max:255',
                'radius_id' => 'nullable',

                'referenceby' => 'nullable|string|max:255',
                'connectedby' => 'nullable|string|max:255',
                'joiningdate' => 'required',
                'billingmonth' => 'required|string|max:255',
                'billingstatus' => 'required|string|max:255',
                'monthlybill' => 'required|numeric',
            ]);
            // Upload images to Cloudinary (update replaces the old image, create uploads new)
            $existingCustomer = $request->input('id') ? Customer::find($request->input('id')) : null;
            foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                if ($request->hasFile($field)) {
                    $imageData = $existingCustomer
                        ? cloudinary_update($request->file($field), $existingCustomer->{$field . '_public_id'}, 'customers')
                        : cloudinary_upload($request->file($field), 'customers');
                    $validated[$field] = $imageData['url'];
                    $validated[$field . '_public_id'] = $imageData['public_id'];
                }
            }



            $mobile = $request->input('mobile');
            $address = $request->input('address');
            $username = $request->input('username');
            $password = $request->input('password');
            $profile = $request->input('profile');
            $protocoltype = $request->input('protocoltype');
            $serverId = $request->input('server_id');
            $radius_id = $request->input('radius_id');
            $connectedby = $request->input('connectedby');
            $subzone = $request->input('subzone');
            $housenumber = $request->input('housenumber');
            $name = $request->input('name');
            $comment = $request->input('remarks');
            $joiningDate = $request->input('joiningdate');

            $dateString = $joiningDate;
            $simplifiedDateString = preg_replace('/ \([^\)]+\)$/', '', $dateString);
            $date = Carbon::parse($simplifiedDateString);
            $formattedDate = $date->format('d F Y');
            $validated['joiningdate'] = $formattedDate;

            $fullComment = $comment;
            if ($name) {
                $fullComment .= " Name: " . $name;
            }
            if ($mobile) {
                $fullComment .= " Mobile: " . $mobile;
            }
            if ($address) {
                $fullComment .= " Address: " . $address;
            }
            if ($housenumber) {
                $fullComment .= " House No: " . $housenumber;
            }
            if ($subzone) {
                $fullComment .= "Location: " . $subzone;
            }
            if ($connectedby) {
                $fullComment .= " Connectedby: " . $subzone;
            }
            if ($request->input('id')) {
                $customer = $existingCustomer;

                $settings = SystemPermission::first();

                if ($settings && $settings->save_comment_in_mikrotik === 'enable') {
                    $comment = $fullComment;
                } else {
                    $comment = '';
                }
                $mikrotikServer = MikrotikServer::find($request->input('server_id'));
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // Connect to the router
                $client->connect();
                $query = new Query('/ppp/secret/set');
                $query->equal('.id', $radius_id);
                $query->equal('name', $username);
                $query->equal('password', $password);
                $query->equal('profile', $profile);
                $query->equal('service', $protocoltype);
                $query->equal('comment', $comment);
                $response = $client->query($query)->read();



                $customer->update($validated);
                return response()->json(['message' => 'Client updated successfully']);
            } else {
                // Customer::create($validated);
                if ($radius_id) {
                    Customer::create($validated);
                } else {
                    $mikrotikServer = MikrotikServer::findOrFail($serverId);
                    $mikrotikClienCreate = $this->addMikrotikUser($mikrotikServer, $username, $password, $profile, $fullComment);
                    $validated['radius_id'] = $mikrotikClienCreate;
                    Customer::create($validated);
                }

                return response()->json(['message' => 'Client created successfully']);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updateclientbillingstatus(Request $request)
    {
        try {
            $customer_id = $request->input('id');
            $billingstatus = $request->input('billingstatus');
            $notes = $request->input('notes');
            $executiondate = $request->input('executiondate');
            $date = Carbon::parse($executiondate);
            $formattedDate = $date->format('d F Y');

            $customer = Customer::where('id', $customer_id)->first();
            StatusChanged::create([
                'customer_id' => $customer_id,
                'billingstatus' => $billingstatus,
                'notes' => $notes,
                'executiondate' => $formattedDate,
            ]);
            $customer->update([
                'billingstatus' => $billingstatus,
            ]);
            return response()->json([
                'message' => ' Updated Status Successful.'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while updated the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updatebulkStatus(Request $request)
    {
        try {
            $billingStatus = $request->input('billingstatus');
            Customer::whereIn('id', $request->ids)->update([
                'billingstatus' => $billingStatus
            ]);
            return response()->json([
                'message' => ' Updated Status Successful.'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while updated the data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updatepPackageStatus(Request $request)
    {
        try {
            $customer_id = $request->input('customer_id');
            $server = $request->input('server');
            $notes = $request->input('notes');
            $protocoltype = $request->input('protocoltype');
            $profile = $request->input('profile');
            $package = $request->input('package');
            $monthlybill = $request->input('monthlybill');
            $executiondate = $request->input('executiondate');
            $date = Carbon::parse($executiondate);
            $formattedDate = $date->format('d F Y');

            // Fetch the customer
            $customer = Customer::findOrFail($customer_id);

            // Create a record in PackageChanged if any data is provided
            PackageChanged::create([
                'customer_id' => $customer_id,
                'server' => $server,
                'protocoltype' => $protocoltype,
                'profile' => $profile,
                'package' => $package,
                'monthlybill' => $monthlybill,
                'notes' => $notes,
                'executiondate' => $formattedDate,
            ]);

            // Prepare update data based on provided fields
            $updateData = [];
            if ($profile) {
                $updateData['profile'] = $profile;
                // Update MikroTik server if profile is provided
                $mikrotikServer = MikrotikServer::findOrFail($request->input('server_id'));
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
                $radius_id = $customer->radius_id;
                // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                $updateRequest = new Query('/ppp/secret/set');
                // $updateRequest->setArgument('.id', $radius_id);
                $updateRequest->equal('.id', $radius_id);
                $updateRequest->equal('profile', $profile);
                $client->query($updateRequest)->read();
            }
            if ($server) {
                $updateData['server'] = $server;
            }
            if ($protocoltype) {
                $updateData['protocoltype'] = $protocoltype;
            }
            if ($package) {
                $updateData['package'] = $package;
            }
            if ($monthlybill) {
                $updateData['monthlybill'] = $monthlybill;
            }

            // Update customer details if there's any update data
            if (!empty($updateData)) {
                $customer->update($updateData);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Updated package status successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the data.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function getpdfImages(Request $request)
    {
        try {
            $id = $request->input('id');
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'error' => 'Customer not found'
                ], 404);
            }

            // Convert an image (Cloudinary URL or legacy local path) to a base64 data URL
            $base64Image = function ($image) {
                if (!$image) {
                    return null;
                }

                try {
                    if (filter_var($image, FILTER_VALIDATE_URL)) {
                        // Image stored as a remote (Cloudinary) URL
                        $response = Http::timeout(30)->get($image);
                        if (!$response->successful()) {
                            return null;
                        }
                        $imageData = $response->body();
                        $mimeType = strtok($response->header('Content-Type') ?: 'application/octet-stream', ';');
                    } else {
                        // Legacy image stored as a local path
                        $path = public_path($image);
                        if (!file_exists($path)) {
                            return null;
                        }
                        $imageData = file_get_contents($path);
                        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
                    }
                } catch (\Exception $e) {
                    return null;
                }

                if (empty($imageData)) {
                    return null;
                }

                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            };

            return response()->json([
                'profileImage' => $base64Image($customer->profileimage),
                'nidImage' => $base64Image($customer->nidimage),
                'registrationImage' => $base64Image($customer->registrationimage)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching customer images.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addMikrotikUser($mikrotikServer, $username, $password, $profile, $fullComment,   $service = 'pppoe')
    {
        try {
            $settings = SystemPermission::first();

            if ($settings && $settings->save_comment_in_mikrotik === 'enable') {
                $comment = $fullComment;
            } else {
                $comment = '';
            }
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            // Connect to the router
            $client->connect();
            // Log::info("Successfully connected to MikroTik router.");
            // Create a query to add a PPPoE user
            $query = new Query('/ppp/secret/add');
            $query->equal('name', $username);
            $query->equal('password', $password);
            $query->equal('profile', $profile);
            $query->equal('service', $service);
            $query->equal('comment', $comment);

            $response = $client->query($query)->read();
            // Log::info("Raw Response from MikroTik: " . print_r($response, true));

            // var_dump($response);
            $result = $response['after'];
            $mikrotikUserId = $result['ret'];
            // Log::info("Successfully received MikroTik user ID: " . $mikrotikUserId);
            return $mikrotikUserId;
        } catch (Exception $e) {
            Log::error("Failed to create Mikrotik users: {$e->getMessage()}");
            return response()->json(['status' => 'error', 'message' => 'Failed to add user'], 500);
        }
    }



    public function removeMikrotikUser(Request $request)
    {
        try {
            $serverId = $request->input('server_id');
            $clientId = $request->input('id');
            $radius_id = $request->input('radius_id');
            // $username = $request->input('username');

            $mikrotikServer = MikrotikServer::findOrFail($serverId);
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
            // if (!$radius_id) {
            //     $userid = $this->findUserByName($username, $serverId);
            // } else {
            //     $userid = $radius_id;
            // }

            $request = new Query('/ppp/secret/remove');
            $request->equal('.id', $radius_id);
            $responses = $client->query($request)->read();

            $client = Customer::find($clientId);
            if ($client) {
                // Delete associated images from Cloudinary before deleting the record
                foreach (['profileimage', 'nidimage', 'registrationimage'] as $field) {
                    if ($client->{$field . '_public_id'}) {
                        cloudinary_delete($client->{$field . '_public_id'});
                    }
                }
                $client->delete();
                return response()->json([
                    'message' => 'Client Delete Successfull.'
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function disabledSelectedClient(Request $request)
    {
        try {
            $disabled = $request->input('disabled');
            $customers = Customer::whereIn('id', $request->ids)->get();
            foreach ($customers as $customer) {
                $server = MikrotikServer::find($customer->server_id);

                if ($server) {
                    // Connect to the MikroTik server
                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);
                    // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);

                    // Prepare MikroTik API request to update 'disabled' status
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $customer->radius_id);
                    $updateRequest->equal('disabled', $disabled);

                    // Send the request
                    $client->query($updateRequest)->read();
                }

                // Update 'disabled' status in the database
                $customer->update([
                    'mikrotikStatus' => false,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Disabled status updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }
    public function enabledSelectedClient(Request $request)
    {
        try {
            $disabled = $request->input('disabled');
            $customers = Customer::whereIn('id', $request->ids)->get();
            foreach ($customers as $customer) {
                $server = MikrotikServer::find($customer->server_id);

                if ($server) {
                    // Connect to the MikroTik server
                    $client = new Client([
                        'host' => $server->serverip,
                        'user' => $server->Username,
                        'pass' => $server->password,
                        'port' => $server->port,
                    ]);
                    // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);

                    // Prepare MikroTik API request to update 'disabled' status
                    // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                    $updateRequest = new Query('/ppp/secret/set');
                    $updateRequest->equal('.id', $customer->radius_id);
                    $updateRequest->equal('disabled', $disabled);

                    // Send the request
                    $client->query($updateRequest)->read();
                }

                // Update 'disabled' status in the database
                $customer->update([
                    'mikrotikStatus' => true,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Enabled status updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }
    protected function findUserByName($username, $serverId)
    {
        try {
            $mikrotikServer = MikrotikServer::findOrFail($serverId);
            $client = new Client([
                'host' => $mikrotikServer->serverip,
                'user' => $mikrotikServer->Username,
                'pass' => $mikrotikServer->password,
                'port' => $mikrotikServer->port,
            ]);
            // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);

            // $request = new RouterOS\Request('/ppp/secret/print');
            $request = new Query('/ppp/secret/print');
            $responses = $client->query($request)->read();
            foreach ($responses as $response) {
                foreach ($response as $key => $value) {
                    if ($key === 'name' && $value === $username) {
                        return $response['.id'];
                    }
                }
            }
            return null; // User not found
        } catch (Exception $e) {
            Log::error("Failed to fetch user by name: {$e->getMessage()}");
            throw new \Exception("Failed to fetch user by name");
        }
    }
    public function toggleClient(Request $request)
    {
        try {
            $disabled = $request->input('disabled');
            $server_id = $request->input('server_id');
            $radius_id = $request->input('radius_id');
            $id = $request->input('id');
            $mikrotikStatus = $request->input('mikrotikStatus');
            $server = MikrotikServer::find($server_id);
            if ($server) {
                // Connect to the MikroTik server

                $client = new Client([
                    'host' => $server->serverip,
                    'user' => $server->Username,
                    'pass' => $server->password,
                    'port' => $server->port,
                ]);
                // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);

                // Prepare MikroTik API request to update 'disabled' status
                $updateRequest = new Query('/ppp/secret/set');
                $updateRequest->equal('.id', $radius_id);
                $updateRequest->equal('disabled', $disabled);

                // Send the request
                $client->query($updateRequest)->read();
                $customer = Customer::find($id);
                // Update 'disabled' status in the database
                $customer->update([
                    'mikrotikStatus' => $mikrotikStatus,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => ' status updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }


    public function bindMac(Request $request)
    {
        try {
            $mac_address = $request->input('mac_address');
            $id = $request->input('id');
            $server_id = $request->input('server_id');
            $radius_id = $request->input('radius_id');
            $customer = Customer::find($id);
            $server = MikrotikServer::find($server_id);
            if ($server) {
                $client = new Client([
                    'host' => $server->serverip,
                    'user' => $server->Username,
                    'pass' => $server->password,
                    'port' => $server->port,
                ]);
                // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);
                // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                $updateRequest = new Query('/ppp/secret/set');
                // $updateRequest->setArgument('.id', $radius_id);
                $updateRequest->equal('.id', $radius_id);
                $updateRequest->equal('caller-id', $mac_address);
                $client->query($updateRequest)->read();

                $customer->update([
                    'caller_id' => $mac_address
                ]);
            }
            return response()->json([
                'message' => ' Mac_address Bind  successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }
    public function unbindMac(Request $request)
    {
        try {

            $server_id = $request->input('server_id');
            $radius_id = $request->input('radius_id');
            $id = $request->input('id');
            $customer = Customer::find($id);
            $mac_address = '';
            $server = MikrotikServer::find($server_id);
            if ($server) {
                $client = new Client([
                    'host' => $server->serverip,
                    'user' => $server->Username,
                    'pass' => $server->password,
                    'port' => $server->port,
                ]);
                // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);
                // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                $updateRequest = new Query('/ppp/secret/set');
                // $updateRequest->setArgument('.id', $radius_id);
                $updateRequest->equal('.id', $radius_id);
                $updateRequest->equal('caller-id', $mac_address);
                $client->query($updateRequest)->read();

                $customer->update([
                    'caller_id' => null
                ]);
            }
            return response()->json([
                'message' => ' Mac_address UnBind  successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }

    public function bindSelectedMacAddresses(Request $request)
    {
        try {
            foreach ($request->users as $user) {
                $customer = Customer::find($user['id']);
                if ($customer) {
                    $server = MikrotikServer::find($user['server_id']);
                    if ($server) {
                        $client = new Client([
                            'host' => $server->serverip,
                            'user' => $server->Username,
                            'pass' => $server->password,
                            'port' => $server->port,
                        ]);
                        // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);
                        // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                        $updateRequest = new Query('/ppp/secret/set');
                        // $updateRequest->setArgument('.id', $user['radius_id']);
                        $updateRequest->equal('.id', $user['radius_id']);
                        $updateRequest->equal('caller-id', $user['mac_address']);
                        $client->query($updateRequest)->read();

                        $customer->update([
                            'caller_id' => $user['mac_address']
                        ]);
                    }
                }
            }
            return response()->json([
                'message' => ' Mac_address Bind  successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }
    public function unbindSelectedMacAddresses(Request $request)
    {
        try {
            foreach ($request->users as $user) {
                $customer = Customer::find($user['id']);

                if ($customer) {
                    $server = MikrotikServer::find($user['server_id']);
                    if ($server) {
                        $client = new Client([
                            'host' => $server->serverip,
                            'user' => $server->Username,
                            'pass' => $server->password,
                            'port' => $server->port,
                        ]);
                        // $client = new Client($server->serverip, $server->Username, $server->password, $server->port);
                        // $updateRequest = new RouterOS\Request('/ppp/secret/set');
                        $updateRequest = new Query('/ppp/secret/set');
                        // $updateRequest->setArgument('.id', $user['radius_id']);
                        $updateRequest->equal('.id', $user['radius_id']);
                        $updateRequest->equal('caller-id', '');
                        $client->query($updateRequest)->read();

                        $customer->update([
                            'caller_id' => null
                        ]);
                    }
                }
            }
            return response()->json([
                'message' => ' Mac_address UnBind  successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([

                'message' => $e->getMessage(),

            ], 500);
        }
    }





    protected function clientList(Request $request)
    {
        try {
            $customers = Customer::with('invoice')->get();
            $customersWithMacs = $customers->map(function ($customer) {
                $mikrotikServer = MikrotikServer::findOrFail($customer->server_id);
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
                // $request = new RouterOS\Request('/ppp/active/print');
                $request = new Query('/ppp/active/print');
                // $responses = $client->sendSync($request);
                $responses = $client->query($request)->read();
                $macAddress = 'N/A';

                foreach ($responses as $response) {

                    if ($response['name'] === $customer->username) {
                        $macAddress = $response['caller-id'];
                        $address = $response['address'];
                        break;
                    }
                }
                return $customer->toArray() + ['mac_address' => $macAddress, 'address' => $address];
            });
            return response()->json([
                'customers' => $customersWithMacs
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
            throw new \Exception("Failed to fetch users from Mikrotik Server");
        }
    }

    protected function BTRClientList(Request $request)
    {
        try {
            $customers = Customer::with('invoice')->get();
            $customersWithMacs = $customers->map(function ($customer) {
                $mikrotikServer = MikrotikServer::findOrFail($customer->server_id);
                $client = new Client([
                    'host' => $mikrotikServer->serverip,
                    'user' => $mikrotikServer->Username,
                    'pass' => $mikrotikServer->password,
                    'port' => $mikrotikServer->port,
                ]);
                // $client = new Client($mikrotikServer->serverip, $mikrotikServer->Username, $mikrotikServer->password, $mikrotikServer->port);
                $request = new Query('/ppp/active/print');
                // $responses = $client->sendSync($request);
                $responses = $client->query($request)->read();
                $macAddress = 'N/A';
                $address = '';
                foreach ($responses as $response) {
                    if ($response['name'] === $customer->username) {
                        $macAddress = $response['caller-id'];
                        $address = $response['address'];
                        break;
                    }
                }
                return $customer->toArray() + ['mac_address' => $macAddress];
            });
            return response()->json([
                'customers' => $customersWithMacs
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to fetch users from Mikrotik Server: {$e->getMessage()}");
            throw new \Exception("Failed to fetch users from Mikrotik Server");
        }
    }
}
