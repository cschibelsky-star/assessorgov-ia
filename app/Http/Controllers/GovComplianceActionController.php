<?php

namespace App\Http\Controllers;

use App\Models\CustomerOpportunity;
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
            'status' => ['required', 'in:pending,in_review,conformant'],
            'note' => ['nullable', 'string', 'max:3000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $rows = CustomerOpportunity::query()
            ->where('customer_id', $customer->getKey())
            ->get()
            ->filter(function (CustomerOpportunity $row) use ($item): bool {
                $actions = $row->metadata['gov_intelligence_actions'] ?? [];

                return is_array($actions) && isset($actions[$item]);
            });

        if ($rows->isEmpty()) {
            return redirect()->route('gov.compliance')
                ->with('status', 'A pendência informada não pertence a este cliente.');
        }

        $evidencePath = null;

        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store(
                'gov-compliance/'.$customer->getKey().'/'.$item,
                'local',
            );
        }

        DB::transaction(function () use ($rows, $item, $data, $evidencePath, $request): void {
            foreach ($rows as $row) {
                $metadata = $row->metadata ?? [];
                $actions = $metadata['gov_intelligence_actions'] ?? [];
                $action = $actions[$item];

                $action['status'] = $data['status'];
                $action['note'] = $data['note'] ?? null;
                $action['updated_at'] = now()->toIso8601String();
                $action['updated_by_user_id'] = $request->user()->getKey();

                if ($data['status'] === 'in_review') {
                    $action['submitted_at'] = now()->toIso8601String();
                }

                if ($data['status'] === 'conformant') {
                    $action['resolved_at'] = now()->toIso8601String();
                }

                if ($evidencePath !== null) {
                    $action['evidence'] = [
                        'disk' => 'local',
                        'path' => $evidencePath,
                        'original_name' => $request->file('evidence')->getClientOriginalName(),
                        'mime_type' => $request->file('evidence')->getClientMimeType(),
                        'uploaded_at' => now()->toIso8601String(),
                        'uploaded_by_user_id' => $request->user()->getKey(),
                    ];
                }

                $actions[$item] = $action;
                $metadata['gov_intelligence_actions'] = $actions;

                $row->forceFill(['metadata' => $metadata])->save();
            }
        });

        return redirect()->route('gov.compliance', ['focus' => $item])
            ->with('status', 'Compliance atualizado com sucesso.');
    }

    public function evidence(Request $request, CustomerOpportunity $customerOpportunity, string $item): StreamedResponse
    {
        $customer = $request->user()->customer;

        abort_unless($customer && $customerOpportunity->customer_id === $customer->getKey(), 403);

        $action = $customerOpportunity->metadata['gov_intelligence_actions'][$item] ?? null;
        $evidence = is_array($action) ? ($action['evidence'] ?? null) : null;

        abort_unless(is_array($evidence) && ! empty($evidence['path']), 404);

        $disk = $evidence['disk'] ?? 'local';
        $path = $evidence['path'];

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download(
            $path,
            $evidence['original_name'] ?? basename($path),
        );
    }
}
