<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalInput;
use Illuminate\Http\Request;

class AgriculturalInputController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_date' => ['required', 'date'],
            'input' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'expiry' => ['nullable', 'date'],
            'used_on' => ['nullable', 'date'],
        ]);

        $data['total'] = $data['qty'] * $data['unit_price'];

        AgriculturalInput::create($data);

        return redirect()->route('dashboard')
            ->with('status', 'Agricultural Input & Purchase record saved.');
    }

    public function update(Request $request, AgriculturalInput $agriculturalInput)
    {
        $data = $request->validate([
            'purchase_date' => ['required', 'date'],
            'input' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'expiry' => ['nullable', 'date'],
            'used_on' => ['nullable', 'date'],
        ]);

        $data['total'] = $data['qty'] * $data['unit_price'];

        $agriculturalInput->update($data);

        return redirect()->route('dashboard')
            ->with('status', 'Agricultural Input & Purchase record updated.');
    }
}
