<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\ServiceTransaction;
use App\Models\User_profile;
use Illuminate\Support\Facades\DB;
use App\Models\Location;

class ServiceTransactionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/service-transactions",
     *     summary="Get all service transactions",
     *     tags={"Service Transactions"},
     *     description="Retrieve a list of all service transactions.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    //     public function index()
    // {
    //     // Fetch all service transactions
    //     $serviceTransactions = DB::table('service_transactions')->get();

    //     // If no service transactions found, return an error
    //     if ($serviceTransactions->isEmpty()) {
    //         return response()->json(['message' => 'No service transactions found'], 404);
    //     }

    //     // Assuming you want to loop through each transaction to get related user profiles and services
    //     $result = [];

    //     foreach ($serviceTransactions as $transaction) {
    //         $id = $transaction->user_id; // Assuming you want to fetch by user_id

    //         // Fetch the user profile data
    //         $userProfile = User_profile::select('firstName', 'lastName', 'available_balance')
    //             ->where('user_id', $id)
    //             ->first(); // Use first() to get a single record

    //         // If no user profile found, skip this transaction
    //         if (!$userProfile) {
    //             continue; // Or return an error for this specific case if needed
    //         }

    //         // Fetch the service details
    //         $serviceDetails = Service::select('serviceName', 'price', 'minutesAvailable')
    //             ->where('id', $transaction->service_id) // Assuming 'service_id' is in the transaction
    //             ->first(); // Fetch a single record related to the service transaction

    //         // If no service details found, skip this transaction
    //         if (!$serviceDetails) {
    //             continue; // Or return an error for this specific case if needed
    //         }

    //         // Combine the data for this transaction
    //         $result[] = [
    //             'service_transaction' => $transaction,
    //             'user_profile' => $userProfile,
    //             'service' => $serviceDetails
    //         ];
    //     }

    //     // If no result is populated, return an error
    //     if (empty($result)) {
    //         return response()->json(['message' => 'No valid data found'], 404);
    //     }

    //     // Return the combined data as a JSON response
    //     return response()->json($result);
    // }

    /**
     * @OA\Get(
     *     path="/api/service-transactions",
     *     summary="Get all service transactions with user, location, and service details",
     *     description="Retrieves a list of all service transactions, including user profile details, preferred location details, and service details (if applicable).",
     *     operationId="getServiceTransactions",
     *     tags={"Service Transactions"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(
     *                     property="user_details",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="firstName", type="string", example="John"),
     *                     @OA\Property(property="lastName", type="string", example="Doe"),
     *                     @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                     @OA\Property(property="phone_number", type="string", example="1234567890"),
     *                     @OA\Property(property="available_balance", type="number", format="float", example=100.50),
     *                     @OA\Property(
     *                         property="preferred_location",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Main Branch"),
     *                         @OA\Property(property="address", type="string", example="123 Main St"),
     *                         @OA\Property(property="city", type="string", example="New York"),
     *                         @OA\Property(property="phone_number", type="string", example="9876543210"),
     *                         @OA\Property(property="post_code", type="string", example="10001")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="service",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Tanning Session"),
     *                     @OA\Property(property="price", type="number", format="float", example=25.00)
     *                 ),
     *                 @OA\Property(
     *                     property="transaction",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(property="type", type="string", example="used"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-19T10:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-19T10:00:00Z")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        // Define the raw SQL query as a string
        $query = "
            SELECT
                -- User profile details
                up.user_id AS user_id,
                up.firstName AS user_firstName,
                up.lastName AS user_lastName,
                up.email AS user_email,
                up.phone_number AS user_phone_number,
                up.available_balance AS user_available_balance,
                -- Preferred location details (nullable)
                l.id AS location_id,
                l.name AS location_name,
                l.address AS location_address,
                l.city AS location_city,
                l.phone_number AS location_phone_number,
                l.post_code AS location_post_code,
                -- Service details (nullable)
                COALESCE(s.id, 0) AS service_id,
                COALESCE(s.serviceName, '') AS service_name,
                COALESCE(s.price, 0) AS service_price,
                -- Transaction details
                st.id AS transaction_id,
                st.quantity AS transaction_quantity,
                st.type AS transaction_type,
                st.created_at AS transaction_created_at,
                st.updated_at AS transaction_updated_at
            FROM service_transactions st
            -- Join with user_profiles (required)
            JOIN user_profiles up ON up.user_id = st.user_id
            -- Left join with locations (preferred_location might be NULL)
            LEFT JOIN locations l ON l.id = up.preferred_location
            -- Left join with services (service_id might be NULL)
            LEFT JOIN services s ON s.id = st.service_id
        ";

        // Execute the query using DB::select with a string
        $result = DB::select($query);

        // Transform the raw query result into the desired JSON structure
        $formattedResult = array_map(function ($row) {
            return [
                'user_details' => [
                    'id' => $row->user_id,
                    'firstName' => $row->user_firstName,
                    'lastName' => $row->user_lastName,
                    'email' => $row->user_email,
                    'phone_number' => $row->user_phone_number,
                    'available_balance' => (float)$row->user_available_balance,
                    'preferred_location' => $row->location_id ? [
                        'id' => $row->location_id,
                        'name' => $row->location_name,
                        'address' => $row->location_address,
                        'city' => $row->location_city,
                        'phone_number' => $row->location_phone_number,
                        'post_code' => $row->location_post_code,
                    ] : null,
                ],
                'service' => [
                    'id' => $row->service_id,
                    'name' => $row->service_name,
                    'price' => (float)$row->service_price,
                ],
                'transaction' => [
                    'id' => $row->transaction_id,
                    'quantity' => $row->transaction_quantity,
                    'type' => $row->transaction_type,
                    'created_at' => $row->transaction_created_at,
                    'updated_at' => $row->transaction_updated_at,
                ],
            ];
        }, $result);

        // Close the database connection
        $connectionName = DB::getDefaultConnection();
        DB::disconnect($connectionName);

        return response()->json($formattedResult);
    }




    /**
     * @OA\Post(
     *     path="/api/service-transactions",
     *     summary="Create a new service transaction",
     *     tags={"Service Transactions"},
     *     description="Store a new service transaction in the database.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="string", example="123"),
     *             @OA\Property(property="quantity", type="string", example="2"),
     *             @OA\Property(property="type", type="string", example="purchase"),
     *             @OA\Property(property="location", type="string", example="New York"),
     *             @OA\Property(property="service", type="string", example="Haircut")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Service transaction created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'type' => 'nullable|string|in:purchased,used,credit',
            'location_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
            'quantity' => 'nullable|integer',
        ]);

        // Check if service exists for 'purchased' or 'used' types
        $serviceQuantity = null;
        if (in_array($request->type, ['purchased', 'used'])) {
            $service = Service::find($request->service_id);
            if (!$service) {
                return response()->json(['error' => 'Service not found'], 404);
            }
            $serviceQuantity = $service->minutesAvailable;
        }

        // Set quantity based on type
        $quantity = ($request->type === 'credit') ? $request->quantity : $serviceQuantity;

        // Create the service transaction
        $serviceTransaction = ServiceTransaction::create([
            'user_id' => $request->user_id,
            'quantity' => $quantity,
            'type' => $request->type,
            'location' => $request->location_id,
            'service_id' => $request->service_id,
        ]);

        // Fetch user profile
        $userProfile = User_profile::where('user_id', $request->user_id)->first();
        if (!$userProfile) {
            return response()->json(['error' => 'User profile not found'], 404);
        }

        // Update balance based on transaction type
        if ($request->type === 'purchased' || $request->type === 'credit') {
            $newAvailableBalance = $userProfile->available_balance + $quantity;
        } elseif ($request->type === 'used') {
            if ($userProfile->available_balance < $quantity) {
                return response()->json(['error' => 'Insufficient balance'], 422);
            }
            $newAvailableBalance = $userProfile->available_balance - $quantity;
        }

        // Update user's available balance
        $userProfile->update(['available_balance' => $newAvailableBalance]);

        return response()->json(['message' => 'Service transaction created successfully', 'data' => $serviceTransaction], 201);
    }




    /**
     * @OA\Get(
     *     path="/api/service-transactions/{id}",
     *     summary="Get a specific service transaction by user_id",
     *     tags={"Service Transactions"},
     *     description="Retrieve the details of a specific service transaction.",
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         description="ID of the service transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service transaction details"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service transaction not found"
     *     )
     * )
     */
    public function show($id)
    {
        // Fetch the service transactions for the user
        $transactions = DB::table('service_transactions')
            ->where('user_id', $id)
            ->get();

        // Check if no transactions found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No service transactions found'], 404);
        // }

        $result = [];

        // Loop through each transaction
        foreach ($transactions as $transaction) {
            $userId = $transaction->user_id;

            // Fetch the user profile data
            $userProfile = User_profile::select('user_id as _id', 'firstName', 'lastName', 'email', 'phone_number', 'available_balance', 'preferred_location')
                ->where('user_id', $userId)
                ->first();

            // If user profile not found, continue to the next transaction
            if (!$userProfile) {
                continue;
            }

            // Fetch the preferred location details
            $preferredLocation = Location::select('id as _id', 'name', 'address', 'city', 'phone_number', 'post_code')
                ->where('id', $userProfile->preferred_location)
                ->first();

            // Fetch the service details
            $service = $transaction->service_id
                ? Service::select('id as _id', 'serviceName', 'price')
                ->find($transaction->service_id)
                : null;

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
                'service' => $service ? [
                    'id' => $service->_id,
                    'name' => $service->serviceName,
                    'price' => $service->price,
                ] : [
                    'id' => 0,
                    'name' => "",
                    'price' => 0,
                ],
                'transaction' => [
                    'id' => $transaction->id,
                    'quantity' => $transaction->quantity,
                    'type' => $transaction->type,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ],
            ];
        }

        // If no valid transactions were found, return an error
        // if (empty($result)) {
        //     return response()->json(['error' => 'No valid service transactions found'], 404);
        // }

        // Return the combined data as a JSON response
        return response()->json($result);
    }




    public function creditMinutes(Request $request)
    {
        // Validate request inputs
        $validated = $request->validate([
            'available_balance' => 'integer|required',
            'user_id' => 'integer|required',
        ]);

        $type = "credit";

        // Fetch the user's current available balance
        $userProfile = User_profile::where('user_id', $validated['user_id'])->first();

        // Check if the user profile exists
        if ($userProfile) {
            // Add the available balance to the user's current balance
            $userProfile->available_balance += $validated['available_balance'];

            // Save the updated balance
            $userProfile->save();



            // Return a success response
            return response()->json(['message' => 'Balance updated successfully.', 'available_balance' => $userProfile->available_balance], 200);
        } else {
            // Return an error response if user profile not found
            return response()->json(['message' => 'User not found.'], 404);
        }
    }




    /**
     * @OA\Put(
     *     path="/api/service-transactions/{id}",
     *     summary="Update a service transaction",
     *     tags={"Service Transactions"},
     *     description="Update a service transaction in the database.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="quantity", type="string", example="2"),
     *             @OA\Property(property="type", type="string", example="purchase"),
     *             @OA\Property(property="location", type="string", example="New York"),
     *             @OA\Property(property="service", type="string", example="Haircut")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service transaction updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service transaction not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $serviceTransaction = ServiceTransaction::find($id);

        if (!$serviceTransaction) {
            return response()->json(['message' => 'Service transaction not found'], 404);
        }

        $request->validate([
            'quantity' => 'nullable|string',
            'type' => 'nullable|string',
            'location' => 'nullable|string',
            'service' => 'nullable|string',
        ]);

        $serviceTransaction->update([
            'quantity' => $request->quantity ?? $serviceTransaction->quantity,
            'type' => $request->type ?? $serviceTransaction->type,
            'location' => $request->location ?? $serviceTransaction->location,
            'service' => $request->service ?? $serviceTransaction->service,
        ]);

        return response()->json(['message' => 'Service transaction updated successfully', 'data' => $serviceTransaction]);
    }

    /**
     * @OA\Delete(
     *     path="/api/service-transactions/{id}",
     *     summary="Delete a service transaction",
     *     tags={"Service Transactions"},
     *     description="Delete a service transaction from the database.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service transaction",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service transaction deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service transaction not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $serviceTransaction = ServiceTransaction::find($id);

        if (!$serviceTransaction) {
            return response()->json(['message' => 'Service transaction not found'], 404);
        }

        // Retrieve the user profile associated with the service transaction
        $userProfile = User_Profile::find($serviceTransaction->user_id);

        if (!$userProfile) {
            return response()->json(['message' => 'User profile not found'], 404);
        }

        // Check the type of the transaction
        if ($serviceTransaction->type === 'purchased') {
            // Decrease the available balance by the quantity
            $userProfile->available_balance -= $serviceTransaction->quantity;
            $userProfile->save();
        } elseif ($serviceTransaction->type === 'used') {
            // Check if the available balance is sufficient
            if ($userProfile->available_balance < $serviceTransaction->quantity) {
                return response()->json(['message' => 'Insufficient balance'], 400);
            }
            // Decrease the available balance by the quantity
            $userProfile->available_balance -= $serviceTransaction->quantity;
            $userProfile->save();
        }

        // Delete the service transaction
        $serviceTransaction->delete();
        return response()->json(['message' => 'Service transaction deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/total-spend/{userId}",
     *     summary="Calculate total spend after last purchase",
     *     tags={"Service Transactions"},
     *     description="Get the total quantity spent by the user after their last purchase date and update the total_spend field in the user profile.",
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The ID of the user"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Total spend updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Total spend updated successfully"),
     *             @OA\Property(property="total_spend", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User profile or purchase transaction not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No purchase transactions found for this user.")
     *         )
     *     )
     * )
     */
    public function totalSpend($userId)
    {
        // Fetch the last purchase date for the user
        $lastPurchaseTransaction = ServiceTransaction::where('user_id', $userId)
            ->where('type', 'purchased')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastPurchaseTransaction) {
            return response()->json(['message' => 'No purchase transactions found for this user.'], 404);
        }

        // Get the last purchase date
        $lastPurchaseDate = $lastPurchaseTransaction->created_at;

        // Get all usage transactions after the last purchase date
        $usedTransactions = ServiceTransaction::where('user_id', $userId)
            ->where('type', 'used')
            ->where('created_at', '>=', $lastPurchaseDate)
            ->get();

        // Calculate total quantity spent
        $totalSpendQuantity = $usedTransactions->sum('quantity');

        // Find the user's profile
        $userProfile = User_profile::where('user_id', $userId)->first();

        if (!$userProfile) {
            return response()->json(['message' => 'User profile not found.'], 404);
        }

        // Update the total_spend field
        $userProfile->total_spend += $totalSpendQuantity;
        $userProfile->save();

        return response()->json([
            'message' => 'Total spend updated successfully',
            'total_spend' => $userProfile->total_spend
        ], 200);
    }


    public function minuteUsed($id = null)
    {
        // Check if an ID is provided
        if ($id) {
            // Calculate the sum of the quantity field for the specified user ID and type 'used'
            $totalUsed = ServiceTransaction::where('user_id', $id)
                ->where('type', 'used')
                ->sum('quantity');

            // Calculate the sum of the quantity field for the specified user ID and type 'purchased'
            $totalPurchased = ServiceTransaction::where('user_id', $id)
                ->where('type', 'purchased')
                ->sum('quantity');
        } else {
            // Calculate the sum of the quantity field for all users where type is 'used'
            $totalUsed = ServiceTransaction::where('type', 'used')->sum('quantity');

            // Calculate the sum of the quantity field for all users where type is 'purchased'
            $totalPurchased = ServiceTransaction::where('type', 'purchased')->sum('quantity');
        }

        // Prepare the response data
        $totalQuantity = [
            'totalUsed' => $totalUsed,
            'totalPurchased' => $totalPurchased
        ];

        // Return the response in JSON format
        return response()->json(['total_quantity' => $totalQuantity]);
    }


    public function servicePurchased()
    {
        // Fetch all service transactions where type is 'purchased'
        $transactions = ServiceTransaction::where('type', 'purchased')->get();

        // Check if no transactions found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No purchased service transactions found'], 404);
        // }

        // Prepare to group data
        $groupedData = [];

        // Get unique location IDs from the transactions
        $locationIds = $transactions->pluck('location')->unique();

        // Fetch all services and locations in a single query
        $serviceIds = $transactions->pluck('service_id')->unique();
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

        // Loop through each transaction to group them by location and service name
        foreach ($transactions as $transaction) {
            $locationId = $transaction->location; // Get location ID from transaction
            $serviceId = $transaction->service_id; // Get service ID from transaction

            // Fetch service details from the already-fetched service collection
            $service = $services->get($serviceId);
            if (!$service) {
                continue; // Skip if service not found
            }

            $serviceName = $service->serviceName; // Get service name
            $price = $service->price; // Get service price
            $date = $transaction->created_at->format('Y-m-d'); // Get the transaction date in 'Y-m-d' format

            // Initialize the location group if it doesn't exist
            if (!isset($groupedData[$locationId])) {
                $location = $locations->get($locationId);
                $groupedData[$locationId] = [
                    'location' => [
                        'id' => $locationId,
                        'name' => $location->name,
                        'address' => $location->address,
                        'city' => $location->city,
                        'phone_number' => $location->phone_number,
                        'post_code' => $location->post_code,
                    ],
                    'services' => [],
                ];
            }

            // Initialize the service group if it doesn't exist for the location
            if (!isset($groupedData[$locationId]['services'][$serviceName])) {
                $groupedData[$locationId]['services'][$serviceName] = [
                    'total_quantity' => 0,
                    'total_price' => 0, // Initialize total price
                    'last_transaction_date' => $date, // Store the last transaction date
                ];
            }

            // Aggregate data
            $groupedData[$locationId]['services'][$serviceName]['total_quantity'] += $transaction->quantity;
            $groupedData[$locationId]['services'][$serviceName]['total_price'] += $price * $transaction->quantity; // Total price reflects the sum based on quantity
            // Update the last transaction date if the current transaction date is more recent
            $currentTransactionDate = $transaction->created_at->format('Y-m-d');
            if ($currentTransactionDate > $groupedData[$locationId]['services'][$serviceName]['last_transaction_date']) {
                $groupedData[$locationId]['services'][$serviceName]['last_transaction_date'] = $currentTransactionDate;
            }
        }

        // Prepare final result array
        $result = [];
        foreach ($groupedData as $location) {
            foreach ($location['services'] as $serviceName => $service) {
                $result[] = [
                    'location' => $location['location'],
                    'service_name' => $serviceName,
                    'total_quantity' => $service['total_quantity'],
                    'total_price' => $service['total_price'],
                    'date' => $service['last_transaction_date'], // Use the last transaction date for the grouping
                ];
            }
        }

        // Return the grouped data as a JSON response
        return response()->json($result);
    }

    public function servicePurchase(Request $request)
    {
        try {
            // Validate request parameters
            $validatedData = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'location_id' => 'nullable|integer|exists:locations,id', // Optional location filter
            ]);

            $startDate = $validatedData['start_date'] . ' 00:00:00';
            $endDate = $validatedData['end_date'] . ' 23:59:59';
            $locationId = $validatedData['location_id'] ?? null;

            // Start the transaction query
            $transactionsQuery = ServiceTransaction::where('type', 'purchased');

            // Check if start and end dates are the same
            if ($startDate === $endDate) {
                // If same date, use whereDate to match only that specific date
                $transactionsQuery->whereDate('created_at', $startDate);
            } else {
                // Otherwise, use whereBetween for the date range
                $transactionsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            // Filter by location_id if provided and not zero
            if (!is_null($locationId) && $locationId !== 0) {
                $transactionsQuery->where('location', $locationId);
            }

            // Execute the query to get the filtered transactions
            $transactions = $transactionsQuery->get();

            // Prepare to group data
            $groupedData = [];

            // Get unique location IDs from the transactions
            $locationIds = $transactions->pluck('location')->unique();

            // Fetch all services and locations in a single query
            $serviceIds = $transactions->pluck('service_id')->unique();
            $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
            $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

            // Loop through each transaction to group them by location and service name
            foreach ($transactions as $transaction) {
                $locationId = $transaction->location;
                $serviceId = $transaction->service_id;

                $service = $services->get($serviceId);
                if (!$service) {
                    continue; // Skip if service not found
                }

                $serviceName = $service->serviceName;
                $price = $service->price;
                $date = $transaction->created_at->format('Y-m-d');

                // Ensure location exists before accessing its properties
                $location = $locations->get($locationId);
                if (!$location) {
                    continue; // Skip if location not found
                }

                if (!isset($groupedData[$locationId])) {
                    $groupedData[$locationId] = [
                        'location' => [
                            'id' => $locationId,
                            'name' => $location->name,
                            'address' => $location->address,
                            'city' => $location->city,
                            'phone_number' => $location->phone_number,
                            'post_code' => $location->post_code,
                        ],
                        'services' => [],
                    ];
                }

                if (!isset($groupedData[$locationId]['services'][$serviceName])) {
                    $groupedData[$locationId]['services'][$serviceName] = [
                        'total_quantity' => 0,
                        'total_price' => 0,
                        'last_transaction_date' => $date,
                    ];
                }

                $groupedData[$locationId]['services'][$serviceName]['total_quantity'] += $transaction->quantity;
                $groupedData[$locationId]['services'][$serviceName]['total_price'] += $price;
                $currentTransactionDate = $transaction->created_at->format('Y-m-d');
                if ($currentTransactionDate > $groupedData[$locationId]['services'][$serviceName]['last_transaction_date']) {
                    $groupedData[$locationId]['services'][$serviceName]['last_transaction_date'] = $currentTransactionDate;
                }
            }

            $result = [];
            foreach ($groupedData as $location) {
                foreach ($location['services'] as $serviceName => $service) {
                    $result[] = [
                        'location' => $location['location'],
                        'serviceName' => $serviceName,
                        'total_quantity' => $service['total_quantity'],
                        'total_price' => $service['total_price'],
                        'date' => $service['last_transaction_date'],
                    ];
                }
            }

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], 500);
        }
    }




    public function serviceUse(Request $request)
    {
        // Validate input parameters
        $validatedData = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        // Format start and end date strings to cover the full day range
        $startDate = $validatedData['start_date'] . ' 00:00:00';
        $endDate = $validatedData['end_date'] . ' 23:59:59';
        $locationId = $validatedData['location_id'] ?? null;

        // Build the query to filter service transactions by type 'used' and date range
        $query = ServiceTransaction::where('type', 'used')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Apply location filter if provided
        if ($locationId) {
            $query->where('location', $locationId);
        }

        // Fetch transactions
        $transactions = $query->get();

        // Check if any transactions were found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No used service transactions found'], 404);
        // }

        // Prepare data for grouping
        $groupedData = [];
        $locationIds = $transactions->pluck('location')->unique();
        $serviceIds = $transactions->pluck('service_id')->unique();

        // Fetch related services and locations in one query each
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

        // Loop to group transactions by location and service name
        foreach ($transactions as $transaction) {
            $locationId = $transaction->location;
            $serviceId = $transaction->service_id;

            $service = $services->get($serviceId);
            if (!$service) {
                continue;
            }

            $serviceName = $service->serviceName;
            $price = $service->price;
            $date = $transaction->created_at->format('Y-m-d');

            // Initialize location group
            if (!isset($groupedData[$locationId])) {
                $location = $locations->get($locationId);
                $groupedData[$locationId] = [
                    'location' => [
                        'id' => $locationId,
                        'name' => $location ? $location->name : 'Unknown',
                        'address' => $location ? $location->address : 'N/A',
                        'city' => $location ? $location->city : 'N/A',
                        'phone_number' => $location ? $location->phone_number : 'N/A',
                        'post_code' => $location ? $location->post_code : 'N/A',
                    ],
                    'services' => [],
                ];
            }

            // Initialize service group
            if (!isset($groupedData[$locationId]['services'][$serviceName])) {
                $groupedData[$locationId]['services'][$serviceName] = [
                    'total_quantity' => 0,
                    'total_price' => 0,
                    'last_transaction_date' => $date,
                ];
            }

            // Aggregate data
            $groupedData[$locationId]['services'][$serviceName]['total_quantity'] += $transaction->quantity;
            $groupedData[$locationId]['services'][$serviceName]['total_price'] += $price;

            // Update last transaction date if more recent
            if ($transaction->created_at->format('Y-m-d') > $groupedData[$locationId]['services'][$serviceName]['last_transaction_date']) {
                $groupedData[$locationId]['services'][$serviceName]['last_transaction_date'] = $transaction->created_at->format('Y-m-d');
            }
        }

        // Prepare final result array
        $result = [];
        foreach ($groupedData as $location) {
            foreach ($location['services'] as $serviceName => $service) {
                $result[] = [
                    'location' => $location['location'],
                    'serviceName' => $serviceName,
                    'total_quantity' => $service['total_quantity'],
                    'total_price' => $service['total_price'],
                    'date' => $service['last_transaction_date'],
                ];
            }
        }

        // if (empty($result)) {
        //     return response()->json(['error' => 'No valid used service transactions found'], 404);
        // }

        // Return the grouped data as JSON response
        return response()->json($result);
    }






    public function serviceUsed()
    {
        // Fetch all service transactions where type is 'used'
        $transactions = ServiceTransaction::where('type', 'used')->get();

        // Check if no transactions found
        // if ($transactions->isEmpty()) {
        //     return response()->json(['error' => 'No used service transactions found'], 404);
        // }

        // Prepare to group data
        $groupedData = [];

        // Get unique location IDs from the transactions
        $locationIds = $transactions->pluck('location')->unique();

        // Fetch all services and locations in a single query
        $serviceIds = $transactions->pluck('service_id')->unique();
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

        // Loop through each transaction to group them by location and service name
        foreach ($transactions as $transaction) {
            $locationId = $transaction->location; // Get location ID from transaction
            $serviceId = $transaction->service_id; // Get service ID from transaction

            // Fetch service details from the already-fetched service collection
            $service = $services->get($serviceId);
            if (!$service) {
                continue; // Skip if service not found
            }

            $serviceName = $service->serviceName; // Get service name
            $price = $service->price; // Get service price
            $date = $transaction->created_at->format('Y-m-d'); // Get the transaction date in 'Y-m-d' format

            // Initialize the location group if it doesn't exist
            if (!isset($groupedData[$locationId])) {
                $location = $locations->get($locationId);
                $groupedData[$locationId] = [
                    'location' => [
                        'id' => $locationId,
                        'name' => $location->name,
                        'address' => $location->address,
                        'city' => $location->city,
                        'phone_number' => $location->phone_number,
                        'post_code' => $location->post_code,
                    ],
                    'services' => [],
                ];
            }

            // Initialize the service group if it doesn't exist for the location
            if (!isset($groupedData[$locationId]['services'][$serviceName])) {
                $groupedData[$locationId]['services'][$serviceName] = [
                    'total_quantity' => 0,
                    'total_price' => 0, // Initialize total price
                    'last_transaction_date' => $date, // Store the last transaction date
                ];
            }

            // Aggregate data
            $groupedData[$locationId]['services'][$serviceName]['total_quantity'] += $transaction->quantity;
            $groupedData[$locationId]['services'][$serviceName]['total_price'] += $price * $transaction->quantity; // Total price reflects the sum based on quantity

            // Update the last transaction date if the current transaction date is more recent
            $currentTransactionDate = $transaction->created_at->format('Y-m-d');
            if ($currentTransactionDate > $groupedData[$locationId]['services'][$serviceName]['last_transaction_date']) {
                $groupedData[$locationId]['services'][$serviceName]['last_transaction_date'] = $currentTransactionDate;
            }
        }

        // Prepare final result array
        $result = [];
        foreach ($groupedData as $location) {
            foreach ($location['services'] as $serviceName => $service) {
                $result[] = [
                    'location' => $location['location'],
                    'service_name' => $serviceName,
                    'total_quantity' => $service['total_quantity'],
                    'total_price' => $service['total_price'],
                    'date' => $service['last_transaction_date'], // Use the last transaction date for the grouping
                ];
            }
        }

        // If no valid grouped transactions were found, return an error
        // if (empty($result)) {
        //     return response()->json(['error' => 'No valid used service transactions found'], 404);
        // }

        // Return the grouped data as a JSON response
        return response()->json($result);
    }


    public function customerDayUsage(Request $request)
    {
        $validatedData = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'location_id' => 'nullable|integer',
        ]);

        // Format start and end dates to cover the full day range
        $startDate = $validatedData['start_date'] . ' 00:00:00';
        $endDate = $validatedData['end_date'] . ' 23:59:59';

        // Fetch transactions with aggregated user count, filtering by date and location if provided
        $query = ServiceTransaction::selectRaw('DATE(created_at) as date, location, COUNT(DISTINCT user_id) as user_count')
            ->where('type', 'used')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date', 'location');

        // Apply location filter if location_id is provided
        if (!is_null($validatedData['location_id'])) {
            $query->where('location', $validatedData['location_id']);
        }

        $result = $query->get();

        // Retrieve location names in a single query to avoid looping calls
        $locationNames = Location::whereIn('id', $result->pluck('location'))->pluck('name', 'id');

        // Prepare final data array
        $data = $result->map(function ($re) use ($locationNames) {
            return [
                'date' => $re->date,
                'location_id' => $re->location,
                'location' => $locationNames[$re->location] ?? null, // Fallback if location name is missing
                'userCount' => $re->user_count,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
