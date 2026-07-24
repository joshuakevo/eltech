<?php

namespace App\Http\Controllers;

use App\Models\ClientSegment;
use Illuminate\Http\Request;

class ClientSegmentController extends Controller
{
    public function index()
    {
        $segments = ClientSegment::withCount('clients')
            ->orderBy('name')
            ->paginate(20);
        return view('client-segments.index', compact('segments'));
    }

    public function create()
    {
        return view('client-segments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:client_segments,name',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        ClientSegment::create($data);

        return redirect()->route('client-segments.index')->with('success', 'Segment created successfully.');
    }

    public function edit(ClientSegment $client_segment)
    {
        return view('client-segments.edit', ['segment' => $client_segment]);
    }

    public function update(Request $request, ClientSegment $client_segment)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:client_segments,name,' . $client_segment->id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $client_segment->update($data);

        return redirect()->route('client-segments.index')->with('success', 'Segment updated successfully.');
    }
}
