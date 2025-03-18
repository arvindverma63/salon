<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\File;
use App\Models\User_profile;
use App\Models\ServiceTransaction;
use App\Models\ProductTransaction;

class LocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/locations",
     *     summary="Get list of all locations",
     *     tags={"Locations"},
     *     description="Retrieve a list of all locations.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function index()
    {
        $locations = Location::all();
        return response()->json($locations);
    }

    /**
     * @OA\Post(
     *     path="/api/locations",
     *     summary="Create a new location",
     *     tags={"Locations"},
     *     description="Store a new location in the database.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Main Office"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="city", type="string", example="Anytown"),
     *             @OA\Property(property="phone_number", type="string", example="123-456-7890"),
     *             @OA\Property(property="post_code", type="string", example="12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Location created successfully"
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
            'name' => 'unique:locations',
            'address' => 'nullable',
            'city' => 'nullable',
            'phone_number' => 'nullable',
            'post_code' => 'nullable|unique:locations',
            'location_id' => 'nullable',
        ]);

        $location = Location::create($request->all());

        return response()->json(['message' => 'Location created successfully', 'location' => $location], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/locations/{id}",
     *     summary="Get a specific location",
     *     tags={"Locations"},
     *     description="Retrieve the details of a specific location.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the location to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location details"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Location not found"
     *     )
     * )
     */
    public function show($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        return response()->json($location);
    }

    /**
     * @OA\Put(
     *     path="/api/locations/{id}",
     *     summary="Update an existing location",
     *     tags={"Locations"},
     *     description="Update the details of an existing location.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the location to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Main Office"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="city", type="string", example="Anytown"),
     *             @OA\Property(property="phone_number", type="string", example="123-456-7890"),
     *             @OA\Property(property="post_code", type="string", example="12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Location not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        $request->validate([
            'name' => 'unique:locations,name,' . $location->id,
            'address' => 'nullable',
            'city' => 'nullable',
            'phone_number' => 'nullable',
            'post_code' => 'nullable|unique:locations,post_code,' . $location->id,
            'location_id'=>'nullable',
            'isActive'=>'nullable',
        ]);

        $location->update($request->all());

        return response()->json(['message' => 'Location updated successfully', 'location' => $location]);
    }

    /**
     * @OA\Delete(
     *     path="/api/locations/{id}",
     *     summary="Delete a specific location",
     *     tags={"Locations"},
     *     description="Permanently delete a location from the database.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the location to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Location deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Location not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        // Find the location by ID
        $location = Location::find($id);

        // Check if the location exists
        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        // Check if the location exists in the User_profile table
        $userExists = User_profile::where('preferred_location', $id)->exists();
        if ($userExists) {
            return response()->json(['message' => 'Location already exists in the user profile table'], 400);
        }

        // Check if the location exists in the ServiceTransaction table
        $serviceTransactionExists = ServiceTransaction::where('location', $id)->exists();
        if ($serviceTransactionExists) {
            return response()->json(['message' => 'Location already exists in the service transaction table'], 400);
        }

        // Check if the location exists in the ProductTransaction table
        $productTransactionExists = ProductTransaction::where('location_id', $id)->exists();
        if ($productTransactionExists) {
            return response()->json(['message' => 'Location already exists in the product transaction table'], 400);
        }

        // Delete the location
        $location->forceDelete();

        // Return a success response
        return response()->json(['message' => 'Location deleted successfully']);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
        ]);

        // Generate the QR code and save it as a PNG file
        $imagePath = public_path('qrcodes');
        if (!File::exists($imagePath)) {
            File::makeDirectory($imagePath, 0755, true);
        }

        $filename = 'qrcode_' . time() . '.png';
        $filePath = $imagePath . '/' . $filename;

        QrCode::format('png')->size(200)->generate($request->text, $filePath);

        // Return the URL to the generated QR code image
        $imageUrl = asset('qrcodes/' . $filename);
        return response()->json(['image_url' => $imageUrl]);
    }
}
