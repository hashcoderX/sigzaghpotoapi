<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use Illuminate\Http\Request;

class AccountingEntryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AccountingEntry::where('user_id', $user->id)
            ->orderByDesc('date');
        if ($search = $request->query('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('type', 'like', "%$search%")
                   ->orWhere('category', 'like', "%$search%")
                   ->orWhere('notes', 'like', "%$search%");
            });
        }
        return $query->paginate((int) $request->query('per_page', 10));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required','string','in:income,expense'],
            'amount' => ['required','numeric','min:0'],
            'category' => ['nullable','string','max:255'],
            'date' => ['required','date'],
            'notes' => ['nullable','string'],
        ]);
        $data['user_id'] = $user->id;
        $entry = AccountingEntry::create($data);
        return response()->json($entry, 201);
    }

    public function show(Request $request, AccountingEntry $accounting_entry)
    {
        $this->authorizeAccess($request, $accounting_entry);
        return $accounting_entry;
    }

    public function update(Request $request, AccountingEntry $accounting_entry)
    {
        $this->authorizeAccess($request, $accounting_entry);
        $data = $request->validate([
            'type' => ['sometimes','string','in:income,expense'],
            'amount' => ['sometimes','numeric','min:0'],
            'category' => ['nullable','string','max:255'],
            'date' => ['sometimes','date'],
            'notes' => ['nullable','string'],
        ]);
        $accounting_entry->update($data);
        return $accounting_entry;
    }

    public function destroy(Request $request, AccountingEntry $accounting_entry)
    {
        $this->authorizeAccess($request, $accounting_entry);
        $accounting_entry->delete();
        return response()->json(['status' => 'deleted']);
    }

    private function authorizeAccess(Request $request, AccountingEntry $entry): void
    {
        abort_if($entry->user_id !== $request->user()->id, 403);
    }
}
