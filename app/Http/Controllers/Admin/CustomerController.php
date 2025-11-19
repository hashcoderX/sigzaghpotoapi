<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Customer::where('user_id', $user->id)->orderByDesc('id');
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('company', 'like', "%$search%")
                  ->orWhere('whatsapp', 'like', "%$search%")
                  ->orWhere('nic_or_dl', 'like', "%$search%")
                  ->orWhere('address', 'like', "%$search%");
            });
        }
        return $query->paginate((int) $request->query('per_page', 10));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'phone' => ['nullable','string','max:100'],
            'company' => ['nullable','string','max:255'],
            'whatsapp' => ['nullable','string','max:100'],
            'nic_or_dl' => ['nullable','string','max:150'],
            'address' => ['nullable','string'],
            'notes' => ['nullable','string'],
        ]);
        // Convert empty strings to null for nullable fields
        foreach (['email', 'phone', 'company', 'whatsapp', 'nic_or_dl', 'address', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $data['user_id'] = $user->id;
        $customer = Customer::create($data);
        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorizeAccess($request, $customer);
        return $customer;
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeAccess($request, $customer);
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'phone' => ['nullable','string','max:100'],
            'company' => ['nullable','string','max:255'],
            'whatsapp' => ['nullable','string','max:100'],
            'nic_or_dl' => ['nullable','string','max:150'],
            'address' => ['nullable','string'],
            'notes' => ['nullable','string'],
        ]);
        // Convert empty strings to null for nullable fields
        foreach (['email', 'phone', 'company', 'whatsapp', 'nic_or_dl', 'address', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $customer->update($data);
        return $customer;
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorizeAccess($request, $customer);
        $customer->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function authorizeAccess(Request $request, Customer $customer): void
    {
        abort_if($customer->user_id !== $request->user()->id, 403);
    }
}
