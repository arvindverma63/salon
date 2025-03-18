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

    public function stats($id)
{
    if ($id == 0) {
        // Global Statistics
        $locations = Location::count();
        $customers = User::where('role', 'customer')->count();
        $products = Product::count();
        $services = Service::count();

        // Today's total quantities and values for all purchased service transactions (location does not matter)
        $serviceTransactionTotalToday = ServiceTransaction::where('created_at', '>=', now()->startOfDay())
            ->where('type', 'used')
            ->sum('quantity');

            $serviceTransactionPriceTotalToday = ServiceTransaction::where('created_at', '>=', now()->startOfDay())
            ->where('type', 'purchased')
            ->get()
            ->sum(fn($transaction) => Service::find($transaction->service_id)->price ?? 0);

        // Today's total quantities and values for product transactions
        $productIds = ProductTransaction::where('created_at', '>=', now()->startOfDay())->pluck('product_id');
        $productsData = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $productTransactionTotalToday = ProductTransaction::where('created_at', '>=', now()->startOfDay())
            ->get()
            ->sum(fn($transaction) => ($productsData[$transaction->product_id]->price ?? 0) * $transaction->quantity);

        // Daily unique customer service count for all locations (type = 'used')
        $totalUniqueCustomersToday = ServiceTransaction::where('type', 'used')
            ->where('created_at', '>=', now()->startOfDay())
            ->distinct('user_id')
            ->count('user_id');

        // Prepare the response data
        $data = [
            'locations' => $locations,
            'customers' => $customers,
            'products' => $products,
            'services' => $services,
            'serviceTransactionTotalToday' => $serviceTransactionTotalToday,
            'productTransactionTotalToday' => $productTransactionTotalToday + $serviceTransactionPriceTotalToday,
            'customerServiceToday' => $totalUniqueCustomersToday,
        ];
    } else {
        // Location-specific Statistics
        $location = Location::find($id);
        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $customers = User::where('role', 'customer')
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->where('user_profiles.preferred_location', $id)
            ->count();

        $products = ProductTransaction::where('location_id', $id)->distinct('product_id')->count('product_id');
        $services = ServiceTransaction::where('location', $id)->distinct('service_id')->count('service_id');

        // Daily totals for service transactions at the specific location
        $serviceTransactionTotalToday = ServiceTransaction::where('created_at', '>=', now()->startOfDay())
            ->where('location', $id)
            ->where('type', 'used')
            ->sum('quantity');

            $serviceTransactionPriceTotalToday = ServiceTransaction::where('created_at', '>=', now()->startOfDay())
            ->where('location', $id)
            ->where('type', 'purchased')
            ->get()
            ->sum(fn($transaction) => Service::find($transaction->service_id)->price ?? 0);


        // Today's product totals at the specific location
        $productIds = ProductTransaction::where('created_at', '>=', now()->startOfDay())
            ->where('location_id', $id)
            ->pluck('product_id');
        $productsData = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $productTransactionTotalToday = ProductTransaction::where('created_at', '>=', now()->startOfDay())
            ->where('location_id', $id)
            ->get()
            ->sum(fn($transaction) => ($productsData[$transaction->product_id]->price ?? 0) * $transaction->quantity);

        // Unique customer service count for the specific location
        $totalUniqueCustomersToday = ServiceTransaction::where('type', 'used')
            ->where('created_at', '>=', now()->startOfDay())
            ->where('location', $id)
            ->distinct('user_id')
            ->count('user_id');

        // Prepare the response data
        $data = [
            'location' => $location->name,
            'customers' => $customers,
            'products' => $products,
            'services' => $services,
            'serviceTransactionTotalToday' => $serviceTransactionTotalToday,
            'productTransactionTotalToday' => $productTransactionTotalToday + $serviceTransactionPriceTotalToday,
            'customerServiceToday' => $totalUniqueCustomersToday,
        ];
    }

    return response()->json(['data' => $data]);
}



    


public function clearData() {
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
        \Log::error("Error clearing data: {$e->getMessage()}");
        throw $e;
    } finally {
        // Ensure foreign key checks are re-enabled
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}





}
