<?php

namespace App\Http\Controllers;

use App\Models\CustomerOpportunity;
use App\Models\GovComplianceEvidence;
use App\Models\GovComplianceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GovComplianceActionController extends Controller
{
    public function update(Request $request, string $item): RedirectResponse
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return redirect()->route('gov.compliance')
                ->with('status', 'Complete o cadastro empresarial antes de atualizar o compliance.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,submitted'],
            'note' => ['nullable', 'string', 'max:3000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $complianceItem = GovComplianceItem::query()
            ->where('customer_id', $customer->getKey())
            ->where('item_key', $item)
            ->first();

        if (! $complianceItem) {
            return redirect()->route('gov.compliance')
                ->with('status', 'A pendência informada não pertence a este cliente.');
        }

        $storedEvidence = null;

        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $path = $file->store(
                'gov-compliance/'.$customer->getKey().'/'.$item,
                'local',
            );

            $storedEvidence = [
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'size_bytes' => $file->getSize(),
            ];
        }

        DB::transaction(function () use ($complianceItem, $data, $storedEvidence, $request): void {
            $changes = [
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'updated_by_user_id' => $request->user()->getKey(),
            ];

            if ($data['status'] === 'submitted') {
                $changes['submitted_at'] = now();
            }

            $complianceItem->forceFill($changes)->save();

            if ($storedEvidence !== null) {
                $complianceItem->evidences()->create([
                    ...$storedEvidence,
                    'uploaded_by_user_id' => $request->user()->getKey(),
                    'uploaded_at' => now(),
                ]);
            }
        });

        return redirect()->route('gov.compliance', ['focus' => $item])
            ->with('status', 'Compliance atualizado e, quando submetido, encaminhado para revisão.');
    }

    public function evidence(
        Request $request,
        CustomerOpportunity $customerOpportunity,
        string $item,
    ): StreamedResponse {
        $customer = $request->user()->customer;

        abort_unless($customer && (string) $customerOpportunity->customer_id === (string) $customer->getKey(), 403);

        $complianceItem = GovComplianceItem::query()
            ->where('customer_id', $customer->getKey())
            ->where('item_key', $item)
            ->whereHas(
                'customerOpportunities',
                fn ($query) => $query->whereKey($customerOpportunity->getKey()),
            )
            ->first();

        abort_unless($complianceItem, 404);

        $evidence = GovComplianceEvidence::query()
            ->where('gov_compliance_item_id', $complianceItem->getKey())
            ->latest('id')
            ->first();

        abort_unless($evidence && Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download(
            $evidence->path,
            $evidence->original_name ?: basename($evidence->path),
        );
    }
}
