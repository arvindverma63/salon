<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;
use App\Models\User_profile;
use App\Models\Service;
use App\Models\ServiceTransaction;
use App\Models\ProductTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\Password;
use DateTime;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     description="Register a new user and send an email verification link",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation","role"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123"),
     *             @OA\Property(property="role", type="string", example="customer"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful, please verify your email."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function register(Request $request)
    {
        // Validation logic
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,operator,customer'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Generate a unique verification token
        $verificationToken = bin2hex(random_bytes(30)); // Create a unique token

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'verification_token' => $verificationToken, // Set the verification token here
        ]);

        // Return response with user data
        return response()->json([
            'message' => 'Registration successful',
            'user' => $user // Return user data
        ], 201);
    }



    public function verifyEmail($token)
    {
        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid verification token.'], 400);
        }

        // Optionally, you can mark the user as verified and clear the token
        $user->verification_token = null; // Clear token
        $user->is_verified = true; // Assuming you have an 'is_verified' column
        $user->save();

        return response()->json(['message' => 'Email verified successfully.']);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login user",
     *     description="Login a user and return the JWT token",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Email not verified"
     *     )
     * )
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = JWTAuth::user();

        // if (!$user->hasVerifiedEmail()) {
        //     return response()->json(['error' => 'Email not verified'], 403);
        // }

        return $this->respondWithToken($token);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user",
     *     description="Invalidate the JWT token and log out the user",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out"
     *     )
     * )
     */
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * @OA\Post(
     *     path="/api/me",
     *     summary="Get authenticated user",
     *     description="Retrieve details of the authenticated user",
     *     tags={"Auth"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="User details"
     *     )
     * )
     */
    public function me()
    {
        return response()->json(JWTAuth::user());
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     summary="Refresh JWT token",
     *     description="Refresh and return a new JWT token",
     *     tags={"Auth"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed"
     *     )
     * )
     */
    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::refresh(JWTAuth::getToken()));
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/password/forgot",
     *     summary="Request a password reset link",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset link has been sent to your email.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed.")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'If your email exists in our system, a password reset link has been sent.'], 200);
        }

        try {
            // Generate a unique token for password reset
            $token = Str::random(60);

            // Check if the table exists and insert or update the token
            $reset = DB::table('password_resets')->updateOrInsert(
                ['email' => $request->email],
                ['token' => $token, 'created_at' => now()]
            );

            if (!$reset) {
                Log::error('Failed to insert or update password reset token for email: ' . $request->email);
                return response()->json(['message' => 'Something went wrong. Please try again later.'], 500);
            }

            // Send the reset password email
            Mail::to($request->email)->send(new PasswordResetMail($token));

            Log::info('Password reset email sent successfully to: ' . $request->email);

            return response()->json(['message' => 'If your email exists in our system, a password reset link has been sent.'], 200);
        } catch (\Exception $e) {
            // Log any exception
            Log::error('Error occurred during forgot password process: ' . $e->getMessage());
            return response()->json(['message' => 'Something went wrong. Please try again later.'], 500);
        }
    }



    /**
     * @OA\Post(
     *     path="/api/password/reset",
     *     summary="Reset the user's password",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="random-token"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="newpassword"),
     *             @OA\Property(property="password_confirmation", type="string", example="newpassword"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password has been reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password has been reset successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid token",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid token.")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid email.'], 404);
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        // Log the password reset for auditing
        \Log::info('Password reset for user: ' . $user->email);

        // Optionally send a notification email
        // Notification::route('mail', $user->email)->notify(new PasswordResetSuccess());

        // Return success response
        return response()->json(['message' => 'Password has been reset successfully.']);
    }



    public function getUserd(Request $request)
    {
        // Retrieve the filter parameters from the POST request body
        $startDate = $request->input('start_date') ? new DateTime($request->input('start_date')) : now()->startOfWeek();
        $endDate = $request->input('end_date') ? new DateTime($request->input('end_date')) : now()->endOfWeek();

        // Add one day to the end date
        $endDate->modify('+1 day');

        $locationIdFilter = $request->input('location_id');

        // Get all users with the customer role
        $users = User::where('role', 'customer')->get();

        // Initialize an empty array to store the results
        $dataByLocationAndWeek = [];

        // Loop through each user to get transaction details
        foreach ($users as $user) {
            // Retrieve the user's preferred location from user_profiles table
            $preferredLocation = DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->value('preferred_location');

            // Fetch the location ID from the locations table
            $locationId = $preferredLocation ? DB::table('locations')->where('id', $preferredLocation)->value('id') : null;

            // Determine the week of registration
            $registrationDate = $user->created_at;
            $registrationWeekNumber = (new DateTime($registrationDate))->format('W');

            // Initialize total spent variable
            $totalSpent = 0;

            // Calculate the spent from service transactions
            $serviceTransactions = ServiceTransaction::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            // Debugging: Log service transactions
            \Log::info('User ID: ' . $user->id . ' Service Transactions: ', $serviceTransactions->toArray());

            foreach ($serviceTransactions as $serviceTransaction) {
                $service = Service::find($serviceTransaction->service_id);
                $serviceSpent = $service ? $service->price : 0;
                $totalSpent += $serviceSpent;

                // Debugging: Log each service spent
                \Log::info('Service ID: ' . $serviceTransaction->service_id . ' Spent: ' . $serviceSpent);
            }

            // Calculate the spent from product transactions
            $productTransactions = ProductTransaction::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            // Debugging: Log product transactions
            \Log::info('User ID: ' . $user->id . ' Product Transactions: ', $productTransactions->toArray());

            foreach ($productTransactions as $productTransaction) {
                $product = Product::find($productTransaction->product_id);
                // Accumulate the total spent considering the quantity of products bought
                $productSpent = $product ? $product->price * $productTransaction->quantity : 0;
                $totalSpent += $productSpent;

                // Debugging: Log each product spent
                \Log::info('Product ID: ' . $productTransaction->product_id . ' Spent: ' . $productSpent);
            }

            // Skip the user if the location doesn't match the filter
            if ($locationIdFilter && $locationId != $locationIdFilter) {
                continue;
            }

            // Get the location name from the locations table
            $locationName = 'All';
            if ($locationId) {
                $location = DB::table('locations')->find($locationId);
                $locationName = $location ? $location->name : 'All';
            }

            // Check if this location and week are already initialized in the array
            if (!isset($dataByLocationAndWeek[$locationName][$registrationWeekNumber])) {
                $dataByLocationAndWeek[$locationName][$registrationWeekNumber] = [
                    'location_name' => $locationName,
                    'location_id' => $locationId,
                    'week_no' => $registrationWeekNumber,
                    'count' => 0,
                    'spent' => 0,
                    'total_registered_customers' => 0,
                    'total_customers_with_transactions' => 0,
                    'total_transactions' => 0,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'), // Automatically modified end date
                ];
            }

            // Increment the count of total registered customers for this location and week
            $dataByLocationAndWeek[$locationName][$registrationWeekNumber]['total_registered_customers'] += 1;

            // Only include users with transactions
            if ($totalSpent > 0) {
                $dataByLocationAndWeek[$locationName][$registrationWeekNumber]['total_customers_with_transactions'] += 1;

                // Add total spent to the corresponding location and week
                $dataByLocationAndWeek[$locationName][$registrationWeekNumber]['spent'] += $totalSpent;
                $dataByLocationAndWeek[$locationName][$registrationWeekNumber]['total_transactions'] += 1;

                // Debugging: Log total spent
                \Log::info('User ID: ' . $user->id . ' Total Spent: ' . $totalSpent);
            }
        }

        // Prepare the response data
        $responseData = [];
        foreach ($dataByLocationAndWeek as $location => $weeks) {
            foreach ($weeks as $weekData) {
                $responseData[] = $weekData;
            }
        }

        // Return the data as a JSON response
        return response()->json(['data' => $responseData]);
    }




/**
     * Get paginated list of users with their profiles and transaction totals
     *
     * @OA\Get(
     *     path="/api/getUser",
     *     summary="Get paginated users with profiles and transaction data",
     *     tags={"Users"},
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
     *                         @OA\Property(property="email", type="string")
     *                     ),
     *                     @OA\Property(property="profile", type="object",
     *                         @OA\Property(property="user_id", type="integer"),
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
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function getUser(Request $request)
    {
        $limit = $request->input('per_page', 15);
        $offset = $request->input('page', 0);

        $query = User::whereNot('role', 'customer')->orderBy('id');
        $total = $query->count();
        $users = $query->offset($offset)->take($limit)->get();

        Log::info("total get users : ",[$users]);
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
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($usersWithProfiles)) < $total
            ]
        ]);
    }



    public function customerAdd(Request $request)
    {
        // Validation logic
        $validator = Validator::make($request->all(), [
            'firstName' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|in:admin,operator,customer',
            'phone_number' => 'nullable|max:15',
            'address' => 'nullable|max:255',
            'preferred_location' => 'nullable|integer',
            'referred_by' => 'nullable|max:255',
            'gender' => 'nullable',
            'post_code' => 'nullable|max:10',
            'gdpr_sms_active' => 'nullable|boolean',
            'gdpr_email_active' => 'nullable|boolean',
            'dob' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Get the first validation error message
            $firstError = $validator->errors()->first();

            return response()->json([
                'errors' => $firstError
            ], 422);
        }

        // Start transaction to ensure both queries succeed
        DB::beginTransaction();

        try {
            // Generate a unique verification token
            $verificationToken = bin2hex(random_bytes(30));

            // Create the user
            $user = User::create([
                'name' => trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? '')),
                'email' => $request->email,
                'password' => $request->password ? Hash::make($request->password) : null,
                'role' => $request->role,
                'verification_token' => $verificationToken,
            ]);

            // Create the user profile
            $userProfile = User_profile::create([
                'user_id' => $user->id,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'preferred_location' => $request->preferred_location,
                'referred_by' => $request->referred_by,
                'gender' => $request->gender,
                'post_code' => $request->post_code,
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'dob' => $request->dob,
                'gdpr_sms_active' => $request->gdpr_sms_active ?? false,
                'gdpr_email_active' => $request->gdpr_email_active ?? false,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'user_profile' => $userProfile
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('User registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'errors' => 'Registration failed. Please try again later.'
            ], 500);
        }
    }
}
