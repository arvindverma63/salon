<?php

namespace App\Http\Controllers;

use App\Models\User_profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Location;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceTransaction;
use App\Models\ProductTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


/**
 * @OA\Info(title="User Profile API", version="1.0")
 */
class UserProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user-profiles",
     *     tags={"User Profiles"},
     *     summary="Get all user profiles",
     *     @OA\Response(
     *         response=200,
     *         description="A list of user profiles",
     *     ),
     * )
     */
    public function index()
    {
        $profiles = User_profile::all();
        return response()->json($profiles);
    }

    /**
     * @OA\Post(
     *     path="/api/user-profiles",
     *     tags={"User Profiles"},
     *     summary="Create a new user profile",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "email", "phone_number", "firstName", "lastName", "gender"},
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="lastName", type="string"),
     *             @OA\Property(property="gender", type="string"),
     *             @OA\Property(property="gdpr_sms_active", type="boolean"),
     *             @OA\Property(property="gdpr_email_active", type="boolean"),
     *             @OA\Property(property="referred_by", type="string"),
     *             @OA\Property(property="preferred_location", type="integer"),
     *             @OA\Property(property="avatar", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="active", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User profile created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="lastName", type="string"),
     *             @OA\Property(property="gender", type="string"),
     *             @OA\Property(property="gdpr_sms_active", type="boolean"),
     *             @OA\Property(property="gdpr_email_active", type="boolean"),
     *             @OA\Property(property="referred_by", type="string"),
     *             @OA\Property(property="preferred_location", type="integer"),
     *             @OA\Property(property="avatar", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="active", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'email' => 'nullable|email',
            'phone_number' => 'nullable|integer',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'gender' => 'nullable|string',
            'gdpr_sms_active' => 'boolean',
            'gdpr_email_active' => 'boolean',
            'referred_by' => 'nullable|string',
            'preferred_location' => 'nullable|integer',
            'avatar' => 'nullable|string',
            'active' => 'boolean',
            'available_balance' => 'integer'  // Fixed typo here
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Create the user profile
        $profile = User_profile::create($request->all());

        return response()->json($profile, 201);
    }


    /**
     * @OA\Get(
     *     path="/api/user-profiles/{id}",
     *     tags={"User Profiles"},
     *     summary="Get a single user profile",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User profile details",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User profile not found",
     *     )
     * )
     */
    public function show($id)
    {
        $profile = User_profile::where('user_id', $id)->get();

        // if (!$profile) {
        //     return response()->json(['error' => 'User profile not found'], 404);
        // }

        return response()->json($profile);
    }

    /**
     * @OA\Put(
     *     path="/api/user-profiles/{id}",
     *     tags={"User Profiles"},
     *     summary="Update a user profile",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="lastName", type="string"),
     *             @OA\Property(property="gender", type="string"),
     *             @OA\Property(property="gdpr_sms_active", type="boolean"),
     *             @OA\Property(property="gdpr_email_active", type="boolean"),
     *             @OA\Property(property="referred_by", type="string"),
     *             @OA\Property(property="preferred_location", type="integer"),
     *             @OA\Property(property="avatar", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="active", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User profile updated successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User profile not found",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        // Fetch the user profile based on the user_id (no foreign key relationship assumed)
        $profile = User_profile::where('user_id', $id)->first();

        if (!$profile) {
            return response()->json(['error' => 'User profile not found'], 404);
        }

        // Validate the incoming request (removed email validation)
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable',
            'firstName' => 'nullable|string',
            'lastName' => 'nullable|string',
            'gender' => 'nullable|string',
            'gdpr_sms_active' => 'boolean',
            'gdpr_email_active' => 'boolean',
            'referred_by' => 'nullable|string',
            'preferred_location' => 'nullable|integer',
            'avatar' => 'nullable|string',
            'active' => 'boolean',
            'available_balance' => 'nullable|integer',
            'role' => 'nullable|string',
            'dob' => 'nullable|string',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Update the email if provided (without checking for duplicates)
        if ($request->has('email')) {
            User::where('id', $id)->update(['email' => $request->email]);
        }

        // Update the user's role in the 'users' table if the role is provided
        if ($request->has('role')) {
            User::where('id', $id)->update(['role' => $request->role]);
        }

        // Update the name in the 'users' table if the firstName is provided
        if ($request->has('firstName')) {
            User::where('id', $id)->update(['name' => $request->firstName]);
        }

        // Update the profile with the validated data
        $profile->update($request->all());

        // Return the updated profile as a JSON response
        return response()->json($profile);
    }






    /**
     * @OA\Delete(
     *     path="/api/user-profiles/{id}",
     *     tags={"User Profiles"},
     *     summary="Delete a user profile",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User profile deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User profile not found",
     *     )
     * )
     */
    public function destroy($id)
    {
        $user = User::find($id); // Include soft-deleted users

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->delete(); // Permanently delete the user
        return response()->json(['message' => 'User permanently deleted successfully']);
    }

    public function getUserByLocation(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'preferred_location' => 'integer|nullable',
            'from' => 'date|nullable',
            'to' => 'date|nullable',
            'month' => 'integer|nullable'
        ]);

        // Start the query
        $query = User_profile::query();

        // Apply filters based on the request
        if ($request->has('preferred_location')) {
            $query->where('preferred_location', $request->preferred_location);
        }

        // Apply date filters if provided
        if ($request->has('from') && $request->has('to')) {
            // Convert to desired format if necessary
            $from = date('Y-m-d', strtotime($request->from));
            $to = date('Y-m-d', strtotime($request->to));

            $query->whereBetween('created_at', [$from, $to]);
        }

        // Apply month filter if provided
        if ($request->has('month')) {
            // Adjust year based on current date or any logic you want to use
            $year = date('Y'); // You can modify this logic as needed

            $query->whereYear('created_at', $year)
                ->whereMonth('created_at', $request->month);
        }

        // Get the filtered users
        $users = $query->get();

        return response()->json($users);
    }

    /**
     * @OA\Get(
     *     path="/api/stats/{id}",
     *     summary="Get statistics for all locations or a specific location",
     *     description="Retrieves statistics including counts of locations, customers, products, services, and transaction totals for the current day. If id is 0, returns global stats; otherwise, returns stats for the specified location.",
     *     operationId="getStats",
     *     tags={"Statistics"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Location ID (0 for global stats, or a specific location ID)",
     *         required=true,
     *         @OA\Schema(type="integer", example=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 oneOf={
     *                     @OA\Schema(
     *                         @OA\Property(property="locations", type="integer", example=5),
     *                         @OA\Property(property="customers", type="integer", example=100),
     *                         @OA\Property(property="products", type="integer", example=50),
     *                         @OA\Property(property="services", type="integer", example=20),
     *                         @OA\Property(property="serviceTransactionTotalToday", type="integer", example=150),
     *                         @OA\Property(property="productTransactionTotalToday", type="number", format="float", example=250.50),
     *                         @OA\Property(property="customerServiceToday", type="integer", example=30)
     *                     ),
     *                     @OA\Schema(
     *                         @OA\Property(property="location", type="string", example="Location Name"),
     *                         @OA\Property(property="customers", type="integer", example=20),
     *                         @OA\Property(property="products", type="integer", example=10),
     *                         @OA\Property(property="services", type="integer", example=5),
     *                         @OA\Property(property="serviceTransactionTotalToday", type="integer", example=30),
     *                         @OA\Property(property="productTransactionTotalToday", type="number", format="float", example=75.25),
     *                         @OA\Property(property="customerServiceToday", type="integer", example=8)
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Location not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Location not found")
     *         )
     *     )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/stats/{id}",
     *     summary="Get statistics for a specific location or globally",
     *     description="Retrieves statistics including counts of locations, customers, products, services, and transaction totals. If id=0, global stats are returned; otherwise, stats for the specified location are returned.",
     *     operationId="getStats",
     *     tags={"Stats"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Location ID (use 0 for global stats)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 oneOf={
     *                     @OA\Schema(
     *                         @OA\Property(property="locations", type="integer", example=5),
     *                         @OA\Property(property="customers", type="integer", example=100),
     *                         @OA\Property(property="products", type="integer", example=50),
     *                         @OA\Property(property="services", type="integer", example=20),
     *                         @OA\Property(property="serviceTransactionTotalToday", type="integer", example=150),
     *                         @OA\Property(property="productTransactionTotalToday", type="number", format="float", example=250.50),
     *                         @OA\Property(property="customerServiceToday", type="integer", example=30)
     *                     ),
     *                     @OA\Schema(
     *                         @OA\Property(property="location", type="string", example="Main Branch"),
     *                         @OA\Property(property="customers", type="integer", example=20),
     *                         @OA\Property(property="products", type="integer", example=10),
     *                         @OA\Property(property="services", type="integer", example=5),
     *                         @OA\Property(property="serviceTransactionTotalToday", type="integer", example=30),
     *                         @OA\Property(property="productTransactionTotalToday", type="number", format="float", example=75.25),
     *                         @OA\Property(property="customerServiceToday", type="integer", example=8)
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Location not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Location not found")
     *         )
     *     )
     * )
     */
    public function stats($id)
    {
        // Determine if we're fetching global or location-specific stats
        $isGlobal = $id == 0;
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        try {
            // Execute the query using DB::selectOne
            $stats = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM locations) AS locations_count,
                -- Count customers based on preferred_location in user_profiles
                (
                    SELECT COUNT(DISTINCT u.id)
                    FROM users u
                    JOIN user_profiles up ON up.user_id = u.id
                    WHERE u.role = ?
                    AND (? OR up.preferred_location = ?)
                ) AS customers_count,
                (SELECT COUNT(*) FROM products) AS products_count,
                (SELECT COUNT(*) FROM services) AS services_count,
                COALESCE((
                    SELECT SUM(st.quantity)
                    FROM service_transactions st
                    WHERE st.type = ?
                    AND st.created_at >= ?
                    AND st.created_at < ?
                    AND (? OR st.location = ?)
                ), 0) AS service_transaction_total_today,
                COALESCE((
                    SELECT SUM(s.price)
                    FROM service_transactions st
                    JOIN services s ON s.id = st.service_id
                    WHERE st.type = ?
                    AND st.created_at >= ?
                    AND st.created_at < ?
                    AND (? OR st.location = ?)
                ), 0) AS service_transaction_price_total_today,
                COALESCE((
                    SELECT SUM(pt.quantity * p.price)
                    FROM product_transaction pt
                    JOIN products p ON p.id = pt.product_id
                    WHERE pt.created_at >= ?
                    AND pt.created_at < ?
                    AND (? OR pt.location_id = ?)
                ), 0) AS product_transaction_total_today,
                (SELECT COUNT(DISTINCT st.user_id)
                 FROM service_transactions st
                 WHERE st.type = ?
                 AND st.created_at >= ?
                 AND st.created_at < ?
                 AND (? OR st.location = ?)
                ) AS total_unique_customers_today
        ", [
                'customer',
                $isGlobal,
                $id, // for customers_count
                'used',
                $todayStart,
                $todayEnd,
                $isGlobal,
                $id, // for service_transaction_total_today
                'purchased',
                $todayStart,
                $todayEnd,
                $isGlobal,
                $id, // for service_transaction_price_total_today
                $todayStart,
                $todayEnd,
                $isGlobal,
                $id, // for product_transaction_total_today
                'used',
                $todayStart,
                $todayEnd,
                $isGlobal,
                $id // for total_unique_customers_today
            ]);
        } finally {
            // Close the database connection dynamically
            $connectionName = DB::getDefaultConnection();
            DB::disconnect($connectionName);
        }

        // Prepare the response based on whether it's global or location-specific
        if ($isGlobal) {
            $data = [
                'locations' => (int)$stats->locations_count,
                'customers' => (int)$stats->customers_count,
                'products' => (int)$stats->products_count,
                'services' => (int)$stats->services_count,
                'serviceTransactionTotalToday' => (int)$stats->service_transaction_total_today,
                'productTransactionTotalToday' => (float)($stats->product_transaction_total_today + $stats->service_transaction_price_total_today),
                'customerServiceToday' => (int)$stats->total_unique_customers_today,
            ];
        } else {
            // Check if the location exists
            $location = Location::find($id);
            if (!$location) {
                return response()->json(['message' => 'Location not found'], 404);
            }

            // Close the connection after the Location query
            $connectionName = (new Location())->getConnectionName();
            DB::disconnect($connectionName);

            $data = [
                'location' => $location->name,
                'customers' => (int)$stats->customers_count,
                'products' => (int)$stats->products_count,
                'services' => (int)$stats->services_count,
                'serviceTransactionTotalToday' => (int)$stats->service_transaction_total_today,
                'productTransactionTotalToday' => (float)($stats->product_transaction_total_today + $stats->service_transaction_price_total_today),
                'customerServiceToday' => (int)$stats->total_unique_customers_today,
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function clearData()
    {
        DB::beginTransaction();

        try {
            // Temporarily disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Truncate tables
            // Service::truncate();
            ServiceTransaction::truncate();
            // Product::truncate();
            ProductTransaction::truncate();

            // Delete users with 'customer' role
            User::where('role', 'customer')->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Log error for debugging
            Log::error("Error clearing data: {$e->getMessage()}");
            throw $e;
        } finally {
            // Ensure foreign key checks are re-enabled
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
