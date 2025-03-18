<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Laptop"),
 *     @OA\Property(property="brand", type="string", example="Dell"),
 *     @OA\Property(property="description", type="string", example="A lightweight laptop"),
 *     @OA\Property(property="price", type="number", format="float", example=999.99),
 *     @OA\Property(property="image", type="string", format="uri", example="http://example.com/image.jpg"),
 *     @OA\Property(property="type", type="string", example="Product"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProductController extends Controller
{
   /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="Get all products",
     *     description="Retrieve a list of all products",
     *     tags={"Products"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Products not found"
     *     )
     * )
     */
    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }

    /**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Your API Title",
 *         version="1.0.0",
 *     ),
 *     @OA\Components(
 *         @OA\SecurityScheme(
 *             securityScheme="bearerAuth",
 *             type="http",
 *             scheme="bearer",
 *             bearerFormat="JWT",
 *             description="Enter your JWT token here"
 *         )
 *     )
 * )
 */
    /**
 * @OA\Post(
 *     path="/api/products",
 *     summary="Create a new product",
 *     description="Create a new product with provided details, including an image upload",
 *     tags={"Products"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"name", "price", "type"},
 *                 @OA\Property(property="name", type="string", example="Laptop"),
 *                 @OA\Property(property="brand", type="string", example="Dell"),
 *                 @OA\Property(property="description", type="string", example="A lightweight laptop"),
 *                 @OA\Property(property="price", type="number", format="float", example="999.99"),
 *                 @OA\Property(property="image", type="string", format="binary", description="Image file to upload"),
 *                 @OA\Property(property="type", type="string", example="Product")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Product created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Product created successfully"),
 *             @OA\Property(property="product", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="Laptop"),
 *                 @OA\Property(property="brand", type="string", example="Dell"),
 *                 @OA\Property(property="description", type="string", example="A lightweight laptop"),
 *                 @OA\Property(property="price", type="number", format="float", example="999.99"),
 *                 @OA\Property(property="image", type="string", example="products/laptop.jpg"),
 *                 @OA\Property(property="type", type="string", example="Product")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid input",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Invalid input")
 *         )
 *     )
 * )
 */

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'brand' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'stock01'=>'integer',
            'stock02'=>'integer',
            'stock03'=>'integer',
        ]);

        // Handle file upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'name' => $request->name,
            'brand' => $request->brand,
            'description' => $request->description,
            'price' => $request->price,
            'image' => $imagePath,
            'stock01'=>$request->stock01,
            'stock02'=>$request->stock02,
            'stock03'=>$request->stock03,
        ]);

        return response()->json(['message' => 'Product created successfully', 'product' => $product], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get a product by ID",
     *     description="Retrieve the details of a specific product by ID",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product details",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Update a product",
     *     description="Update the details of an existing product",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Laptop"),
     *             @OA\Property(property="brand", type="string", example="Dell"),
     *             @OA\Property(property="description", type="string", example="A lightweight laptop"),
     *             @OA\Property(property="price", type="number", format="float", example="999.99"),
     *             @OA\Property(property="type", type="string", example="Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $request->validate([
            'name' => 'string|nullable',
            'brand' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'stock01'=>'integer|nullable',
            'stock02'=>'integer|nullable',
            'stock03'=>'integer|nullable',
        ]);

        // Handle file upload
        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'name' => $request->name ?? $product->name,
            'brand' => $request->brand ?? $product->brand,
            'description' => $request->description ?? $product->description,
            'price' => $request->price ?? $product->price,
            'image' => $imagePath,
            'stock01'=>$request->stock01,
            'stock02'=>$request->stock02,
            'stock03'=>$request->stock03,
        ]);

        return response()->json(['message' => 'Product updated successfully', 'product' => $product]);
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Delete a product",
     *     description="Permanently delete a product by ID",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Delete the product image if it exists
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
