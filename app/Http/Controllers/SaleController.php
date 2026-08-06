<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\SaleProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    function saleCreate(Request $request)
    {
        DB::beginTransaction();

        try {

            $request->validate([
                'total' => 'required|max:50',

            ]);

            $total = $request->input('total');

            $Pqty = $request->input('qty');
            $customer_id = $request->input('customer_id');


            $sale = Sale::create([
                'total' => $total,
                'customer_id' => $customer_id,
            ]);






            $saleID = $sale->id;

            $products = $request->input('products');
            foreach ($products as $EachProduct) {
                SaleProduct::create([
                    'sale_id' => $saleID,
                    'product_id' => $EachProduct['id'],
                    'qty' =>  $EachProduct['qty'],
                    'price' =>  $EachProduct['price'],
                ]);

                $product = Product::find($EachProduct['id']);
                $product->stock -= $EachProduct['qty'];
                $product->save();
            }




            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Request Successful"]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }


    function saleSelect(Request $request)
    {
        try {

            $rows = Sale::with('customer')->get();
            return response()->json($rows);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }


    function SaleDetails(Request $request)
    {
        try {

            $saleProduct = SaleProduct::where('sale_id', $request->input('sale_id'))->with('product')->get();

            return response()->json(['status' => 'success', 'productlist' => $saleProduct]);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }



    function saleDelete(Request $request)
    {
        DB::beginTransaction();
        try {


            SaleProduct::where('sale_id', $request->input('sale_id'))->delete();

            Sale::where('id', $request->input('sale_id'))->delete();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Request Successful"]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }
}
