<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use App\Models\Bkl;
use App\Models\New_product;

class BklController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('q');
        $newProducts = BKL::latest()
            ->where(function ($queryBuilder) use ($searchQuery) {
                $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_name_product', 'LIKE', '%' . $searchQuery . '%');
            })
            ->whereNotIn('new_status_product', 'expired')
            ->paginate(20);

        return new ResponseResource(true, "list new product", $newProducts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $product = New_product::findOrFail($id);
            $duplicate = Bkl::where('new_barcode_product', $product->new_barcode_product)->exists();
            if ($duplicate) {
                return new ResponseResource(false, "barcode product di inventory sudah ada : " . $product->new_barcode_product, null);
            }
            New_product::where('id',$id)->delete();
            $productFilter = Bkl::create($product->toArray());
            DB::commit();
            return new ResponseResource(true, "berhasil menambah list product bundle", $productFilter);
        } catch (\Exception $e) {
            DB::rollBack();
            return (new ResponseResource(false, $e->getMessage(), null))
            ->response()->setStatusCode(500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(BKL $bKL)
    {
        return new ResponseResource(true, "list bkl", $bKL);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BKL $bKL)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BKL $bKL)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BKL $bKL)
    {
        //
    }
}
