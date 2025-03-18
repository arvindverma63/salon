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

        // Check if key is empty and locationId is not 0
        if ((!$request->has('key') || $request->input('key') === '') &&
            !($request->has('locationId') && $request->input('locationId') === '0')
        ) {
            return response()->json(['data' => []]);
        }

        // Apply location filter with key search
        if ($request->has('locationId')) {
            if (
                $request->input('locationId') === '0' ||
                ($request->input('locationId') != null && $request->input('locationId') != '0')
            ) {
                $hasValidFilter = true;
                $key = $request->has('key') && $request->input('key') !== '' ? '%' . $request->input('key') . '%' : '%';
                $profileQuery->where(function ($q) use ($key) {
                    $q->where('firstName', 'like', $key)
                        ->orWhere('lastName', 'like', $key)
                        ->orWhere('phone_number', 'like', $key);
                });
                // Only add location filter if not 0
                if ($request->input('locationId') !== '0') {
                    $profileQuery->where('preferred_location', $request->input('locationId'));
                }
            }
        } else {
            // Apply single key search across multiple profile fields
            if ($request->has('key') && $request->input('key') !== '') {
                $hasValidFilter = true;
                $key = '%' . $request->input('key') . '%';
                $profileQuery->where(function ($q) use ($key) {
                    $q->where('firstName', 'like', $key)
                        ->orWhere('lastName', 'like', $key)
                        ->orWhere('phone_number', 'like', $key);
                });
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
}
