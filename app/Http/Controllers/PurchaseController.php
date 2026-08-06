<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\PurchaseProduct;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function purchaseCreate(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'supplier_id' => 'required|max:50',
                'products' => 'required|array|min:1', // Ensure products array is provided
            ]);

            $supplier_id = $request->input('supplier_id');
            $products = $request->input('products');

            $total = $request->input('total');
            // Insert into purchases table, leaving product_id null if not needed
            $purchase = Purchase::create([
                'total' => $total,
                'supplier_id' => $supplier_id,

            ]);

            // Update each product's stock and insert into purchaseProduct table
            foreach ($products as $EachProduct) {
                $product_id = $EachProduct['id'];
                $quantity = $EachProduct['qty'];

                // Update product stock
                $product = Product::find($product_id);
                $product->stock += $quantity;
                $product->save();

                // Insert into purchase_products table
                PurchaseProduct::create([
                    'purchase_id' => $purchase->id,
                    'qty' =>  $EachProduct['qty'],
                    'product_id' => $EachProduct['id'],
                    'price' =>  $EachProduct['price'],

                ]);
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Request Successful"]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }



    function purchaseSelect(Request $request)
    {
        try {

            $rows = Purchase::with('supplier')->get();
            return response()->json($rows);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }



    function purchaseDetails(Request $request)
    {
        try {

            $supplierDetails = Supplier::where('id', $request->input('sup_id'))->first();
            $purchase = Purchase::where('id', $request->input('pur_id'))->first();
            $purchaseProduct = purchaseProduct::where('purchase_id', $request->input('pur_id'))->with('product')->get();
            $rows = array(
                'supplier' => $supplierDetails,
                'purchase' => $purchase,
                'product' => $purchaseProduct,
            );
            return response()->json(['status' => 'success', 'rows' => $rows]);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }



    function purchaseDelete(Request $request)
    {
        DB::beginTransaction();
        try {


            // purchase::where('purchase_id',$request->input('pur_id'))
            //     ->where('user_id',$user_id)
            //     ->delete();

            Purchase::where('id', $request->input('pur_id'))->delete();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Request Successful"]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }
}
