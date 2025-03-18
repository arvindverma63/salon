<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductTransaction;
use App\Models\Service;
use App\Models\ServiceTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Get paginated list of customers with their profiles and transaction totals
     *
     * @OA\Get(
     *     path="/api/customers",
     *     summary="Get paginated list of customers with their transaction data",
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
     *                         @OA\Property(property="user_id", type="integer"),
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
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true, example="http://example.com/api/customers?page=2"),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true, example=null)
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
    public function getAllCustomers()
    {
        $users = User::where('role', 'customer')->paginate(15);

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
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl()
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
     *         name="first_name",
     *         in="query",
     *         description="Search by customer's first name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
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
     *                         @OA\Property(property="user_id", type="integer"),
     *                         @OA\Property(property="first_name", type="string", nullable=true),
     *                         @OA\Property(property="last_name", type="string", nullable=true),
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
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true)
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
        $query = User::where('role', 'customer')
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id');

        // Apply search filters
        if ($request->has('first_name')) {
            $query->where('user_profiles.first_name', 'like', '%' . $request->input('first_name') . '%');
        }

        if ($request->has('last_name')) {
            $query->where('user_profiles.last_name', 'like', '%' . $request->input('last_name') . '%');
        }

        if ($request->has('phone_number')) {
            $query->where('user_profiles.phone_number', 'like', '%' . $request->input('phone_number') . '%');
        }

        if ($request->has('email')) {
            $query->where('users.email', 'like', '%' . $request->input('email') . '%');
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $users = $query->select('users.*')->paginate($perPage);

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

            // Apply minimum total filter if provided
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
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl()
            ]
        ]);
    }
}
