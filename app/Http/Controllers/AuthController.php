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
use App\Models\Location;
use App\Models\User_profile;
use App\Models\Service;
use App\Models\ServiceTransaction;
use App\Models\ProductTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\Password;
use DateTime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;
use Symfony\Component\HttpKernel\Profiler\Profile;

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

        // $profile = User_profile::where('user_id',$user->id)->first();
        // $location = Location::find($profile->preferred_location);
        // $data = [
        //     'location'
        // ]


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
        Log::info('Password reset for user: ' . $user->email);

        // Optionally send a notification email
        // Notification::route('mail', $user->email)->notify(new PasswordResetSuccess());

        // Return success response
        return response()->json(['message' => 'Password has been reset successfully.']);
    }


    /**
     * Get customer transaction data by location and week with page-based pagination
     *
     * @OA\Post(
     *     path="/api/getUserd",
     *     summary="Get customer transaction data by location and registration week with page-based pagination",
     *     tags={"Customers"},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-01-01", description="Start date for transaction filtering (YYYY-MM-DD)"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-01-07", description="End date for transaction filtering (YYYY-MM-DD)"),
     *             @OA\Property(property="location_id", type="integer", example=1, description="Filter by specific location ID"),
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
     *                     @OA\Property(property="location_name", type="string", example="All"),
     *                     @OA\Property(property="location_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="week_no", type="string", example="01"),
     *                     @OA\Property(property="count", type="integer", example=0),
     *                     @OA\Property(property="spent", type="number", example=150.75),
     *                     @OA\Property(property="total_registered_customers", type="integer", example=10),
     *                     @OA\Property(property="total_customers_with_transactions", type="integer", example=5),
     *                     @OA\Property(property="total_transactions", type="integer", example=8),
     *                     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2024-01-08")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
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
    public function getUserd(Request $request)
    {
        // Retrieve filter parameters from POST body
        try {
            $startDate = $request->input('start_date') ? new DateTime($request->input('start_date')) : now()->startOfWeek();
            $endDate = $request->input('end_date') ? new DateTime($request->input('end_date')) : now()->endOfWeek();
            $endDate->modify('+1 day');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        $locationIdFilter = $request->input('location_id');
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage; // Calculate offset based on page

        // Fetch all locations once
        $locations = DB::table('locations')->pluck('name', 'id')->toArray();

        // Fetch user profiles with preferred locations in bulk
        $profiles = DB::table('user_profiles')
            ->select('user_id', 'preferred_location')
            ->whereIn('user_id', User::where('role', 'customer')->pluck('id'))
            ->get()
            ->pluck('preferred_location', 'user_id')
            ->toArray();

        // Fetch all services and products in bulk
        $services = DB::table('services')->pluck('price', 'id')->toArray();
        $products = DB::table('products')->pluck('price', 'id')->toArray();

        // Base user query
        $users = User::where('role', 'customer')
            ->select('id', 'created_at')
            ->get();

        // Fetch transactions in bulk within date range
        $serviceTransactions = ServiceTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', 'service_id')
            ->get()
            ->groupBy('user_id');

        $productTransactions = ProductTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', 'product_id', 'quantity')
            ->get()
            ->groupBy('user_id');

        // Initialize result array
        $dataByLocationAndWeek = [];

        foreach ($users as $user) {
            $userId = $user->id;
            $preferredLocation = $profiles[$userId] ?? null;
            $locationId = $preferredLocation && isset($locations[$preferredLocation]) ? $preferredLocation : null;

            // Apply location filter early
            if ($locationIdFilter && $locationId != $locationIdFilter) {
                continue;
            }

            $registrationWeekNumber = (new DateTime($user->created_at))->format('W');
            $locationName = $locationId ? ($locations[$locationId] ?? 'All') : 'All';

            // Initialize location-week entry
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
                    'end_date' => $endDate->format('Y-m-d'),
                ];
            }

            $entry = &$dataByLocationAndWeek[$locationName][$registrationWeekNumber];
            $entry['total_registered_customers'] += 1;

            $totalSpent = 0;
            $transactionCount = 0;

            // Process service transactions
            if (isset($serviceTransactions[$userId])) {
                foreach ($serviceTransactions[$userId] as $transaction) {
                    $serviceSpent = $services[$transaction->service_id] ?? 0;
                    $totalSpent += $serviceSpent;
                    $transactionCount++;
                    Log::info('User ID: ' . $userId . ' Service ID: ' . $transaction->service_id . ' Spent: ' . $serviceSpent);
                }
            }

            // Process product transactions
            if (isset($productTransactions[$userId])) {
                foreach ($productTransactions[$userId] as $transaction) {
                    $productSpent = ($products[$transaction->product_id] ?? 0) * $transaction->quantity;
                    $totalSpent += $productSpent;
                    $transactionCount++;
                    Log::info('User ID: ' . $userId . ' Product ID: ' . $transaction->product_id . ' Spent: ' . $productSpent);
                }
            }

            if ($totalSpent > 0) {
                $entry['total_customers_with_transactions'] += 1;
                $entry['spent'] += $totalSpent;
                $entry['total_transactions'] += $transactionCount;
                Log::info('User ID: ' . $userId . ' Total Spent: ' . $totalSpent);
            }
        }

        // Flatten the nested array
        $responseData = [];
        foreach ($dataByLocationAndWeek as $location => $weeks) {
            foreach ($weeks as $weekData) {
                $responseData[] = $weekData;
            }
        }

        // Apply page-based pagination
        $total = count($responseData);
        $lastPage = ceil($total / $perPage);
        $paginatedData = array_slice($responseData, $offset, $perPage);
        $from = $offset + 1;
        $to = $offset + count($paginatedData);

        return response()->json([
            'data' => $paginatedData,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to
            ]
        ]);
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

        Log::info("total get users : ", [$users]);
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

            Log::error('User registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'errors' => 'Registration failed. Please try again later.'
            ], 500);
        }
    }
}
