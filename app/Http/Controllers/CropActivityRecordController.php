<?php

namespace App\Http\Controllers;

use App\Models\CropActivityRecord;
use Illuminate\Http\Request;

class CropActivityRecordController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'field_block' => ['required', 'string', 'max:255'],
            'variety' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        CropActivityRecord::create($data);

        return redirect()->route('dashboard')
            ->with('status', 'Crop Activity Record saved.');
    }

    public function update(Request $request, CropActivityRecord $cropActivityRecord)
    {
        $data = $request->validate([
            'activity' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'field_block' => ['required', 'string', 'max:255'],
            'variety' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $cropActivityRecord->update($data);

        return redirect()->route('dashboard')
            ->with('status', 'Crop Activity Record updated.');
    }
}
