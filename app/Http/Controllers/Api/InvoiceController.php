<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DocumentCalculator;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $request->user()->workspace;

        $query = Invoice::where('workspace_id', $workspace->id)
            ->with(['customer', 'items']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function store(Request $request)
    {
        $workspace = $request->user()->workspace;
        
        // Check plan limit
        if (!$workspace->canCreateInvoice()) {
            return response()->json([
                'success'    => false,
                'message'    => 'You have used all your free invoices. Upgrade to Pro for unlimited invoices, or refer friends to earn more.',
                'error_code' => 'PLAN_LIMIT_REACHED',
                'data' => [
                    'current_plan'   => $workspace->plan,
                    'invoices_used'  => $workspace->invoices_this_month,
                    'invoices_limit' => $workspace->freeCap(),
                    'referral_code'  => $workspace->referral_code,
                ]
            ], 403);
        }

        $validated = $request->validate([
            'customer_id'     => 'required|exists:customers,id',
            'issue_date'      => 'required|date',
            'due_date'        => 'required|date|after_or_equal:issue_date',
            'currency'        => 'required|string|size:3',
            'notes'           => 'nullable|string',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'discount_type'   => 'nullable|in:none,percentage,fixed',
            'discount_amount' => 'nullable|numeric|min:0',
            'items'           => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $workspace = $request->user()->workspace;

        $count = Invoice::where('workspace_id', $workspace->id)->withTrashed()->count() + 1;
        $invoiceNumber = 'INV-' . str_pad($count, 5, '0', STR_PAD_LEFT);

        $discountType   = $validated['discount_type'] ?? 'none';
        $discountAmount = (float)($validated['discount_amount'] ?? 0);
        $taxRate        = (float)($validated['tax_rate'] ?? 0);
        $taxType        = $workspace->tax_type ?? 'on_total';
        $taxInclusive   = (bool)($workspace->tax_inclusive ?? false);

        $totals = DocumentCalculator::compute(
            $validated['items'], $discountType, $discountAmount, $taxType, $taxRate, $taxInclusive
        );

        $invoice = Invoice::create([
            'workspace_id'    => $workspace->id,
            'customer_id'     => $validated['customer_id'],
            'created_by_id'   => $request->user()->id,
            'invoice_number'  => $invoiceNumber,
            'status'          => 'draft',
            'issue_date'      => $validated['issue_date'],
            'due_date'        => $validated['due_date'],
            'currency'        => $validated['currency'],
            'notes'           => $validated['notes'] ?? null,
            'discount_type'   => $discountType,
            'discount_amount' => $discountAmount,
            'discount_value'  => $totals['discount_value'],
            'subtotal'        => $totals['subtotal'],
            'tax_amount'      => $totals['tax_amount'],
            'total_amount'    => $totals['total_amount'],
        ]);
        $workspace->incrementInvoiceCount();

        foreach ($validated['items'] as $itemData) {
            $lineSubtotal = (float)$itemData['quantity'] * (float)$itemData['unit_price'];
            $lineTaxRate  = (float)($itemData['tax_rate'] ?? 0);
            $lineTax      = $taxType === 'per_item' ? $lineSubtotal * ($lineTaxRate / 100) : 0;

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $itemData['description'],
                'quantity'    => $itemData['quantity'],
                'unit_price'  => $itemData['unit_price'],
                'tax_rate'    => $lineTaxRate,
                'line_total'  => $lineSubtotal + $lineTax,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice created',
            'data' => $invoice->load(['customer', 'items']),
        ], 201);
    }

    public function show(Invoice $invoice)
    {
        return response()->json([
            'success' => true,
            'data' => $invoice->load(['customer', 'items']),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft invoices can be edited',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'due_date' => 'sometimes|date',
        ]);

        $invoice->update($validated);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted',
        ]);
    }
    public function sendEmail(Request $request, Invoice $invoice)
    {
        // Verify ownership
        if ($invoice->workspace_id !== $request->user()->workspace->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string|max:1000',
        ]);
        
        try {
            $invoice->load(['items', 'customer', 'workspace']);
            
            $mailer = new \App\Mail\InvoiceMail($invoice, $validated['message'] ?? '');
            $html = $mailer->build();
            
            $apiKey = env('RESEND_API_KEY');
            if (!$apiKey) {
                return response()->json(['success' => false, 'message' => 'Email service not configured'], 500);
            }
            
            $resend = \Resend::client($apiKey);
            
            $resend->emails->send([
                'from' => 'Invoxa <onboarding@resend.dev>',
                'to' => [$validated['email']],
                'subject' => 'Invoice ' . $invoice->invoice_number . ' from ' . $invoice->workspace->name,
                'html' => $html,
            ]);
            
            // Update status to sent
            $invoice->update(['status' => 'sent']);
            
            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully',
                'data' => $invoice->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }
    public function publicView(string $token)
    {
        // Token format: invoice_id-hash (e.g., "5-abc123")
        $parts = explode('-', $token, 2);
        if (count($parts) !== 2) {
            return response()->json(['success' => false, 'message' => 'Invalid link'], 404);
        }
        
        $invoice = Invoice::with(['items', 'customer', 'workspace'])->find($parts[0]);
        
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }
        
        // Verify hash to prevent ID guessing
        $expectedHash = substr(md5($invoice->id . $invoice->invoice_number . $invoice->created_at), 0, 12);
        if ($parts[1] !== $expectedHash) {
            return response()->json(['success' => false, 'message' => 'Invalid link'], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $invoice
        ]);
    }
    
    public function getShareToken(Invoice $invoice, Request $request)
    {
        if ($invoice->workspace_id !== $request->user()->workspace->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $hash = substr(md5($invoice->id . $invoice->invoice_number . $invoice->created_at), 0, 12);
        $token = $invoice->id . '-' . $hash;
        
        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'invoice_number' => $invoice->invoice_number,
            ]
        ]);}

        public function downloadPdf(Invoice $invoice, Request $request)
    {
        // For public access via token (no auth)
        if ($request->has('token')) {
            $parts = explode('-', $request->token, 2);
            if (count($parts) !== 2 || (int)$parts[0] !== $invoice->id) {
                abort(404);
            }
            $expectedHash = substr(md5($invoice->id . $invoice->invoice_number . $invoice->created_at), 0, 12);
            if ($parts[1] !== $expectedHash) {
                abort(404);
            }
        } else {
            // Auth check for logged-in users
            if (!$request->user() || $invoice->workspace_id !== $request->user()->workspace->id) {
                abort(403);
            }
        }

        $invoice->load(['items', 'customer', 'workspace']);
        
        $pdf = \PDF::loadView('invoices.pdf', ['invoice' => $invoice]);
        return $pdf->download($invoice->invoice_number . '.pdf');
    }
    }
