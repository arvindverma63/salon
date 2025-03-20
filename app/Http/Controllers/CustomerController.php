<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductTransaction;
use App\Models\Service;
use App\Models\ServiceTransaction;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

class CustomerController extends Controller
{
    /**
     * Get list of customers with their profiles and transaction totals using offset pagination
     *
     * @OA\Get(
     *     path="/api/customers",
     *     summary="Get list of customers with their transaction data using offset pagination",
     *     tags={"Customers"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="role", type="string", example="customer")
     *                     ),
     *                     @OA\Property(property="profile", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="user_id", type="integer"),
     *                         @OA\Property(property="firstName", type="string", nullable=true),
     *                         @OA\Property(property="lastName", type="string", nullable=true),
     *                         @OA\Property(property="phone_number", type="string", nullable=true),
     *                         additionalProperties=true
     *                     ),
     *                     @OA\Property(property="total_used_minutes", type="number", example=120),
     *                     @OA\Property(property="total_service_purchased_price", type="number", example=100.50),
     *                     @OA\Property(property="total_product_purchased_price", type="number", example=75.25),
     *                     @OA\Property(property="total_price", type="number", example=175.75)
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="limit", type="integer", example=15),
     *                 @OA\Property(property="offset", type="integer", example=0),
     *                 @OA\Property(property="has_more", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     )
     * )
     */
    public function getAllCustomers(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $searchKey = $request->input('searchKey');
        $offset = ($page - 1) * $perPage;

        $query = User::where('role', 'customer')->orderBy('id');
        $total = $query->count();
        $users = $query->skip($offset)->take($perPage)->get();

        $usersWithProfiles = [];

        foreach ($users as $user) {
            $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();
            $totalUsedMinutes = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'used')
                ->sum('quantity');

            $totalServicePurchasedPrice = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'purchased')
                ->get()
                ->sum(function ($transaction) {
                    $service = Service::find($transaction->service_id);
                    return $service ? $service->price : 0;
                });

            $totalProductPurchasedPrice = ProductTransaction::where('user_id', $user->id)
                ->get()
                ->sum(function ($transaction) {
                    $product = Product::find($transaction->product_id);
                    return $product ? $transaction->quantity * $product->price : 0;
                });

            $usersWithProfiles[] = [
                'user' => $user,
                'profile' => $profile,
                'total_used_minutes' => $totalUsedMinutes,
                'total_service_purchased_price' => $totalServicePurchasedPrice,
                'total_product_purchased_price' => $totalProductPurchasedPrice,
                'total_price' => $totalServicePurchasedPrice + $totalProductPurchasedPrice
            ];
        }

        return response()->json([
            'data' => $usersWithProfiles,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => $offset + count($usersWithProfiles)
            ]
        ]);
    }

    /**
     * Search customers by various criteria including profile fields
     *
     * @OA\Get(
     *     path="/api/customers/search",
     *     summary="Search customers with filtering options",
     *     tags={"Customers"},
     *     @OA\Parameter(
     *         name="min_total",
     *         in="query",
     *         description="Minimum total purchase amount",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="Email address to filter by (partial match)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="key",
     *         in="query",
     *         description="Search term to match against firstName, lastName, or phone_number",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="locationId",
     *         in="query",
     *         description="Filter by preferred location ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="role", type="string", example="customer")
     *                     ),
     *                     @OA\Property(property="profile", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="user_id", type="integer"),
     *                         @OA\Property(property="firstName", type="string", nullable=true),
     *                         @OA\Property(property="lastName", type="string", nullable=true),
     *                         @OA\Property(property="phone_number", type="string", nullable=true),
     *                         additionalProperties=true
     *                     ),
     *                     @OA\Property(property="total_used_minutes", type="number"),
     *                     @OA\Property(property="total_service_purchased_price", type="number"),
     *                     @OA\Property(property="total_product_purchased_price", type="number"),
     *                     @OA\Property(property="total_price", type="number")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid search parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid search parameters")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     )
     * )
     */

    public function searchCustomers(Request $request)
    {
        // Initialize base query for customers
        $query = User::where('role', 'customer')->orderBy('id');

        // Apply email filter if provided
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        // Set up profile query for filtering by profile fields
        $profileQuery = DB::table('user_profiles');
        $hasValidFilter = false;

        // Apply location filter with key search
        if ($request->has('locationId')) {

            $hasValidFilter = true;
            if ($request->has('key') && $request->input('key') !== '') {
                $key = '%' . $request->input('key') . '%';
                $profileQuery->where('preferred_location', $request->input('locationId') ? $request->input('locationId') : null)
                    ->where(function ($q) use ($key) {
                        $q->where('firstName', 'like', $key)
                            ->orWhere('lastName', 'like', $key)
                            ->orWhere('phone_number', 'like', $key);
                    });
            } else {
                // When key is empty or not provided, filter only by locationId (including '0')
                $profileQuery->where('preferred_location', $request->input('locationId'));
            }
        } else {
            // Apply single key search across multiple profile fields
            if ($request->has('key')) {
                if ($request->input('key') !== '') {
                    $hasValidFilter = true;
                    $key = '%' . $request->input('key') . '%';
                    $profileQuery->where(function ($q) use ($key) {
                        $q->where('firstName', 'like', $key)
                            ->orWhere('lastName', 'like', $key)
                            ->orWhere('phone_number', 'like', $key);
                    });
                } else {
                    // Return empty when key is empty and no locationId
                    return response()->json(['data' => []]);
                }
            } else {
                // Return empty when no key is provided and no locationId
                return response()->json(['data' => []]);
            }
        }

        // If no valid filters are applied (besides email), return empty result
        if (!$hasValidFilter && !$request->has('email')) {
            return response()->json(['data' => []]);
        }

        // Apply profile filters to main query if any exist
        if ($hasValidFilter) {
            $matchingUserIds = $profileQuery->pluck('user_id')->toArray();
            if (empty($matchingUserIds)) {
                return response()->json(['data' => []]);
            }
            $query->whereIn('id', $matchingUserIds);
        }

        // Execute query and get results
        $users = $query->get();
        $usersWithProfiles = [];

        // Process each user and calculate totals
        foreach ($users as $user) {
            $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();

            // Calculate total used minutes from service transactions
            $totalUsedMinutes = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'used')
                ->sum('quantity');

            // Calculate total service purchase price
            $totalServicePurchasedPrice = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'purchased')
                ->get()
                ->sum(function ($transaction) {
                    $service = Service::find($transaction->service_id);
                    return $service ? $service->price : 0;
                });

            // Calculate total product purchase price
            $totalProductPurchasedPrice = ProductTransaction::where('user_id', $user->id)
                ->get()
                ->sum(function ($transaction) {
                    $product = Product::find($transaction->product_id);
                    return $product ? $transaction->quantity * $product->price : 0;
                });

            $totalPrice = $totalServicePurchasedPrice + $totalProductPurchasedPrice;

            // Apply minimum total filter if specified
            if ($request->has('min_total') && $totalPrice < $request->input('min_total')) {
                continue;
            }

            // Build result array for each user
            $usersWithProfiles[] = [
                'user' => $user,
                'profile' => $profile,
                'total_used_minutes' => $totalUsedMinutes,
                'total_service_purchased_price' => $totalServicePurchasedPrice,
                'total_product_purchased_price' => $totalProductPurchasedPrice,
                'total_price' => $totalPrice
            ];
        }

        return response()->json(['data' => $usersWithProfiles]);
    }

    /**
     * Get customers with transaction data within a date range using page-based pagination
     *
     * @OA\Post(
     *     path="/api/customers/date-range",
     *     summary="Get customers with transaction data within a date range using page-based pagination",
     *     tags={"Customers"},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date-time", example="2023-01-01 00:00:00", description="Start date for transaction filtering (YYYY-MM-DD HH:MM:SS)"),
     *             @OA\Property(property="end_date", type="string", format="date-time", example="2023-01-07 23:59:59", description="End date for transaction filtering (YYYY-MM-DD HH:MM:SS)"),
     *             @OA\Property(property="page", type="integer", example=1, default=1, description="Page number"),
     *             @OA\Property(property="per_page", type="integer", example=15, default=15, description="Number of items per page")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="role", type="string", example="customer")
     *                     ),
     *                     @OA\Property(property="profile", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="user_id", type="integer"),
     *                         @OA\Property(property="firstName", type="string", nullable=true),
     *                         @OA\Property(property="lastName", type="string", nullable=true),
     *                         @OA\Property(property="phone_number", type="string", nullable=true),
     *                         additionalProperties=true
     *                     ),
     *                     @OA\Property(property="total_used_minutes", type="number"),
     *                     @OA\Property(property="total_service_purchased_price", type="number"),
     *                     @OA\Property(property="total_product_purchased_price", type="number"),
     *                     @OA\Property(property="total_price", type="number")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid date format")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     )
     * )
     */
    public function customerDateRange(Request $request)
    {
        // Retrieve filter parameters
        try {
            $startDate = $request->input('start_date')
                ? new DateTime($request->input('start_date'))
                : now()->startOfWeek()->setTime(0, 0, 0);
            $endDate = $request->input('end_date')
                ? new DateTime($request->input('end_date'))
                : now()->endOfWeek()->setTime(23, 59, 59);

            // Ensure full day coverage if only date is provided
            if (strlen($request->input('start_date')) <= 10) {
                $startDate->setTime(0, 0, 0);
            }
            if (strlen($request->input('end_date')) <= 10) {
                $endDate->setTime(23, 59, 59);
            }

            // Log the date range for debugging
            Log::info('Date Range', [
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        // Get users with transactions in the date range
        $userIdsWithTransactions = array_unique(array_merge(
            ServiceTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->pluck('user_id')->toArray(),
            ProductTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->pluck('user_id')->toArray()
        ));

        // Log user IDs with transactions
        Log::info('Users with transactions in date range', ['user_ids' => $userIdsWithTransactions]);

        $query = User::where('role', 'customer')
            ->whereIn('id', $userIdsWithTransactions) // Only users with transactions
            ->orderBy('id');

        $total = $query->count();
        if ($total === 0) {
            Log::info('No customers with transactions found in date range');
            return response()->json([
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => 0,
                    'from' => null,
                    'to' => null
                ]
            ]);
        }

        $users = $query->skip($offset)->take($perPage)->get();

        $usersWithProfiles = [];

        foreach ($users as $user) {
            $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();

            $totalUsedMinutes = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'used')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('quantity');

            $serviceTransactions = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'purchased')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();
            $totalServicePurchasedPrice = $serviceTransactions->sum(function ($transaction) {
                $service = Service::find($transaction->service_id);
                return $service ? $service->price : 0;
            });

            $productTransactions = ProductTransaction::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();
            $totalProductPurchasedPrice = $productTransactions->sum(function ($transaction) {
                $product = Product::find($transaction->product_id);
                return $product ? $transaction->quantity * $product->price : 0;
            });

            $totalPrice = $totalServicePurchasedPrice + $totalProductPurchasedPrice;

            // Log transaction details for debugging
            Log::info('User Transaction Data', [
                'user_id' => $user->id,
                'total_used_minutes' => $totalUsedMinutes,
                'total_service_purchased_price' => $totalServicePurchasedPrice,
                'total_product_purchased_price' => $totalProductPurchasedPrice,
                'total_price' => $totalPrice,
                'service_transactions' => $serviceTransactions->toArray(),
                'product_transactions' => $productTransactions->toArray()
            ]);

            $usersWithProfiles[] = [
                'user' => $user,
                'profile' => $profile,
                'total_used_minutes' => $totalUsedMinutes,
                'total_service_purchased_price' => $totalServicePurchasedPrice,
                'total_product_purchased_price' => $totalProductPurchasedPrice,
                'total_price' => $totalPrice
            ];
        }

        return response()->json([
            'data' => $usersWithProfiles,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => $offset + count($usersWithProfiles)
            ]
        ]);
    }



    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Get all customers with their usage and purchase details",
     *     description="Retrieves a list of users with role 'customer', including their profile details, total used minutes, and total purchased service/product prices.",
     *     operationId="getAllUsers",
     *     tags={"Users"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2258),
     *                     @OA\Property(property="name", type="string", example="Ruby White"),
     *                     @OA\Property(property="email", type="string", example="ruby2pro@gmail.com"),
     *                     @OA\Property(property="role", type="string", example="customer")
     *                 ),
     *                 @OA\Property(
     *                     property="profile",
     *                     type="object",
     *                     @OA\Property(property="address", type="string", example="Connerways Intern Lane Madge..."),
     *                     @OA\Property(property="phone_number", type="string", example="07541888560", nullable=true),
     *                     @OA\Property(property="preferred_location", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T10:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T10:00:00Z"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="available_balance", type="number", format="float", example=100.50),
     *                     @OA\Property(property="total_spend", type="number", format="float", example=500.00),
     *                     @OA\Property(property="dob", type="string", format="date", example="1990-05-15", nullable=true),
     *                     @OA\Property(property="gdpr_email_active", type="boolean", example=true),
     *                     @OA\Property(property="gdpr_sms_active", type="boolean", example=false),
     *                     @OA\Property(property="gender", type="string", example="female", nullable=true),
     *                     @OA\Property(property="firstName", type="string", example="Ruby", nullable=true),
     *                     @OA\Property(property="lastName", type="string", example="White", nullable=true),
     *                     @OA\Property(property="post_code", type="string", example="AB12 3CD", nullable=true)
     *                 ),
     *                 @OA\Property(property="total_used_minutes", type="integer", example=0),
     *                 @OA\Property(property="total_service_purchased_price", type="number", format="float", example=0.00),
     *                 @OA\Property(property="total_product_purchased_price", type="number", format="float", example=0.00),
     *                 @OA\Property(property="total_price", type="number", format="float", example=0.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=false)
     *         )
     *     )
     * )
     */
    public function getAllUsers()
    {
        try {
            // Fetch the raw data with additional user_profiles fields
            $results = DB::table('users as u')
                ->select(
                    'u.id',
                    'u.email',
                    'u.role',
                    'up.address',
                    'up.phone_number',
                    'up.preferred_location',
                    'up.created_at',
                    'up.updated_at',
                    'up.active',
                    'up.available_balance',
                    'up.total_spend',
                    'up.dob',
                    'up.gdpr_email_active',
                    'up.gdpr_sms_active',
                    'up.gender',
                    'up.firstName',
                    'up.lastName',
                    'up.post_code',
                    DB::raw('COALESCE(SUM(CASE WHEN st.type = \'used\' THEN st.quantity ELSE 0 END), 0) as total_used_minutes'),
                    DB::raw('COALESCE(SUM(CASE WHEN st.type = \'purchased\' THEN s.price ELSE 0 END), 0) as total_service_purchased_price'),
                    DB::raw('COALESCE(SUM(pt.quantity * p.price), 0) as total_product_purchased_price')
                )
                ->leftJoin('user_profiles as up', 'up.user_id', '=', 'u.id')
                ->leftJoin('service_transactions as st', 'st.user_id', '=', 'u.id')
                ->leftJoin('services as s', function ($join) {
                    $join->on('s.id', '=', 'st.service_id')
                        ->where('st.type', '=', 'purchased');
                })
                ->leftJoin('product_transaction as pt', 'pt.user_id', '=', 'u.id')
                ->leftJoin('products as p', 'p.id', '=', 'pt.product_id')
                ->groupBy(
                    'u.id',
                    'u.email',
                    'u.role',
                    'up.address',
                    'up.phone_number',
                    'up.preferred_location',
                    'up.created_at',
                    'up.updated_at',
                    'up.active',
                    'up.available_balance',
                    'up.total_spend',
                    'up.dob',
                    'up.gdpr_email_active',
                    'up.gdpr_sms_active',
                    'up.gender',
                    'up.firstName',
                    'up.lastName',
                    'up.post_code'
                )
                ->orderBy('u.id')
                ->get();

            // Transform the results into the desired structure
            $usersWithProfiles = $results->map(function ($result) {
                $user = [
                    'id' => $result->id,
                    'email' => $result->email,
                    'role' => $result->role,
                ];

                $profile = [
                    'address' => $result->address,
                    'phone_number' => $result->phone_number,
                    'preferred_location' => $result->preferred_location,
                    'created_at' => $result->created_at,
                    'updated_at' => $result->updated_at,
                    'active' => $result->active,
                    'available_balance' => $result->available_balance,
                    'total_spend' => $result->total_spend,
                    'dob' => $result->dob,
                    'gdpr_email_active' => $result->gdpr_email_active,
                    'gdpr_sms_active' => $result->gdpr_sms_active,
                    'gender' => $result->gender,
                    'firstName' => $result->firstName,
                    'lastName' => $result->lastName,
                    'post_code' => $result->post_code,
                ];

                $totalUsedMinutes = (int)$result->total_used_minutes; // Ensure integer
                $totalServicePurchasedPrice = (float)$result->total_service_purchased_price; // Ensure float
                $totalProductPurchasedPrice = (float)$result->total_product_purchased_price; // Ensure float
                $totalPrice = (float)($totalServicePurchasedPrice + $totalProductPurchasedPrice); // Ensure float

                return [
                    'user' => $user,
                    'profile' => $profile,
                    'total_used_minutes' => $totalUsedMinutes,
                    'total_service_purchased_price' => $totalServicePurchasedPrice,
                    'total_product_purchased_price' => $totalProductPurchasedPrice,
                    'total_price' => $totalPrice,
                ];
            })->all();

            return $usersWithProfiles;
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json(['status' => false], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/system-users",
     *     summary="Get all System users with their usage and purchase details",
     *     description="Retrieves a list of users with role 'customer', including their profile details, total used minutes, and total purchased service/product prices.",
     *     operationId="getAllSystemUser",
     *     tags={"Users"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2258),
     *                     @OA\Property(property="name", type="string", example="Ruby White"),
     *                     @OA\Property(property="email", type="string", example="ruby2pro@gmail.com"),
     *                     @OA\Property(property="role", type="string", example="customer")
     *                 ),
     *                 @OA\Property(
     *                     property="profile",
     *                     type="object",
     *                     @OA\Property(property="address", type="string", example="Connerways Intern Lane Madge..."),
     *                     @OA\Property(property="phone_number", type="string", example="07541888560", nullable=true),
     *                     @OA\Property(property="preferred_location", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T10:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T10:00:00Z"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="available_balance", type="number", format="float", example=100.50),
     *                     @OA\Property(property="total_spend", type="number", format="float", example=500.00),
     *                     @OA\Property(property="dob", type="string", format="date", example="1990-05-15", nullable=true),
     *                     @OA\Property(property="gdpr_email_active", type="boolean", example=true),
     *                     @OA\Property(property="gdpr_sms_active", type="boolean", example=false),
     *                     @OA\Property(property="gender", type="string", example="female", nullable=true),
     *                     @OA\Property(property="firstName", type="string", example="Ruby", nullable=true),
     *                     @OA\Property(property="lastName", type="string", example="White", nullable=true),
     *                     @OA\Property(property="post_code", type="string", example="AB12 3CD", nullable=true)
     *                 ),
     *                 @OA\Property(property="total_used_minutes", type="integer", example=0),
     *                 @OA\Property(property="total_service_purchased_price", type="number", format="float", example=0.00),
     *                 @OA\Property(property="total_product_purchased_price", type="number", format="float", example=0.00),
     *                 @OA\Property(property="total_price", type="number", format="float", example=0.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while fetching users")
     *         )
     *     )
     * )
     */
    public function getAllSystemUsers()
    {
        try {
            $results = DB::table('users as u')
                ->where('role', 'customer') // Fixed to match description
                ->select(
                    'u.id',
                    'u.email',
                    'u.role',
                    'up.address',
                    'up.phone_number',
                    'up.preferred_location',
                    'up.created_at',
                    'up.updated_at',
                    'up.active',
                    'up.available_balance',
                    'up.total_spend',
                    'up.dob',
                    'up.gdpr_email_active',
                    'up.gdpr_sms_active',
                    'up.gender',
                    'up.firstName',
                    'up.lastName',
                    'up.post_code',
                    DB::raw('COALESCE(SUM(CASE WHEN st.type = \'used\' THEN st.quantity ELSE 0 END), 0) as total_used_minutes'),
                    DB::raw('COALESCE(SUM(CASE WHEN st.type = \'purchased\' THEN s.price ELSE 0 END), 0) as total_service_purchased_price'),
                    DB::raw('COALESCE(SUM(pt.quantity * p.price), 0) as total_product_purchased_price')
                )
                ->leftJoin('user_profiles as up', 'up.user_id', '=', 'u.id')
                ->leftJoin('service_transactions as st', 'st.user_id', '=', 'u.id')
                ->leftJoin('services as s', function ($join) {
                    $join->on('s.id', '=', 'st.service_id')
                        ->where('st.type', '=', 'purchased');
                })
                ->leftJoin('product_transaction as pt', 'pt.user_id', '=', 'u.id')
                ->leftJoin('products as p', 'p.id', '=', 'pt.product_id')
                ->groupBy(
                    'u.id',
                    'u.email',
                    'u.role',
                    'up.address',
                    'up.phone_number',
                    'up.preferred_location',
                    'up.created_at',
                    'up.updated_at',
                    'up.active',
                    'up.available_balance',
                    'up.total_spend',
                    'up.dob',
                    'up.gdpr_email_active',
                    'up.gdpr_sms_active',
                    'up.gender',
                    'up.firstName',
                    'up.lastName',
                    'up.post_code'
                )
                ->orderBy('u.id')
                ->get();

            $usersWithProfiles = $results->map(function ($result) {
                $user = [
                    'id' => $result->id,
                    'name' => trim("{$result->firstName} {$result->lastName}"), // Combine firstName and lastName
                    'email' => $result->email,
                    'role' => $result->role,
                ];

                $profile = [
                    'address' => $result->address,
                    'phone_number' => $result->phone_number,
                    'preferred_location' => $result->preferred_location,
                    'created_at' => $result->created_at,
                    'updated_at' => $result->updated_at,
                    'active' => $result->active,
                    'available_balance' => $result->available_balance,
                    'total_spend' => $result->total_spend,
                    'dob' => $result->dob,
                    'gdpr_email_active' => $result->gdpr_email_active,
                    'gdpr_sms_active' => $result->gdpr_sms_active,
                    'gender' => $result->gender,
                    'firstName' => $result->firstName,
                    'lastName' => $result->lastName,
                    'post_code' => $result->post_code,
                ];

                $totalUsedMinutes = (int)$result->total_used_minutes;
                $totalServicePurchasedPrice = (float)$result->total_service_purchased_price;
                $totalProductPurchasedPrice = (float)$result->total_product_purchased_price;
                $totalPrice = $totalServicePurchasedPrice + $totalProductPurchasedPrice;

                return [
                    'user' => $user,
                    'profile' => $profile,
                    'total_used_minutes' => $totalUsedMinutes,
                    'total_service_purchased_price' => $totalServicePurchasedPrice,
                    'total_product_purchased_price' => $totalProductPurchasedPrice,
                    'total_price' => $totalPrice,
                ];
            })->all();

            return $usersWithProfiles;
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching users'
            ], 500);
        }
    }
}
