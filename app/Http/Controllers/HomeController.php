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
                return response()->api(null, true, 'No categories found.', 200);
            }

            $data = [
                'categories' => $categories,
            ];

            return response()->api($data); // ✅ success response

        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500); // ✅ error response
        }
    }

}
