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
     * Search customers by various criteria including profile fields using offset pagination
     *
     * @OA\Get(
     *     path="/api/customers/search",
     *     summary="Search customers with filtering options using offset pagination",
     *     tags={"Customers"},
     *     @OA\Parameter(
     *         name="firstName",
     *         in="query",
     *         description="Search by customer's first name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="lastName",
     *         in="query",
     *         description="Search by customer's last name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="phone_number",
     *         in="query",
     *         description="Search by customer's phone number",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="Search by customer email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="min_total",
     *         in="query",
     *         description="Minimum total purchase amount",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Number of items to skip",
     *         required=false,
     *         @OA\Schema(type="integer", default=0)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of items to return",
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
     *                     @OA\Property(property="total_used_minutes", type="number"),
     *                     @OA\Property(property="total_service_purchased_price", type="number"),
     *                     @OA\Property(property="total_product_purchased_price", type="number"),
     *                     @OA\Property(property="total_price", type="number")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="limit", type="integer"),
     *                 @OA\Property(property="offset", type="integer"),
     *                 @OA\Property(property="has_more", type="boolean")
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
        $query = User::where('role', 'customer');

        // Apply email filter
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        // Get matching user IDs from user_profiles for profile fields
        $profileQuery = DB::table('user_profiles');

        if ($request->has('firstName')) {
            $profileQuery->where('firstName', 'like', '%' . $request->input('firstName') . '%');
        }

        if ($request->has('lastName')) {
            $profileQuery->where('lastName', 'like', '%' . $request->input('lastName') . '%');
        }

        if ($request->has('phone_number')) {
            $profileQuery->where('phone_number', 'like', '%' . $request->input('phone_number') . '%');
        }

        // If any profile filters are applied, restrict users to matching profile user_ids
        if ($request->hasAny(['firstName', 'lastName', 'phone_number'])) {
            $matchingUserIds = $profileQuery->pluck('user_id')->toArray();
            if (empty($matchingUserIds)) {
                return response()->json([
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'limit' => $request->input('limit', 15),
                        'offset' => $request->input('offset', 0),
                        'has_more' => false
                    ]
                ]);
            }
            $query->whereIn('id', $matchingUserIds);
        }

        // Offset-based pagination
        $limit = $request->input('limit', 15);
        $offset = $request->input('offset', 0);
        $total = $query->count();
        $users = $query->skip($offset)->take($limit)->get();

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

            $totalPrice = $totalServicePurchasedPrice + $totalProductPurchasedPrice;

            // Apply minimum total filter
            if ($request->has('min_total') && $totalPrice < $request->input('min_total')) {
                continue;
            }

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
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($usersWithProfiles)) < $total
            ]
        ]);
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
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-01-01", description="Start date for transaction filtering (YYYY-MM-DD)"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-01-07", description="End date for transaction filtering (YYYY-MM-DD)"),
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
            $startDate = $request->input('start_date') ? new DateTime($request->input('start_date')) : now()->startOfWeek();
            $endDate = $request->input('end_date') ? new DateTime($request->input('end_date')) : now()->endOfWeek();
            $endDate->modify('+1 day'); // Include end date
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $query = User::where('role', 'customer')->orderBy('id');
        $total = $query->count();
        $users = $query->skip($offset)->take($perPage)->get();

        $usersWithProfiles = [];

        foreach ($users as $user) {
            $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();
            $totalUsedMinutes = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'used')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('quantity');

            $totalServicePurchasedPrice = ServiceTransaction::where('user_id', $user->id)
                ->where('type', 'purchased')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get()
                ->sum(function ($transaction) {
                    $service = Service::find($transaction->service_id);
                    return $service ? $service->price : 0;
                });

            $totalProductPurchasedPrice = ProductTransaction::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
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
}
