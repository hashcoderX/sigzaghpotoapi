<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobCardExpense;
use App\Models\JobCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobCardExpenseController extends Controller
{
    /**
     * Display a listing of job card expenses.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = JobCardExpense::with(['jobCard'])
            ->where('user_id', $user->id)
            ->orderByDesc('expense_date');

        // Filter by job card if specified
        if ($jobCardId = $request->query('job_card_id')) {
            $query->where('job_card_id', $jobCardId);
        }

        // Filter by event type
        if ($eventType = $request->query('event_type')) {
            $query->where('event_type', $eventType);
        }

        // Date range filter
        if ($startDate = $request->query('start_date')) {
            $query->where('expense_date', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->where('expense_date', '<=', $endDate);
        }

        // Search functionality
        if ($search = $request->query('q')) {
            $query->where(function ($q2) use ($search) {
                $q2->where('event_type', 'like', "%$search%")
                   ->orWhere('description', 'like', "%$search%")
                   ->orWhere('vendor', 'like', "%$search%");
            });
        }

        return $query->paginate((int) $request->query('per_page', 15));
    }

    /**
     * Store a newly created job card expense.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'job_card_id' => ['required', 'exists:job_cards,id'],
            'event_type' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'max:1000'],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5MB max
            'metadata' => ['nullable', 'array'],
        ]);

        // Verify the job card belongs to the user
        $jobCard = JobCard::where('id', $data['job_card_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['user_id'] = $user->id;

        // Handle receipt upload
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $receiptPath;
        }

        $expense = JobCardExpense::create($data);

        return response()->json($expense->load('jobCard'), 201);
    }

    /**
     * Display the specified job card expense.
     */
    public function show(Request $request, JobCardExpense $jobCardExpense)
    {
        $this->authorizeAccess($request, $jobCardExpense);
        return $jobCardExpense->load('jobCard');
    }

    /**
     * Update the specified job card expense.
     */
    public function update(Request $request, JobCardExpense $jobCardExpense)
    {
        $this->authorizeAccess($request, $jobCardExpense);

        $data = $request->validate([
            'event_type' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'expense_date' => ['sometimes', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Handle receipt upload
        if ($request->hasFile('receipt')) {
            // Delete old receipt if exists
            if ($jobCardExpense->receipt_path) {
                Storage::disk('public')->delete($jobCardExpense->receipt_path);
            }
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $receiptPath;
        }

        $jobCardExpense->update($data);

        return $jobCardExpense->load('jobCard');
    }

    /**
     * Remove the specified job card expense.
     */
    public function destroy(Request $request, JobCardExpense $jobCardExpense)
    {
        $this->authorizeAccess($request, $jobCardExpense);

        // Delete receipt file if exists
        if ($jobCardExpense->receipt_path) {
            Storage::disk('public')->delete($jobCardExpense->receipt_path);
        }

        $jobCardExpense->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * Get expense summary for a specific job card.
     */
    public function getJobCardSummary(Request $request, $jobCardId)
    {
        $user = $request->user();

        // Verify job card ownership
        $jobCard = JobCard::where('id', $jobCardId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $expenses = JobCardExpense::where('job_card_id', $jobCardId)
            ->selectRaw('event_type, SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('event_type')
            ->get();

        $totalExpenses = $expenses->sum('total_amount');

        return response()->json([
            'job_card' => $jobCard,
            'expenses_by_type' => $expenses,
            'total_expenses' => $totalExpenses,
        ]);
    }

    /**
     * Get all available event types.
     */
    public function getEventTypes()
    {
        // Predefined event types for job card expenses
        $eventTypes = [
            'wedding_shoot' => 'Wedding Shoot',
            'pre_shoot' => 'Pre Shoot',
            'homecoming_shoot' => 'Homecoming Shoot',
            'birthday_shoot' => 'Birthday Shoot',
            'other_event' => 'Other Event',
            'equipment_rental' => 'Equipment Rental',
            'travel' => 'Travel',
            'accommodation' => 'Accommodation',
            'supplies' => 'Supplies & Materials',
            'subcontractor' => 'Subcontractor Fees',
            'transportation' => 'Transportation',
            'permits_licenses' => 'Permits & Licenses',
            'insurance' => 'Insurance',
            'marketing' => 'Marketing & Promotion',
            'post_processing' => 'Post-Processing',
            'other' => 'Other',
        ];

        return response()->json($eventTypes);
    }

    private function authorizeAccess(Request $request, JobCardExpense $expense): void
    {
        abort_if($expense->user_id !== $request->user()->id, 403);
    }
}
