<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['clients', 'loans', 'savingsAccounts', 'fixedDeposits'])
            ->orderBy('name')
            ->paginate(20);
        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:10|unique:branches,code',
            'address'      => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
            'manager_name' => 'nullable|string|max:100',
            'is_active'    => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        Branch::create($data);

        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    }

    public function show(Branch $branch)
    {
        $branch->loadCount(['clients', 'loans', 'savingsAccounts', 'fixedDeposits']);
        $users = $branch->users()->orderBy('name')->get();
        return view('branches.show', compact('branch', 'users'));
    }

    public function edit(Branch $branch)
    {
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:10|unique:branches,code,' . $branch->id,
            'address'      => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
            'manager_name' => 'nullable|string|max:100',
            'is_active'    => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $branch->update($data);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }
}
