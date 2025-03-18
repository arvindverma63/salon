<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductTransaction;
use App\Models\User_profile;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class ProductTransactionController extends Controller
{
    /**
     * Display a listing of the product transactions.
     *
     * @OA\Get(
     *     path="/api/product-transactions",
     *     summary="Get all product transactions",
     *     tags={"ProductTransactions"},
     *     description="Retrieve a list of all product transactions.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function index()
    {
        // Fetch all product transactions
        $transactions = ProductTransaction::all();

        // Check if no transactions found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No product transactions found'], 404);
        // }

        $result = [];

        // Loop through each transaction
        foreach ($transactions as $transaction) {
            $userId = $transaction->user_id; // Assuming 'user_id' exists in the transaction

            // Fetch the user profile data
            $userProfile = User_profile::select('user_id as _id', 'firstName', 'lastName', 'email', 'phone_number', 'available_balance', 'preferred_location')
                ->where('user_id', $userId)
                ->first(); // Use first() to get a single record

            // If user profile not found, continue to the next transaction
            if (!$userProfile) {
                continue;
            }

            // Fetch the preferred location details
            $preferredLocation = Location::select('id as id', 'name', 'address', 'city', 'phone_number', 'post_code')
                ->where('id', $userProfile->preferred_location) // Use preferred_location_id from user profile
                ->first();

            // Fetch the product details
            $product = Product::select('id as _id', 'name', 'price')
                ->where('id', $transaction->product_id) // Assuming 'product_id' exists in the transaction
                ->first();

            // If product not found, continue to the next transaction
            if (!$product) {
                continue;
            }

            // Add the transaction data to the result array
            $result[] = [
                'user_details' => [
                    'id' => $userProfile->_id,
                    'firstName' => $userProfile->firstName,
                    'lastName' => $userProfile->lastName,
                    'email' => $userProfile->email,
                    'phone_number' => $userProfile->phone_number,
                    'available_balance' => $userProfile->available_balance,
                    'preferred_location' => $preferredLocation ? [
                        'id' => $preferredLocation->_id,
                        'name' => $preferredLocation->name,
                        'address' => $preferredLocation->address,
                        'city' => $preferredLocation->city,
                        'phone_number' => $preferredLocation->phone_number,
                        'post_code' => $preferredLocation->post_code,
                    ] : null, // Handle case where preferred location may not exist
                ],
                'product' => [
                    'id' => $product->_id,
                    'name' => $product->name,
                    'price' => $product->price,
                ],
                'transaction' => [
                    'id' => $transaction->id, // Assuming your transaction model has an id field
                    'quantity' => $transaction->quantity, // Assuming you have a 'quantity' field in the transaction
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ],
            ];
        }

        // If no valid transactions were found, return an error
        // if (empty($result)) {
        //     return response()->json(['error' => 'No valid product transactions found'], 404);
        // }

        // Return the combined data as a JSON response
        return response()->json($result);
    }



    /**
     * Store a newly created product transaction.
     *
     * @OA\Post(
     *     path="/api/product-transactions",
     *     summary="Create a new product transaction",
     *     tags={"ProductTransactions"},
     *     description="Store a new product transaction.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="location", type="string", example="Store A"),
     *             @OA\Property(property="product", type="string", example="Product 1"),
     *             @OA\Property(property="quantity", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product transaction created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
{
    // Validate input
    $request->validate([
        'user_id' => 'required|integer',
        'location_id' => 'nullable|integer',
        'product_id' => 'required|integer',
        'quantity' => 'required|integer|min:1',
    ]);

    // Retrieve the product
    $product = Product::where('id', $request->product_id)->first();

    // Check if the product exists
    if (!$product) {
        return response()->json(['message' => 'Product not found'], 404);
    }

    // Create the transaction
    $transaction = ProductTransaction::create([
        'user_id' => $request->user_id,
        'location_id' => $request->location_id,
        'product_id' => $request->product_id,
        'quantity' => $request->quantity,
    ]);

    // Retrieve the location ID as a string
    $location = Location::where('id', $request->location_id)->first();

    // Check if location exists and update the appropriate stock
    if ($location) {
        if ($location->location_id === "01") {
            $product->stock01 -= $request->quantity;
        } elseif ($location->location_id === "02") {
            $product->stock02 -= $request->quantity;
        } elseif ($location->location_id === "03") {
            $product->stock03 -= $request->quantity;
        }

        // Save the updated product stocks
        $product->save();
    } else {
        return response()->json(['message' => 'Invalid location'], 400);
    }

    return response()->json([
        'message' => 'Product transaction created successfully',
        'transaction' => $transaction
    ], 201);
}


    public function storeAll(Request $request)
    {
        if (!$request->has('data')) {
            return response()->json(['status' => 'error', 'message' => '']);
        }
        $data = $request['data'];

        $productTransactions = array();
        foreach ($data as $product) {

            $transaction = ProductTransaction::create([
                'user_id' => $product['user_id'],
                'location_id' => $product['location_id'],
                'product_id' => $product['product_id'],
                'quantity' => $product['quantity'],
            ]);

            array_push($productTransactions, $transaction);
        }
        return response()->json(['message' => 'Product transaction created successfully', 'transaction' => $productTransactions], 201);
        // return response()->json($request->all());

        // $request->validate([
        //     'user_id' => 'required|integer',
        //     'location_id' => 'integer',
        //     'product_id' => 'required|integer',
        //     'quantity' => 'required|integer',
        // ]);

        // $transaction = ProductTransaction::create([
        //     'user_id' => $request->user_id,
        //     'location_id' => $request->location_id,
        //     'product_id' => $request->product_id,
        //     'quantity' => $request->quantity,
        // ]);

        // return response()->json(['message' => 'Product transaction created successfully', 'transaction' => $transaction], 201);
    }

    /**
     * Display the specified product transaction.
     *
     * @OA\Get(
     *     path="/api/product-transactions/{id}",
     *     summary="Get a specific product transaction",
     *     tags={"ProductTransactions"},
     *     description="Retrieve details of a specific product transaction.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description=" user ID of the product transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product transaction details"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product transaction not found"
     *     )
     * )
     */
    public function show($id)
    {
        // Fetch user profile, including preferred_location
        $userProfile = User_profile::select('user_id as user_id', 'firstName', 'lastName', 'email', 'phone_number', 'preferred_location')
            ->where('user_id', $id)
            ->first(); // Use first() to get a single record

        // Check if the user profile exists
        if (!$userProfile) {
            return response()->json(['message' => 'User profile not found'], 404);
        }

        // Fetch the preferred location using preferred_location ID
        $preferredLocation = Location::select('id as _id', 'name', 'address', 'city', 'phone_number', 'post_code')
            ->where('id', $userProfile->preferred_location) // Use preferred_location from user profile
            ->first();

        // Fetch transactions
        $transactions = ProductTransaction::where('user_id', $id)->get();

        // Check if the transactions exist
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'Product transactions not found'], 404);
        // }

        // Prepare the data
        $transactionData = [];
        foreach ($transactions as $transaction) {
            // Fetch product details
            $productDetail = Product::select('id as id', 'name as productName', 'price', 'description') // Fetching relevant fields
                ->where('id', $transaction->product_id) // Assuming product_id is in the transaction
                ->first();

            // Check if the product exists
            if ($productDetail) {
                $transactionData[] = [
                    'id' => $transaction->id, // Assuming your transaction model has an id field
                    'user' => [
                        'id' => $userProfile->user_id,
                        'firstName' => $userProfile->firstName,
                        'lastName' => $userProfile->lastName,
                        'email' => $userProfile->email,
                        'phone_number' => $userProfile->phone_number,
                        'preferred_location' => $preferredLocation ? [
                            'id' => $preferredLocation->_id,
                            'name' => $preferredLocation->name,
                            'address' => $preferredLocation->address,
                            'city' => $preferredLocation->city,
                            'phone_number' => $preferredLocation->phone_number,
                            'post_code' => $preferredLocation->post_code,
                        ] : null,
                    ],
                    'quantity' => $transaction->quantity, // Assuming this field exists in your transaction model
                    'type' => $transaction->type, // Assuming you have a 'type' field in the transaction
                    'location' => $preferredLocation ? [
                        'id' => $preferredLocation->_id,
                        'name' => $preferredLocation->name,
                        'address' => $preferredLocation->address,
                        'city' => $preferredLocation->city,
                        'phone_number' => $preferredLocation->phone_number,
                        'post_code' => $preferredLocation->post_code,
                    ] : null,
                    'product' => $productDetail, // Including product details
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'), // Format date
                    'updated_at' => $transaction->updated_at->format('Y-m-d H:i:s'), // Format date
                ];
            }
        }

        // Return the combined data as a JSON response
        return response()->json($transactionData);
    }




    /**
     * Update the specified product transaction.
     *
     * @OA\Put(
     *     path="/api/product-transactions/{id}",
     *     summary="Update an existing product transaction",
     *     tags={"ProductTransactions"},
     *     description="Update a product transaction's details.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="location", type="string", example="Store B"),
     *             @OA\Property(property="product", type="string", example="Product 2"),
     *             @OA\Property(property="quantity", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product transaction updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product transaction not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $transaction = ProductTransaction::find($id);

        if (!$transaction) {
            return response()->json(['error' => 'Product transaction not found'], 404);
        }

        $transaction->update($request->only(['user_id', 'location', 'product', 'quantity']));

        return response()->json(['message' => 'Product transaction updated successfully', 'transaction' => $transaction]);
    }

    /**
     * Remove the specified product transaction.
     *
     * @OA\Delete(
     *     path="/api/product-transactions/{id}",
     *     summary="Delete a specific product transaction",
     *     tags={"ProductTransactions"},
     *     description="Permanently delete a product transaction.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product transaction deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product transaction not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $transaction = ProductTransaction::find($id);

        if (!$transaction) {
            return response()->json(['error' => 'Product transaction not found'], 404);
        }

        $transaction->delete();
        return response()->json(['message' => 'Product transaction deleted successfully']);
    }

    // public function productSale($id){
    //     if ($id) {
    //         // Calculate the sum of the quantity field for the specified user ID and type 'used'
    //         $totalUsed = ProductTransaction::where('user_id', $id)
    //             ->where('type', 'product')
    //             ->sum('quantity');

    //         // Calculate the sum of the quantity field for the specified user ID and type 'purchased'
    //         $totalPurchased = ProductTransaction::where('user_id', $id)
    //             ->where('type', 'purchased')
    //             ->sum('quantity');
    //     } else {
    //         // Calculate the sum of the quantity field for all users where type is 'used'
    //         $totalUsed = ServiceTransaction::where('type', 'used')->sum('quantity');

    //         // Calculate the sum of the quantity field for all users where type is 'purchased'
    //         $totalPurchased = ServiceTransaction::where('type', 'purchased')->sum('quantity');
    //     }

    //     // Prepare the response data
    //     $totalQuantity = [
    //         'totalUsed' => $totalUsed,
    //         'totalPurchased' => $totalPurchased
    //     ];

    //     // Return the response in JSON format
    //     return response()->json(['total_quantity' => $totalQuantity]);
    // }

    public function productSale()
    {
        // Fetch the product transactions, grouped by product, location, and transaction date
        $transactions = ProductTransaction::select(
            'product_id',
            'location_id',
            DB::raw('SUM(quantity) as total_sold'),
            DB::raw('DATE(created_at) as transaction_date') // Group by transaction date (only date part)
        )
            ->groupBy('product_id', 'location_id', 'transaction_date') // Group by product, location, and transaction date
            ->get();

        // Check if no transactions found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No product transactions found'], 404);
        // }

        // Get unique product and location IDs from the transactions
        $productIds = $transactions->pluck('product_id')->unique();
        $locationIds = $transactions->pluck('location_id')->unique();

        // Fetch all products and locations in a single query to reduce multiple DB calls
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

        $result = [];

        // Loop through each grouped transaction
        foreach ($transactions as $transaction) {
            // Fetch product details from the already-fetched product collection
            $product = $products->get($transaction->product_id);

            // If product not found, continue to the next transaction
            if (!$product) {
                continue;
            }

            // Fetch location details from the already-fetched location collection
            $location = $locations->get($transaction->location_id);

            // If location not found, continue to the next transaction
            if (!$location) {
                continue;
            }

            // Calculate the total price based on the product price and the total quantity sold
            $totalPrice = $transaction->total_sold * $product->price;

            // Add the grouped transaction data to the result array
            $result[] = [
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'address' => $location->address,
                    'city' => $location->city,
                    'phone_number' => $location->phone_number,
                    'post_code' => $location->post_code,
                ],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name, // Get product name from the 'Product' table
                    'price' => $product->price, // Get product price from the 'Product' table
                ],
                'total_sold' => $transaction->total_sold, // Total sold quantity
                'total_price' => $totalPrice, // Total price of the sold quantity
                'last_transaction_date' => $transaction->transaction_date, // Grouped by transaction date
            ];
        }

        // Return the result as a JSON response
        return response()->json($result);
    }


    public function productSaled(Request $request)
    {
        // Validate request parameters
        $validatedData = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'location_id' => 'nullable|integer|exists:locations,id', // Optional location filter
        ]);

        // Set start and end date strings for full-day coverage
        $startDate = $validatedData['start_date'] . ' 00:00:00';
        $endDate = $validatedData['end_date'] . ' 23:59:59';
        $locationId = $validatedData['location_id'] ?? null;

        // Fetch product transactions, filtered by date range and optionally by location
        $transactionsQuery = ProductTransaction::select(
            'product_id',
            'location_id',
            DB::raw('SUM(quantity) as total_sold'),
            DB::raw('DATE(created_at) as transaction_date') // Group by transaction date (only date part)
        )
            ->whereBetween('created_at', [$startDate, $endDate]); // Filter by date range

        // Apply location filter if provided
        if (!is_null($locationId)) {
            $transactionsQuery->where('location_id', $locationId);
        }

        // Group by product, location, and transaction date
        $transactions = $transactionsQuery->groupBy('product_id', 'location_id', 'transaction_date')->get();

        // Check if no transactions found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No product transactions found'], 404);
        // }

        // Get unique product and location IDs from the transactions
        $productIds = $transactions->pluck('product_id')->unique();
        $locationIds = $transactions->pluck('location_id')->unique();

        // Fetch all products and locations in a single query to reduce multiple DB calls
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

        $result = [];

        // Loop through each grouped transaction
        foreach ($transactions as $transaction) {
            // Fetch product details from the already-fetched product collection
            $product = $products->get($transaction->product_id);

            // If product not found, continue to the next transaction
            if (!$product) {
                continue;
            }

            // Fetch location details from the already-fetched location collection
            $location = $locations->get($transaction->location_id);

            // If location not found, continue to the next transaction
            if (!$location) {
                continue;
            }

            // Calculate the total price based on the product price and the total quantity sold
            $totalPrice = $transaction->total_sold * $product->price;

            // Add the grouped transaction data to the result array
            $result[] = [
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'address' => $location->address,
                    'city' => $location->city,
                    'phone_number' => $location->phone_number,
                    'post_code' => $location->post_code,
                ],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name, // Get product name from the 'Product' table
                    'price' => $product->price, // Get product price from the 'Product' table
                ],
                'total_sold' => $transaction->total_sold, // Total sold quantity
                'total_price' => $totalPrice, // Total price of the sold quantity
                'last_transaction_date' => $transaction->transaction_date, // Grouped by transaction date
            ];
        }

        // Return the result as a JSON response
        return response()->json($result);
    }
}
