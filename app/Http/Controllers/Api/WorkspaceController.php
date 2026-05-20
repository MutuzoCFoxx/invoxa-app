<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function show(Request $request)
    {
        $workspace = $request->user()->workspace;
        return response()->json([
            'success' => true,
            'data' => $workspace,
        ]);
    }

    public function update(Request $request)
    {
        $workspace = $request->user()->workspace;

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'currency'           => 'sometimes|string|in:USD,EUR,GBP,RWF',
'company_email'      => 'nullable|email|max:255',
            'company_phone'      => 'nullable|string|max:50',
            'company_address'    => 'nullable|string|max:1000',
            'tax_id'             => 'nullable|string|max:100',
            'website'            => 'nullable|string|max:255',
            'invoice_footer'     => 'nullable|string|max:1000',
            'brand_color'        => 'nullable|string|max:20',
            'bank_name'          => 'nullable|string|max:255',
            'bank_account_number'=> 'nullable|string|max:255',
            'bank_account_name'  => 'nullable|string|max:255',
            'tax_type'           => 'nullable|string|in:on_total,per_item',
            'tax_rate'           => 'nullable|numeric|min:0|max:100',
            'tax_label'          => 'nullable|string|max:50',
            'tax_inclusive'      => 'nullable|boolean',
            'invoice_template'   => 'nullable|string|in:classic,sharp,compact,bold,wave,redmond,clean',
        ]);

        $workspace->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company details updated successfully',
            'data'    => $workspace->fresh(),
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,jpg,png,svg,webp|max:2048',
        ], [
            'logo.image'    => 'File must be an image (JPEG, PNG, SVG, WebP)',
            'logo.mimes'    => 'Accepted formats: JPEG, PNG, SVG, WebP',
            'logo.max'      => 'Logo must be smaller than 2 MB',
        ]);

        $workspace = $request->user()->workspace;

        $file    = $request->file('logo');
        $mime    = $file->getMimeType() ?: 'image/png';
        $data    = base64_encode(file_get_contents($file->getRealPath()));
        $dataUri = "data:{$mime};base64,{$data}";

        $workspace->update(['logo_url' => $dataUri]);

        return response()->json([
            'success'  => true,
            'message'  => 'Logo uploaded successfully',
            'logo_url' => $dataUri,
        ]);
    }

    public function removeLogo(Request $request)
    {
        $workspace = $request->user()->workspace;
        $workspace->update(['logo_url' => null]);

        return response()->json(['success' => true, 'message' => 'Logo removed']);
    }
}
