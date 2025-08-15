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
        return $company->reviews()
            ->leftJoin('job_seekers', 'company_reviews.job_seeker_id', '=', 'job_seekers.id')
            ->leftJoin('users', 'job_seekers.user_id', '=', 'users.id')
            ->leftJoin('companies', 'company_reviews.company_id', '=', 'companies.id')
            ->select('company_reviews.*', 'users.name as job_seeker_name', 'companies.name as company_name')
            ->latest('company_reviews.posted_at')
            ->paginate(20);
    }

    public function salaries(Company $company)
    {
        return $company->salaries()->paginate(20);
    }
}


