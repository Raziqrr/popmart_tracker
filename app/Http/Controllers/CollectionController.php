<?php

namespace App\Http\Controllers;

use App\Repositories\ProductRepository;

class CollectionController extends Controller
{
    public function __construct(private ProductRepository $products) {}

    public function index()
    {
        return response()->json($this->products->withLatestStockByCollection());
    }
}
