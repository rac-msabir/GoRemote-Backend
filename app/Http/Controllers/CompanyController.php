<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query();
        if ($q = $request->string('q')->toString()) {
            $query->where('name', 'like', "%$q%");
        }
        return $query->paginate(20);
    }

    public function show(Company $company)
    {
        return $company;
    }

    public function reviews(Company $company)
    {
        return $company->reviews()->latest('posted_at')->paginate(20);
    }

    public function salaries(Company $company)
    {
        return $company->salaries()->paginate(20);
    }
}


