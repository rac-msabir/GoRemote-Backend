<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class HomeController extends Controller
{
    public function getCategories(Request $request)
    {
        try {
            $categories = DB::table('categories')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            if ($categories->isEmpty()) {
                return response()->json([
                    'status_code'  => 200,
                    'error'        => true,
                    'errorMessage' => 'No categories found.',
                    'data'         => null,
                ], 200);
            }

            return response()->json([
                'status_code'  => 200,
                'error'        => false,
                'errorMessage' => null,
                'data'         => [
                    'categories' => $categories,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status_code'  => 500,
                'error'        => true,
                'errorMessage' => $e->getMessage(),
                'data'         => null,
            ], 500);
        }
    }
}
