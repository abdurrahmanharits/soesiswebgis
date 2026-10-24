<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin-managed list of organizations (provenance of users and their uploads). */
class OrganizationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('organization', [
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')],
        ]);

        Organization::create(['name' => trim($data['name'])]);

        return redirect()->route('admin.users.index')->with('status', 'Organization added.');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validateWithBag('organization', [
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization)],
        ]);

        $organization->update(['name' => trim($data['name'])]);

        return redirect()->route('admin.users.index')->with('status', 'Organization renamed.');
    }
}
