<?php

namespace App\Http\Controllers\Admin;

use App\Models\Products;
use App\Models\Categories;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\ProductSettings;
use App\Http\Controllers\Controller;

class ProductsController extends Controller
{
    public function index()
    {
        $categories = Categories::all();

        return view('admin.products.index', compact('categories'));
    }

    public function create()
    {
        $categories = Categories::all();

        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = request()->validate([
            'name' => 'required',
            'description' => 'required|string|min:10',
            'price' => 'required',
            'category_id' => 'required|integer',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:5242',
        ]);
        if ($request->get('no_image')) {
            $data['image'] = 'null';
        } else {
            $imageName = time() . $request->get('category_id') . '.' . $request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            $data['image'] = '/images/' . $imageName;
        }
        $product = Products::create($data);

        return redirect()->route('admin.products.edit', $product->id)->with('success', 'Product created successfully');
    }

    public function edit(Products $product)
    {
        $categories = Categories::all();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Products $product)
    {
        $data = request()->validate([
            'name' => 'required',
            'description' => 'required|string|min:10',
            'price' => 'required',
            'category_id' => 'required|integer',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:5242',
        ]);

        if ($request->hasFile('image') && !$request->get('no_image')) {
            $imageName = time() . '-' . $product->id . '.' . $request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            $data['image'] = '/images/' . $imageName;
            if (file_exists(public_path() . $product->image)) {
                $image = unlink(public_path() . $product->image);
                if (!$image) {
                    error_log('Failed to delete image: ' . public_path() . $product->image);
                }
            }
        }

        if ($request->get('no_image')) {
            $data['image'] = 'null';
        }
        $product->update($data);

        return redirect()->route('admin.products.edit', $product->id)->with('success', 'Product updated successfully');
    }

    public function destroy(Products $product)
    {
        $product->delete();

        return redirect()->route('admin.products')->with('success', 'Product deleted successfully');
    }

    public function extension(Products $product)
    {
        $extensions = Extensions::where('type', 'server')->where('enabled', true)->get();
        if ($product->server_id != null) {
            $server = Extensions::findOrFail($product->server_id);
            if (!file_exists(base_path('app/Extensions/Servers/' . $server->name . '/index.php'))) {
                $server = null;
                $extension = null;

                return view('admin.products.extension', compact('product', 'extensions', 'server', 'extension'))->with('error', 'Extension not found');
            }
            include_once base_path('app/Extensions/Servers/' . $server->name . '/index.php');
            $extension = new \stdClass();
            $function = $server->name . '_getProductConfig';
            $extension2 = json_decode(json_encode($function()));
            $extension->productConfig = $extension2;
            $extension->name = $server->name;
        } else {
            $server = null;
            $extension = null;
        }

        return view('admin.products.extension', compact('product', 'extensions', 'server', 'extension'));
    }

    public function extensionUpdate(Request $request, Products $product)
    {
        $data = request()->validate([
            'server_id' => 'required|integer',
        ]);
        // Check if only the server has been changed
        if ($product->server_id != $request->input('server_id')) {
            // Delete all product settings
            ProductSettings::where('product_id', $product->id)->delete();
            $product->update($data);
            return redirect()->route('admin.products.extension', $product->id)->with('success', 'Server changed successfully');
        }

        include_once base_path('app/Extensions/Servers/' . $product->server()->get()->first()->name . '/index.php');
        $extension = new \stdClass();
        $function = $product->server()->get()->first()->name . '_getProductConfig';
        $extension2 = json_decode(json_encode($function()));
        $extension->productConfig = $extension2;
        foreach ($extension->productConfig as $config) {
            $config->required = isset($config->required) ? $config->required : false;
            if ($config->required && !$request->input($config->name)) {
                return redirect()->route('admin.products.extension', $product->id)->with('error', 'Please fill in all required fields');
            }
            ProductSettings::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'name' => $config->name,
                    'extension' => $product->server()->get()->first()->id,
                ],
                [
                    'product_id' => $product->id,
                    'name' => $config->name,
                    'value' => $request->input($config->name),
                    'extension' => $product->server()->get()->first()->id,
                ]
            );
        }

        return redirect()->route('admin.products.extension', $product->id)->with('success', 'Product updated successfully');
    }

    public function extensionExport(Products $product)
    {
        $server = Extensions::findOrFail($product->server_id);
        if (!file_exists(base_path('app/Extensions/Servers/' . $server->name . '/index.php'))) {
            $server = null;
            $extension = null;

            return view('admin.products.extension', compact('product', 'extensions', 'server', 'extension'))->with('error', 'Extension not found');
        }
        include_once base_path('app/Extensions/Servers/' . $server->name . '/index.php');
        $extension = new \stdClass();
        $function = $server->name . '_getProductConfig';
        $extension2 = json_decode(json_encode($function()));
        $extension->productConfig = $extension2;
        $extension->name = $server->name;

        $productSettings = ProductSettings::where('product_id', $product->id)->get();
        $settings = [];
        $settings['!NOTICE!'] = 'This file was generated by Paymenter. Do not edit this file manually.';
        $settings['server'] = $server->name;

        foreach ($extension->productConfig as $config) {
            $productSettings2 = $productSettings->where('name', $config->name)->first();
            if ($productSettings2) {
                if (empty($productSettings2->value)) {
                    if ($config->type == 'text')
                        $settings['config'][$config->name] = '';
                    else if ($config->type == 'number')
                        $settings['config'][$config->name] = 0;
                    else if ($config->type == 'boolean')
                        $settings['config'][$config->name] = false;
                    else if ($config->type == 'select')
                        $settings['config'][$config->name] = $config->options[0];
                } else {
                    $settings['config'][$config->name] = $productSettings2->value;
                }
            } else {
                if ($config->type == 'text')
                    $settings['config'][$config->name] = '';
                else if ($config->type == 'number')
                    $settings['config'][$config->name] = 0;
                else if ($config->type == 'boolean')
                    $settings['config'][$config->name] = false;
                else if ($config->type == 'select')
                    $settings['config'][$config->name] = $config->options[0];
            }
        }

        // Export it as JSON
        $json = json_encode($settings, JSON_PRETTY_PRINT);
        $filename = $product->name . '.json';
        // Save the file
        return response(
            $json,
            200,
            [
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    public function extensionImport(Request $request, Products $product)
    {
        $request->validate([
            'json' => 'required|file|mimes:json',
        ]);
        // Move the file to the temp directory
        $file = $request->file('json');
        $file->move(storage_path('app/temp'), $file->getClientOriginalName());

        // Read the file
        $json = json_decode(file_get_contents(storage_path('app/temp/' . $file->getClientOriginalName())));
        // Delete the file
        unlink(storage_path('app/temp/' . $file->getClientOriginalName()));
        $server = Extensions::where('name', $json->server)->first();
        if(!$server)
            return redirect()->route('admin.products.extension', $product->id)->with('error', 'Invalid server');
        if (!file_exists(base_path('app/Extensions/Servers/' . $server->name . '/index.php'))) {
            $server = null;
            $extension = null;

            return redirect()->route('admin.products.extension', $product->id)->with('error', 'Extension not found');
        }
        if ($product->server_id != $server->id)
            $product->update(['server_id' => $server->id]);
            
        include_once base_path('app/Extensions/Servers/' . $server->name . '/index.php');
        $extension = new \stdClass();
        $function = $server->name . '_getProductConfig';
        $extension2 = json_decode(json_encode($function()));
        $extension->productConfig = $extension2;
        $extension->name = $server->name;
        if (!$json) {
            return redirect()->route('admin.products.extension', $product->id)->with('error', 'Invalid JSON');
        }
        if (!isset($json->server) || $json->server != $server->name) {
            return redirect()->route('admin.products.extension', $product->id)->with('error', 'Invalid server');
        }
        if (!isset($json->config)) {
            return redirect()->route('admin.products.extension', $product->id)->with('error', 'Invalid config');
        }

        // Delete all product settings
        ProductSettings::where('product_id', $product->id)->delete();

        foreach ($extension->productConfig as $config) {
            if (isset($json->config->{$config->name})) {
                ProductSettings::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'name' => $config->name,
                        'extension' => $product->server()->get()->first()->id,
                    ],
                    [
                        'product_id' => $product->id,
                        'name' => $config->name,
                        'value' => $json->config->{$config->name},
                        'extension' => $product->server()->get()->first()->id,
                    ]
                );
            }
        }

        return redirect()->route('admin.products.extension', $product->id)->with('success', 'Product updated successfully');
    }
}
