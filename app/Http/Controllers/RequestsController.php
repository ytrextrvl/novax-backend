<?php

namespace App\Http\Controllers;

use App\Models\TravelRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestsController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'type' => ['required','string', Rule::in(['flight','hotel','car'])],
            'agency_id' => ['nullable','integer','exists:agencies,id'],
            'flight_id' => ['nullable','integer','exists:flights,id'],
            'passengers' => ['nullable','array'],
            'service_details' => ['required','array'],
            'currency' => ['nullable','string','size:3'],
            'notes' => ['nullable','string','max:5000'],
        ]);

        $meta = [
            'source' => 'manual',
            'provider' => 'manual',
            'provider_reference' => null,
            'pricing_status' => 'pending_quote',
            'service_details' => $data['service_details'],
            'quote' => null,
        ];

        $tr = TravelRequest::create([
            'user_id' => auth('api')->id(),
            'agency_id' => $data['agency_id'] ?? null,
            'type' => $data['type'],
            'flight_id' => $data['type'] === 'flight' ? ($data['flight_id'] ?? null) : null,
            'passengers' => $data['passengers'] ?? [],
            'status' => 'created',
            // Commercial price is always set by the server/admin quote, never trusted from the client.
            'amount' => 0,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'payment_status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
            'meta' => $meta,
        ]);

        activity()->causedBy(auth('api')->user())->performedOn($tr)->log('request.create');

        return response()->json(['request' => $tr], 201);
    }

    public function show($id)
    {
        $tr = TravelRequest::findOrFail($id);

        $user = auth('api')->user();
        if (! $user->hasRole('admin') && $tr->user_id !== $user->id) {
            abort(403, 'Forbidden');
        }

        return response()->json(['request' => $tr]);
    }

    public function quote(Request $request, $id)
    {
        $tr = TravelRequest::findOrFail($id);

        $data = $request->validate([
            'amount' => ['required','numeric','min:0.01'],
            'currency' => ['required','string','size:3'],
            'provider' => ['nullable','string','max:50'],
            'provider_reference' => ['nullable','string','max:255'],
            'expires_at' => ['nullable','date','after:now'],
            'details' => ['nullable','array'],
        ]);

        $meta = $tr->meta ?? [];
        $meta['provider'] = $data['provider'] ?? ($meta['provider'] ?? 'manual');
        $meta['provider_reference'] = $data['provider_reference'] ?? ($meta['provider_reference'] ?? null);
        $meta['pricing_status'] = 'quoted';
        $meta['quote'] = [
            'amount' => (float)$data['amount'],
            'currency' => strtoupper($data['currency']),
            'expires_at' => $data['expires_at'] ?? null,
            'details' => $data['details'] ?? [],
            'quoted_at' => now()->toIso8601String(),
            'quoted_by' => auth('api')->id(),
        ];

        $tr->amount = (float)$data['amount'];
        $tr->currency = strtoupper($data['currency']);
        $tr->status = 'quoted';
        $tr->meta = $meta;
        $tr->save();

        activity()->causedBy(auth('api')->user())->performedOn($tr)->log('request.quote');

        return response()->json(['request' => $tr]);
    }

    public function stateChange(Request $request, $id)
    {
        $tr = TravelRequest::findOrFail($id);

        $data = $request->validate([
            'status' => ['required','string', Rule::in([
                'created','in_review','quoted','awaiting_payment','paid',
                'confirmed','ticketed','completed','rejected','cancelled'
            ])],
        ]);

        $tr->status = $data['status'];
        $tr->save();

        activity()->causedBy(auth('api')->user())->performedOn($tr)->log('request.state_change');

        return response()->json(['request' => $tr]);
    }

    public function paymentVerify(Request $request, $id)
    {
        $tr = TravelRequest::findOrFail($id);

        $data = $request->validate([
            'reference' => ['required','string','max:255'],
            'status' => ['required','string', Rule::in(['paid','failed','pending'])],
        ]);

        $tr->payment_reference = $data['reference'];
        $tr->payment_status = $data['status'];
        if ($data['status'] === 'paid' && in_array($tr->status, ['quoted','awaiting_payment'], true)) {
            $tr->status = 'paid';
        }
        $tr->save();

        activity()->causedBy(auth('api')->user())->performedOn($tr)->log('request.payment_verify');

        return response()->json(['request' => $tr]);
    }
}
