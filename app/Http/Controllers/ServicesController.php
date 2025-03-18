<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServiceTransaction;

class ServicesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/services",
     *     summary="Get list of all services",
     *     tags={"Services"},
     *     description="Retrieve a list of all services.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function index()
    {
        $services = Service::all();
        return response()->json($services);
    }

    /**
     * @OA\Post(
     *     path="/api/services",
     *     summary="Create a new service",
     *     tags={"Services"},
     *     description="Store a new service in the database.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="service_name", type="string", example="Haircut"),
     *             @OA\Property(property="minutesAvailable", type="string", example="30"),
     *             @OA\Property(property="price", type="integer", example=20)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Service created successfully"
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
            'serviceName' => 'required|string',
            'minutesAvailable' => 'integer',
            'price' => 'required',
        ]);

        $service = Service::create([
            'serviceName' => $request->serviceName,
            'minutesAvailable' => $request->minutesAvailable,
            'price' => $request->price,
        ]);

        return response()->json(['message' => 'Service created successfully', 'service' => $service], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/services/{id}",
     *     summary="Get a specific service",
     *     tags={"Services"},
     *     description="Retrieve the details of a specific service.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service details"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service not found"
     *     )
     * )
     */
    public function show($id)
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        return response()->json($service);
    }

    /**
     * @OA\Put(
     *     path="/api/services/{id}",
     *     summary="Update an existing service",
     *     tags={"Services"},
     *     description="Update the details of an existing service.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="service_name", type="string", example="Haircut"),
     *             @OA\Property(property="minutesAvailable", type="string", example="30"),
     *             @OA\Property(property="price", type="integer", example=20)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
{
    $service = Service::find($id);

    if (!$service) {
        return response()->json(['error' => 'Service not found'], 404);
    }

    $request->validate([
        'serviceName' => 'string',
        'minutesAvailable' => 'string',
        'price' => 'nullable',
    ]);

    $service->update([
        'serviceName' => $request->serviceName ?? $service->serviceName, // Fixed typo
        'minutesAvailable' => $request->minutesAvailable ?? $service->minutesAvailable,
        'price' => $request->price ?? $service->price,
    ]);

    return response()->json(['message' => 'Service updated successfully', 'service' => $service]);
}


    /**
     * @OA\Delete(
     *     path="/api/services/{id}",
     *     summary="Delete a specific service",
     *     tags={"Services"},
     *     description="Permanently delete a service from the database.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        // Permanent delete
        $service->forceDelete();
        return response()->json(['message' => 'Service deleted successfully']);
    }

    public function getAvailableService($user_id) {
        // Fetch purchased transactions
        $transactionBuy = ServiceTransaction::where('user_id', $user_id)
            ->where('type', 'purchased')
            ->get();
    
        // Fetch used transactions
        $transactionUsed = ServiceTransaction::where('user_id', $user_id)
            ->where('type', 'used')
            ->get();
    
        // Extract used service IDs
        $usedServiceIds = $transactionUsed->pluck('service_id')->toArray();
        $result = [];
    
        foreach ($transactionBuy as $buy) {
            // Check if the service has not been used
            if (in_array($buy->service_id, $usedServiceIds)) {
                $used = $transactionUsed->where('service_id', $buy->service_id)->first();
                if ($used != null) {
                    $buy->service_id = 0;
                    $used->service_id = 0;
                    $usedServiceIds = $transactionUsed->pluck('service_id')->toArray();
                }
            } else {
                // Fetch service details
                $service = Service::where('id', $buy->service_id)->first();
    
                // Check if the service exists before accessing its properties
                if ($service && $buy->service_id != 0) {
                    $result[] = [
                        'service_id' => $buy->service_id,
                        'quantity' => $buy->quantity,
                        'serviceName' => $service->serviceName, // Only access if $service is not null
                    ];
                }
            }
        }
    
        return response()->json($result); // Return the available services as JSON
    }
    

}
