<?php

namespace App\Http\Controllers;

use App\Models\EmailSetup;
use Illuminate\Http\Request;

class EmailSetupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $emailSetup = EmailSetup::first();
        return response()->json($emailSetup);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $data = $request->validate([
            'mailer' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'nullable|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'mail_from_email' => 'nullable|email|max:255',
            'encryption' => 'nullable|max:255',

        ]);
        $id = $request->input('id');
        $EmailSetup = EmailSetup::find($id);

        if ($EmailSetup) {
            $EmailSetup->update($data);
        } else {
            $EmailSetup = EmailSetup::create($data);
        }

        return response()->json(['message' => 'EmailSetup Updated successfully', 'data' => $EmailSetup]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
