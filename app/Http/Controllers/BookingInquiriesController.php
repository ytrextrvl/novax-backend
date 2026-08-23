<?php

namespace App\Http\Controllers;

use App\Models\BookingInquiry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingInquiriesController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required','string', Rule::in(['flight','hotel','car'])],
            'name' => ['required','string','max:160'],
            'phone' => ['required','string','max:60'],
            'email' => ['nullable','email','max:255'],
            'service_details' => ['required','array'],
            'notes' => ['nullable','string','max:5000'],
        ]);

        $inquiry = BookingInquiry::create([
            ...$data,
            'status' => 'new',
            'source' => 'web',
        ]);

        return response()->json([
            'inquiry' => [
                'id' => $inquiry->id,
                'type' => $inquiry->type,
                'status' => $inquiry->status,
                'created_at' => $inquiry->created_at,
            ],
            'message' => 'Booking inquiry received.',
        ], 201);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable','string','max:30'],
            'type' => ['nullable','string', Rule::in(['flight','hotel','car'])],
        ]);

        $q = BookingInquiry::query()->latest('id');
        if (!empty($data['status'])) $q->where('status', $data['status']);
        if (!empty($data['type'])) $q->where('type', $data['type']);

        return response()->json(['inquiries' => $q->limit(500)->get()]);
    }

    public function show($id)
    {
        return response()->json(['inquiry' => BookingInquiry::findOrFail($id)]);
    }

    public function quote(Request $request, $id)
    {
        $inquiry = BookingInquiry::findOrFail($id);
        $data = $request->validate([
            'amount' => ['required','numeric','min:0.01'],
            'currency' => ['required','string','size:3'],
            'details' => ['nullable','array'],
        ]);

        $inquiry->quote_amount = (float)$data['amount'];
        $inquiry->quote_currency = strtoupper($data['currency']);
        $inquiry->quote_details = $data['details'] ?? [];
        $inquiry->quoted_at = now();
        $inquiry->status = 'quoted';
        $inquiry->save();

        activity()->causedBy(auth('api')->user())->performedOn($inquiry)->log('booking_inquiry.quote');

        return response()->json(['inquiry' => $inquiry]);
    }

    public function updateStatus(Request $request, $id)
    {
        $inquiry = BookingInquiry::findOrFail($id);
        $data = $request->validate([
            'status' => ['required','string', Rule::in(['new','contacted','quoted','converted','closed','cancelled'])],
        ]);
        $inquiry->status = $data['status'];
        $inquiry->save();

        activity()->causedBy(auth('api')->user())->performedOn($inquiry)->log('booking_inquiry.status_change');

        return response()->json(['inquiry' => $inquiry]);
    }
}
